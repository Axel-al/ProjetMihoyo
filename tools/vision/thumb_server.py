#!/usr/bin/env python
"""
thumb_server.py
Serveur HTTP pour génération de thumbnails centrés sur le visage (si possible).

Pipeline:
  - Reçoit des jobs via POST /enqueue  (JSON: src, dst, width, height, job_id)
  - Enfile dans une queue
  - Un worker traite les jobs :
      -> SSD Anime Face
      -> si rien -> YOLOv8 Anime Face
      -> si toujours rien -> crop centré (50% largeur, 30% hauteur)
  - Écrit le thumbnail dans dst
"""

import threading
import queue
from pathlib import Path
from typing import Dict, Any, Optional, Tuple

import cv2
import numpy as np
from flask import Flask, request, jsonify

from detectors import get_ssd_detector
from detectors import get_yolo_detector

# -------------------------------------------------
# Config minimale
# -------------------------------------------------

app = Flask(__name__)

_job_queue: queue.Queue[dict] = queue.Queue()
_pending_jobs = set()
_processing_jobs = set()

# Lazy init des détecteurs (chargés une seule fois)
_SSD = None
_YOLO = None


def get_detectors():
    global _SSD, _YOLO
    if _SSD is None:
        _SSD = get_ssd_detector()  # modèle SSD
    if _YOLO is None:
        _YOLO = get_yolo_detector()  # modèle YOLO
    return _SSD, _YOLO


# -------------------------------------------------
# Crop intelligent centré sur un point de focus
# -------------------------------------------------

def compute_focus_box(
    img: np.ndarray,
    target_w: int,
    target_h: int,
    focus_x: float,
    focus_y: float,
    offset_box_x: float = 0.0,
    offset_box_y: float = 0.0
) -> Tuple[int, int, int, int]:
    """
    Calcule un rectangle de crop :
      - même ratio que (target_w, target_h)
      - le plus grand possible dans l'image
      - centré autant que possible sur (focus_x, focus_y)
      - sans sortir de l'image

    Retourne (x1, y1, x2, y2).
    """
    h, w = img.shape[:2]
    if h <= 0 or w <= 0:
        return 0, 0, w, h

    # Ratio cible
    if target_h <= 0:
        target_ratio = w / h
    else:
        target_ratio = target_w / target_h

    # On veut le plus grand rectangle possible de ce ratio dans l'image,
    # et ensuite on le translate pour qu'il soit centré sur (focus_x, focus_y).
    img_ratio = w / h

    if img_ratio >= target_ratio:
        # Image plus large que le ratio cible:
        # -> on garde toute la hauteur, et on réduit la largeur.
        box_h = h
        box_w = int(round(box_h * target_ratio))
    else:
        # Image plus haute (ou plus "carrée"):
        # -> on garde toute la largeur, et on réduit la hauteur.
        box_w = w
        box_h = int(round(box_w / target_ratio))

    box_w = max(1, min(box_w, w))
    box_h = max(1, min(box_h, h))

    focus_x += offset_box_x * box_w
    focus_y += offset_box_y * box_h

    # Centrage sur le point de focus
    cx = float(focus_x)
    cy = float(focus_y)

    x1 = int(round(cx - box_w / 2))
    y1 = int(round(cy - box_h / 2))
    x2 = x1 + box_w
    y2 = y1 + box_h

    # Clamp dans l'image sans changer la taille du box
    if x1 < 0:
        x2 -= x1
        x1 = 0
    if y1 < 0:
        y2 -= y1
        y1 = 0
    if x2 > w:
        x1 -= (x2 - w)
        x2 = w
    if y2 > h:
        y1 -= (y2 - h)
        y2 = h

    # Sécurité finale
    x1 = max(0, min(x1, w - 1))
    y1 = max(0, min(y1, h - 1))
    x2 = max(x1 + 1, min(x2, w))
    y2 = max(y1 + 1, min(y2, h))

    return x1, y1, x2, y2


def detect_best_face_box(
    img: np.ndarray
) -> Optional[Tuple[int, int, int, int]]:
    """
    Essaie SSD, puis YOLO, retourne une bbox ou None.
    """
    ssd, yolo = get_detectors()

    # 1. SSD
    box = ssd.detect_best_face(img)
    if box is not None:
        return box

    # 2. YOLO
    box = yolo.detect_best_face(img)
    if box is not None:
        return box

    # 3. None -> fallback géré ailleurs
    return None


