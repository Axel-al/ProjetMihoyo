import sys
import subprocess
import venv
import shutil
import re
import argparse
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
VENV_DIR = ROOT / ".venv"
REQUIREMENTS_FILE = ROOT / "requirements.txt"

# PyTorch CUDA tags (newest to oldest)
TORCH_GPU_TAGS = [
    "cu130",
    "cu129",
    "cu128",
    "cu126",
    "cu124",
    "cu121",
    "cu118",
    "cu117",
    "cu116",
    "cu115",
    "cu113"
]


def check_requirements_forbidden_packages():
    """
    Ensure requirements.txt does NOT include torch or torchvision.
    If it does → print error and exit.
    """
    if not REQUIREMENTS_FILE.is_file():
        return

    forbidden = ("torch", "torchvision")

    with REQUIREMENTS_FILE.open("r", encoding="utf-8") as f:
        lines = f.readlines()

    for line in lines:
        low = line.strip().lower()

        for pkg in forbidden:
            # Disallow:
            #  torch
            #  torch==...
            #  torch>=...
            #  torch<=...
            #  torch-xxx
            if (
                low == pkg
                or low.startswith(pkg + "=")
                or low.startswith(pkg + ">")
                or low.startswith(pkg + "<")
                or low.startswith(pkg + "-")
            ):
                print(
                    f"[Error] Forbidden package found in {REQUIREMENTS_FILE} : '{line.strip()}'.\n"
                    "torch / torchvision MUST NOT be installed via requirements.txt.\n"
                    "They are automatically handled (GPU/CPU) by this script.\n"
                )
                sys.exit(1)


def run(cmd, **kwargs):
    print("+", " ".join(cmd))
    subprocess.check_call(cmd, **kwargs)


def create_venv():
    print(f"Creating virtual environment in: {VENV_DIR}")
    venv.create(VENV_DIR, with_pip=True)

    if sys.platform.startswith("win"):
        python = VENV_DIR / "Scripts" / "python.exe"
    else:
        python = VENV_DIR / "bin" / "python"

    return str(python)


def has_nvidia_gpu():
    return shutil.which("nvidia-smi") is not None


def _parse_cuda_version_str(version_str):
    """
    Parse a CUDA version string like '12.6' or '11.8' → float version.
    Returns None if invalid.
    """
    if not version_str:
        return None

    match = re.search(r"(\d+)\.(\d+)", version_str)
    if not match:
        return None

    major = int(match.group(1))
    minor = int(match.group(2))
    return major + minor / 10.0


def detect_cuda_version():
    """
    Detect installed CUDA version:
      1) from `nvidia-smi`
      2) fallback to `nvcc --version`

    Returns a float (e.g. 12.6) or None.
    """
    # Try nvidia-smi
    try:
        result = subprocess.run(
            ["nvidia-smi"],
            stdout=subprocess.PIPE,
            stderr=subprocess.PIPE,
            text=True,
            check=False
        )

        if result.returncode == 0:
            m = re.search(r"CUDA Version:\s*([\d\.]+)", result.stdout)
            if m:
                ver = _parse_cuda_version_str(m.group(1))
                if ver is not None:
                    print(f"Detected CUDA version via nvidia-smi: {ver}")
                    return ver
    except Exception:
        pass

    # Try nvcc
    if shutil.which("nvcc"):
        try:
            result = subprocess.run(
                ["nvcc", "--version"],
                stdout=subprocess.PIPE,
                stderr=subprocess.PIPE,
                text=True,
                check=False
            )

            if result.returncode == 0:
                m = re.search(r"release\s+([\d\.]+)", result.stdout)
                if m:
                    ver = _parse_cuda_version_str(m.group(1))
                    if ver is not None:
                        print(f"Detected CUDA version via nvcc: {ver}")
                        return ver
        except Exception:
            pass

    print("[Warning] Unable to detect CUDA version.")
    return None


def cuda_tag_to_float(tag):
    """
    Convert tag 'cu118' → 11.8, 'cu130' → 13.0, etc.
    """
    num = int(tag[2:])
    major = num // 10
    minor = num % 10
    return major + minor / 10.0


def _build_torch_packages(torch_version: str | None,
                          torchvision_version: str | None) -> list[str]:
    """
    Build the ['torch', 'torchvision'] list, optionally with ==X.Y.Z versions.
    """
    pkgs: list[str] = []
    if torch_version:
        pkgs.append(f"torch=={torch_version}")
    else:
        pkgs.append("torch")

    if torchvision_version:
        pkgs.append(f"torchvision=={torchvision_version}")
    else:
        pkgs.append("torchvision")

    return pkgs


