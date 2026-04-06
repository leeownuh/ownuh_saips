from __future__ import annotations

import json

from benchmark_dataset import build_benchmark_dataset
from benchmark_pipeline import evaluate_dataset, load_or_train_detectors


def main() -> None:
    dataset = build_benchmark_dataset()
    anomaly, attack = load_or_train_detectors()
    report = evaluate_dataset(dataset["test_cases"], anomaly, attack)
    report["dataset"] = {
        "train_events": len(dataset["train_events"]),
        "test_cases": len(dataset["test_cases"]),
        "positive_cases": sum(case["label"] for case in dataset["test_cases"]),
        "negative_cases": sum(1 for case in dataset["test_cases"] if case["label"] == 0),
    }
    print(json.dumps(report, indent=2))


if __name__ == "__main__":
    main()
