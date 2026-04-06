from __future__ import annotations

import json

from benchmark_dataset import build_benchmark_dataset
from benchmark_pipeline import train_detectors


def main() -> None:
    dataset = build_benchmark_dataset()
    train_detectors()
    print(json.dumps({
        "status": "success",
        "train_events": len(dataset["train_events"]),
        "models_saved": [
            "models/anomaly_detector_if.pkl",
            "models/anomaly_detector_pca.pkl",
            "models/anomaly_detector_scaler.pkl",
            "models/attack_detector_clf.pkl",
            "models/attack_detector_scaler.pkl",
        ],
    }, indent=2))


if __name__ == "__main__":
    main()