def install_torch_cpu(pip_cmd,
                      torch_version: str | None = None,
                      torchvision_version: str | None = None):
    print("Installing torch/torchvision CPU...")
    packages = _build_torch_packages(torch_version, torchvision_version)
    run(pip_cmd + [
        "install",
        *packages,
        "--index-url", "https://download.pytorch.org/whl/cpu",
    ])
    print("[Success] Installed torch (CPU).")
    return False


def install_torch(
    pip_cmd,
    force_cpu: bool = False,
    user_cuda_version: float | None = None,
    user_cuda_tag: str | None = None,
    torch_version: str | None = None,
    torchvision_version: str | None = None,
):
    """
    Install torch/torchvision.

    If force_cpu is True → install CPU directly.
    If user_cuda_tag is provided → try that tag only.
    If user_cuda_version is provided → select tags <= this version.
    Otherwise → auto-detect CUDA version and pick best tag, or fallback to CPU.

    If torch_version / torchvision_version are provided, they are used as
    explicit versions (torch==X / torchvision==Y) with the chosen index-url.
    """
    # Force CPU requested
    if force_cpu:
        print("[Info] Forcing CPU installation (--cpu).")
        if torch_version:
            print(f"[Info] Forcing torch version: {torch_version}")
        if torchvision_version:
            print(f"[Info] Forcing torchvision version: {torchvision_version}")
        return install_torch_cpu(pip_cmd, torch_version, torchvision_version)

    # If a specific CUDA tag is requested, try only that one
    if user_cuda_tag:
        tag = user_cuda_tag
        if not tag.startswith("cu"):
            tag = "cu" + tag  # allow e.g. "118"
        print(f"[Info] Forcing PyTorch CUDA tag: {tag}")
        if torch_version:
            print(f"[Info] Forcing torch version: {torch_version}")
        if torchvision_version:
            print(f"[Info] Forcing torchvision version: {torchvision_version}")

        url = f"https://download.pytorch.org/whl/{tag}"
        packages = _build_torch_packages(torch_version, torchvision_version)
        try:
            run(pip_cmd + ["install", *packages, "--index-url", url])
            print(f"[Success] Installed torch GPU ({tag}).")
            return True
        except subprocess.CalledProcessError:
            print(f"[Error] Failed to install torch for tag {tag}. Falling back to CPU.")
            return install_torch_cpu(pip_cmd, torch_version, torchvision_version)

    # If a specific CUDA version is requested, use it instead of system detection
    if user_cuda_version is not None:
        print(f"[Info] Using user-specified CUDA version: {user_cuda_version}")
        if torch_version:
            print(f"[Info] Forcing torch version: {torch_version}")
        if torchvision_version:
            print(f"[Info] Forcing torchvision version: {torchvision_version}")

        compatible_tags = [
            tag for tag in TORCH_GPU_TAGS
            if cuda_tag_to_float(tag) <= user_cuda_version
        ]
        if not compatible_tags:
            print(
                "[Warning] No PyTorch CUDA tag <= requested CUDA version.\n"
                "Falling back to CPU torch."
            )
            return install_torch_cpu(pip_cmd, torch_version, torchvision_version)

        print("Candidate CUDA tags for requested version:", ", ".join(compatible_tags))
        candidate_tags = compatible_tags

        for tag in candidate_tags:
            url = f"https://download.pytorch.org/whl/{tag}"
            print(f"Trying torch/torchvision for {tag} (requested version)...")
            packages = _build_torch_packages(torch_version, torchvision_version)
            try:
                run(pip_cmd + ["install", *packages, "--index-url", url])
                print(f"[Success] Installed torch GPU ({tag}).")
                return True
            except subprocess.CalledProcessError:
                print(f"[Warning] Failed for {tag}")

        print("[Warning] All requested-version GPU installs failed → using CPU.")
        return install_torch_cpu(pip_cmd, torch_version, torchvision_version)

    # Automatic mode (original behavior)
    if not has_nvidia_gpu():
        print("No NVIDIA GPU detected → installing CPU version.")
        return install_torch_cpu(pip_cmd, torch_version, torchvision_version)

    print("NVIDIA GPU detected.")

    cuda_system_version = detect_cuda_version()

    if cuda_system_version is not None:
        compatible_tags = [
            tag for tag in TORCH_GPU_TAGS
            if cuda_tag_to_float(tag) <= cuda_system_version
        ]

        if not compatible_tags:
            print(
                "[Warning] No PyTorch CUDA tag <= system CUDA version.\n"
                "Falling back to CPU torch."
            )
            return install_torch_cpu(pip_cmd, torch_version, torchvision_version)

        print("Compatible CUDA tags:", ", ".join(compatible_tags))
        candidate_tags = compatible_tags

    else:
        print("[Warning] Could not detect CUDA → trying all tags.")
        candidate_tags = TORCH_GPU_TAGS

    if torch_version:
        print(f"[Info] Forcing torch version: {torch_version}")
    if torchvision_version:
        print(f"[Info] Forcing torchvision version: {torchvision_version}")

    for tag in candidate_tags:
        url = f"https://download.pytorch.org/whl/{tag}"
        print(f"Trying torch/torchvision for {tag}...")
        packages = _build_torch_packages(torch_version, torchvision_version)
        try:
            run(pip_cmd + ["install", *packages, "--index-url", url])
            print(f"[Success] Installed torch GPU ({tag}).")
            return True
        except subprocess.CalledProcessError:
            print(f"[Warning] Failed for {tag}")

    print("[Warning] All GPU installs failed → using CPU.")
    return install_torch_cpu(pip_cmd, torch_version, torchvision_version)


