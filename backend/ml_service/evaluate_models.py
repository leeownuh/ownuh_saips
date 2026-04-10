from __future__ import annotations

import json

from benchmark_dataset import build_benchmark_dataset
from benchmark_pipeline import evaluate_dataset, load_or_train_detectors
from cross_environment_eval import evaluate_cross_environment
from export_case_studies import export_case_studies
from feedback_store import recent_feedback
from report_utils import save_json_report
from time_window_eval import evaluate_time_windows


def main() -> None:
    dataset = build_benchmark_dataset()
    anomaly, attack = load_or_train_detectors()
    report = evaluate_dataset(dataset["test_cases"], anomaly, attack)
    report["dataset"] = {
        "train_events": len(dataset["train_events"]),
        "test_cases": len(dataset["test_cases"]),
        "positive_cases": sum(case["label"] for case in dataset["test_cases"]),
        "negative_cases": sum(1 for case in dataset["test_cases"] if case["label"] == 0),
        "dataset_version": dataset.get("dataset_version", "v1"),
        "dataset_source": dataset.get("dataset_source", "generated"),
    }
    report["feedback_recent"] = recent_feedback(limit=10)
    report["time_window_evaluation"] = evaluate_time_windows(dataset)
    report["cross_environment_generalization"] = evaluate_cross_environment(dataset, anomaly, attack)
    report["case_study_export"] = export_case_studies(top_n=5, evaluation=report)
    report["report_path"] = save_json_report("latest_evaluation.json", report)
    print(json.dumps(report, indent=2))


if __name__ == "__main__":
    main()
