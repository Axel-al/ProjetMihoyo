# detectors/__init__.py

from .anime_face_ssd import get_ssd_detector
from .anime_face_yolo import get_yolo_detector

__all__ = [
    "get_ssd_detector",
    "get_yolo_detector",
]