def install_requirements(pip_cmd):
    if REQUIREMENTS_FILE.is_file():
        print(f"Installing additional dependencies from {REQUIREMENTS_FILE}...")
        run(pip_cmd + ["install", "-r", str(REQUIREMENTS_FILE)])
    else:
        print("[Info] requirements.txt not found, nothing to install.")


def parse_args(argv):
    parser = argparse.ArgumentParser(
        description="Create venv and install PyTorch (GPU/CPU) + requirements."
    )
    group = parser.add_mutually_exclusive_group()
    group.add_argument(
        "--cpu",
        action="store_true",
        help="Force CPU version of torch/torchvision.",
    )
    group.add_argument(
        "--cuda-version",
        type=str,
        help="Force a given CUDA version for PyTorch (e.g. 11.8).",
    )
    group.add_argument(
        "--cuda-tag",
        type=str,
        help="Force a given PyTorch CUDA tag (e.g. cu118).",
    )

    parser.add_argument(
        "--torch-version",
        type=str,
        help="Force a specific torch version (e.g. 2.2.2).",
    )
    parser.add_argument(
        "--torchvision-version",
        type=str,
        help="Force a specific torchvision version (e.g. 0.17.2).",
    )

    return parser.parse_args(argv)


def main(argv=None):
    if argv is None:
        argv = sys.argv[1:]

    args = parse_args(argv)

    # Python version check
    if not (sys.version_info.major == 3 and 10 <= sys.version_info.minor <= 13):
        print(
            f"[Error] Unsupported Python version: {sys.version.split()[0]}\n"
            "This script supports Python 3.10 → 3.13."
        )
        sys.exit(1)

    # Determine user-specified CUDA settings
    user_cuda_version = None
    user_cuda_tag = None

    if args.cuda_version:
        # Try to parse as numeric version, but also accept a tag-like string (cu118)
        ver = _parse_cuda_version_str(args.cuda_version)
        if ver is None and args.cuda_version.startswith("cu") and args.cuda_version[2:].isdigit():
            ver = cuda_tag_to_float(args.cuda_version)
        if ver is None:
            print(f"[Error] Invalid --cuda-version value: {args.cuda_version}")
            sys.exit(1)
        user_cuda_version = ver

    if args.cuda_tag:
        user_cuda_tag = args.cuda_tag.strip()

    check_requirements_forbidden_packages()

    python = create_venv()
    pip_cmd = [python, "-m", "pip"]

    run(pip_cmd + ["install", "--upgrade", "pip"])

    gpu_used = install_torch(
        pip_cmd,
        force_cpu=args.cpu,
        user_cuda_version=user_cuda_version,
        user_cuda_tag=user_cuda_tag,
        torch_version=args.torch_version,
        torchvision_version=args.torchvision_version,
    )
    install_requirements(pip_cmd)

    print("\nTorch verification:")
    run([
        python,
        "-c",
        "import torch;print('torch:', torch.__version__, "
        "'cuda available:', torch.cuda.is_available(), "
        "'cuda version:', torch.version.cuda)"
    ])

    print("\nInstallation complete.")
    print("Torch GPU used:", "Yes" if gpu_used else "No")

    print(
        "\nPyTorch and TorchVision CUDA can take up several gigabytes in the pip cache.\n"
        "If you want to free up disk space:\n"
        "\tpip cache purge\n"
        "(Optional — note that future installations will be slower.)"
        )

    print("\nTo activate the venv:")

    if sys.platform.startswith("win"):
        print(
            f"  {VENV_DIR / 'Scripts' / 'activate.bat'}\n"
            "  or\n"
            f"  {VENV_DIR / 'Scripts' / 'Activate.ps1'}"
        )
    else:
        print(f"  source {VENV_DIR / 'bin' / 'activate'}")


if __name__ == "__main__":
    main()
