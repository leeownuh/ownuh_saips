from __future__ import annotations

import json

from benchmark_dataset import build_benchmark_dataset
from benchmark_pipeline import train_detectors
from report_utils import save_json_report


def main() -> None:
    dataset = build_benchmark_dataset()
    anomaly, attack = train_detectors()

    training_report = {
        "status": "success",
        "train_events": len(dataset["train_events"]),
        "train_users": len({str(event.get("user_id", "")) for event in dataset["train_events"] if str(event.get("user_id", "")).strip() not in {"", "unknown"}}),
        "feature_count": len(getattr(anomaly, "feature_names", [])),
        "temporal_features_enabled": True,
        "models_saved": [
            "models/anomaly_detector_if.pkl",
            "models/anomaly_detector_pca.pkl",
            "models/anomaly_detector_ocsvm.pkl",
            "models/anomaly_detector_autoencoder.pkl",
            "models/anomaly_detector_scaler.pkl",
            "models/anomaly_detector_meta.pkl",
            "models/attack_detector_clf.pkl",
            "models/attack_detector_scaler.pkl",
        ],
        "anomaly_models": [
            "isolation_forest",
            "pca_reconstruction",
            "one_class_svm",
            "autoencoder",
            "behavioral_ensemble",
        ],
        "attack_model": "random_forest_classifier",
        "detectors_ready": bool(anomaly.models_trained and attack.model_trained),
    }

    report_path = save_json_report("latest_training.json", training_report)
    training_report["report_path"] = report_path
    print(json.dumps(training_report, indent=2))


if __name__ == "__main__":
    main()
