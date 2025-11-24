import os, io
import contextlib
from pathlib import Path
from typing import Optional, Tuple

import numpy as np
import torch

# Désactivation globale du gradient (on ne fait que de l'inférence)
torch.set_grad_enabled(False)

# Patch torch.load pour forcer weights_only=False
_real_torch_load = torch.load


def unsafe_torch_load(*args, **kwargs):
    kwargs.setdefault("weights_only", False)
    return _real_torch_load(*args, **kwargs)


torch.load = unsafe_torch_load  # monkey-patch global (même process)

os.environ["ULTRALYTICS_AUTOINSTALL"] = "False"
os.environ["ULTRALYTICS_AUTO_INSTALL"] = "False"

# Import silencieux d'ultralytics
with contextlib.redirect_stdout(io.StringIO()):
    from ultralytics import YOLO


class YOLOAnimeFaceDetector:
    def __init__(self, model_path: str, conf: float = 0.5, max_det: int = 1, imgsz: int = 1280):
        self.model = YOLO(model_path)
        self.model.overrides["conf"] = conf
        self.model.overrides["max_det"] = max_det
        self.imgsz = imgsz
        self.min_conf = conf

    def detect_best_face(self, img_bgr: np.ndarray) -> Optional[Tuple[int, int, int, int]]:
        """
        Détecte un visage avec YOLOv8 sur une image BGR (H, W, 3).
        Retourne (xmin, ymin, xmax, ymax) ou None.
        """
        if img_bgr is None or img_bgr.size == 0:
            return None

        # Ultralytics accepte les ndarrays directement
        results = self.model.predict(img_bgr, imgsz=self.imgsz, verbose=False)
        if not results:
            return None

        result = results[0]
        boxes = result.boxes
        if boxes is None or boxes.xyxy is None or boxes.xyxy.shape[0] == 0:
            return None

        xyxy = boxes.xyxy.cpu().numpy()  # shape (N, 4)
        scores = boxes.conf.cpu().numpy()  # shape (N,)

        if xyxy.shape[0] == 0:
            return None

        k = int(scores.argmax())
        if scores[k] < self.min_conf:
            return None

        x1, y1, x2, y2 = xyxy[k]
        return int(x1), int(y1), int(x2), int(y2)


_YOLO_DETECTOR: Optional[YOLOAnimeFaceDetector] = None


def get_yolo_detector(model_path: Optional[str] = None) -> YOLOAnimeFaceDetector:
    """
    Singleton pour le modèle YOLO anime face.
    """
    global _YOLO_DETECTOR
    if _YOLO_DETECTOR is None:
        if model_path is None:
            base_dir = Path(__file__).resolve().parents[1]
            model_path = base_dir / "models" / "yolov8x6_animeface.pt"
        _YOLO_DETECTOR = YOLOAnimeFaceDetector(str(model_path))
    return _YOLO_DETECTOR
