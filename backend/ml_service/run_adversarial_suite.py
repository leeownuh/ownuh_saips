from __future__ import annotations

import json
from datetime import datetime, timedelta
from typing import Any, Dict, List

from benchmark_dataset import build_benchmark_dataset, clone_cases
from benchmark_pipeline import evaluate_dataset, load_or_train_detectors
from feedback_store import feedback_summary
from report_utils import save_json_report


def mutate_ip_rotation(cases: List[Dict[str, Any]]) -> List[Dict[str, Any]]:
    mutated = clone_cases(cases)
    rotated_ips = ["45.83.64.19", "185.76.9.31", "154.73.12.8"]
    for case in mutated:
        if case["label"] != 1:
            continue
        for index, event in enumerate(case["events"]):
            if event["source_ip"]:
                event["source_ip"] = rotated_ips[index % len(rotated_ips)]
    return mutated


def mutate_timing_jitter(cases: List[Dict[str, Any]]) -> List[Dict[str, Any]]:
    mutated = clone_cases(cases)
    for case in mutated:
        for index, event in enumerate(case["events"]):
            stamp = datetime.strptime(event["created_at"], "%Y-%m-%d %H:%M:%S")
            event["created_at"] = (stamp + timedelta(minutes=((index % 3) - 1) * 7)).strftime("%Y-%m-%d %H:%M:%S")
    return mutated


def mutate_device_spoofing(cases: List[Dict[str, Any]]) -> List[Dict[str, Any]]:
    mutated = clone_cases(cases)
    for case in mutated:
        if case["label"] != 1:
            continue
        for index, event in enumerate(case["events"]):
            if event["device_fingerprint"]:
                event["device_fingerprint"] = f"spoofed_device_{index}"
    return mutated


def mutate_mfa_failure_noise(cases: List[Dict[str, Any]]) -> List[Dict[str, Any]]:
    mutated = clone_cases(cases)
    next_id = 9000
    for case in mutated:
        if case["attack_type"] != "MFA_BYPASS":
            continue
        first = case["events"][0]
        noise_event = dict(first)
        noise_event["id"] = next_id
        noise_event["event_code"] = "AUTH-002"
        noise_event["event_name"] = "Failed Login Attempt"
        noise_event["risk_score"] = 48
        noise_event["details"] = {"username": first["user_id"], "reason": "mfa_retry_noise"}
        case["events"].append(noise_event)
        next_id += 1
    return mutated


def main() -> None:
    dataset = build_benchmark_dataset()
    anomaly, attack = load_or_train_detectors()
    baseline = evaluate_dataset(dataset["test_cases"], anomaly, attack)
    scenarios_by_mode: Dict[str, Dict[str, Dict[str, Any]]] = {}
    scenario_cases = {
        "baseline": dataset["test_cases"],
        "ip_rotation": mutate_ip_rotation(dataset["test_cases"]),
        "timing_jitter": mutate_timing_jitter(dataset["test_cases"]),
        "device_spoofing": mutate_device_spoofing(dataset["test_cases"]),
        "mfa_failure_noise": mutate_mfa_failure_noise(dataset["test_cases"]),
    }

    for mode in ("graph_plus_anomaly", "temporal_graph_plus_anomaly"):
        mode_scenarios: Dict[str, Dict[str, Any]] = {}
        for name, cases in scenario_cases.items():
            result = baseline if name == "baseline" else evaluate_dataset(cases, anomaly, attack)
            mode_scenarios[name] = result["modes"][mode]

        baseline_recall = mode_scenarios["baseline"]["recall"]
        for name, metrics in mode_scenarios.items():
            metrics["recall_delta_vs_baseline"] = round(float(metrics["recall"]) - float(baseline_recall), 4)
        scenarios_by_mode[mode] = mode_scenarios

    report = {
        "mode": "multi_mode",
        "scenarios": scenarios_by_mode,
        "feedback": feedback_summary(),
        "dataset": {
            "test_cases": len(dataset["test_cases"]),
            "positive_cases": sum(case["label"] for case in dataset["test_cases"]),
            "negative_cases": sum(1 for case in dataset["test_cases"] if case["label"] == 0),
        },
    }
    report["report_path"] = save_json_report("latest_adversarial.json", report)
    print(json.dumps(report, indent=2))


if __name__ == "__main__":
    main()