# -------------------------------------------------
# Traitement d'un job
# -------------------------------------------------

def process_job(job: Dict[str, Any]) -> None:
    src = Path(job["src"])
    dst = Path(job["dst"])
    width = int(job["width"])
    height = int(job["height"])

    if not src.is_file():
        print(f"[WARN] Source image not found at processing time: {src}")
        return

    dst.parent.mkdir(parents=True, exist_ok=True)

    img = cv2.imread(str(src), cv2.IMREAD_COLOR)
    if img is None:
        print(f"[WARN] Failed to read image: {src}")
        return

    # 1. Détection visage (SSD -> YOLO)
    box = detect_best_face_box(img)

    offset_box_y = 0
    if box is not None:
        x1_face, y1_face, x2_face, y2_face = box
        # Point de focus = centre du visage détecté
        fx = (x1_face + x2_face) / 2.0
        fy = (y1_face + y2_face) / 2.0
        offset_box_y = 0.1
    else:
        h, w = img.shape[:2]
        # Fallback : point de focus par défaut (50% largeur, 30% hauteur)
        fx = w * 0.5
        fy = h * 0.3

    # 2. On calcule un crop minimal au bon ratio centré sur ce point
    x1, y1, x2, y2 = compute_focus_box(img, width, height, fx, fy, offset_box_y=offset_box_y)
    face = img[y1:y2, x1:x2]

    # 3. Resize vers la taille cible sans déformation de ratio (le crop a déjà le bon ratio)
    thumb = cv2.resize(face, (width, height), interpolation=cv2.INTER_AREA)
    cv2.imwrite(str(dst), thumb)
    print(f"[INFO] Wrote thumbnail {dst}")


def worker_loop() -> None:
    while True:
        job = _job_queue.get()
        jid = job.get("job_id")

        if jid is not None:
            _pending_jobs.discard(jid)
            _processing_jobs.add(jid)

        try:
            process_job(job)
        except Exception as e:
            print(f"[ERROR] Exception while processing job {jid}: {e}")
        finally:
            if jid is not None:
                _processing_jobs.discard(jid)
            _job_queue.task_done()


# -------------------------------------------------
# API HTTP
# -------------------------------------------------

@app.route("/enqueue", methods=["POST"])
def enqueue():
    """
    Reçoit un job JSON :
    {
      "job_id": "...",
      "src": "/abs/path/to/source.jpg",
      "dst": "/abs/path/to/thumb.jpg",
      "width": 400,
      "height": 600
    }

    Si job_id déjà en attente ou en cours → ne pas le rajouter.
    """
    data = request.get_json(silent=True) or {}

    job_id = data.get("job_id")
    src = data.get("src")
    dst = data.get("dst")
    width = data.get("width")
    height = data.get("height")

    # Vérif présence de base
    if not all([job_id, src, dst, width, height]):
        return jsonify({"ok": False, "error": "Missing parameters"}), 400

    # Vérif que le fichier source existe AVANT d'enqueuer
    if not Path(src).is_file():
        return jsonify({
            "ok": False,
            "error": "Source image not found",
            "src": src
        }), 404

    # Éviter les doublons
    if job_id in _pending_jobs or job_id in _processing_jobs:
        return jsonify({"ok": True, "status": "already_queued"})

    _pending_jobs.add(job_id)
    _job_queue.put({
        "job_id": job_id,
        "src": src,
        "dst": dst,
        "width": width,
        "height": height,
    })

    return jsonify({"ok": True, "status": "queued"})


@app.route("/health", methods=["GET"])
def health():
    """Petit endpoint pour vérifier que le serveur tourne."""
    return jsonify({
        "ok": True,
        "pending": len(_pending_jobs),
        "processing": len(_processing_jobs),
        "processing_jobs": list(_processing_jobs),
        "queue_size": _job_queue.qsize(),
    })


# -------------------------------------------------
# Entrée principale
# -------------------------------------------------

def main():
    worker_thread = threading.Thread(target=worker_loop, daemon=True)
    worker_thread.start()

    # Important: debug=False pour éviter le reloader qui relance le process 2x
    app.run(host="127.0.0.1", port=5001, debug=False)


if __name__ == "__main__":
    main()
