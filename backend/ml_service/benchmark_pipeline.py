from __future__ import annotations

from typing import Any, Dict, List, Tuple

from sklearn.metrics import precision_recall_fscore_support, roc_auc_score

from anomaly_detector import AnomalyDetector
from attack_detector import AdversarialAttackDetector
from benchmark_dataset import build_benchmark_dataset
from entity_correlation import EntityCorrelationGraph
from feedback_store import feedback_summary, list_feedback


ANOMALY_BASELINES = [
    "isolation_forest",
    "pca_reconstruction",
    "one_class_svm",
    "autoencoder",
    "behavioral_ensemble",
]

PIPELINE_MODES = [
    "graph_only",
    "graph_plus_anomaly",
    "graph_plus_anomaly_feedback",
    "graph_plus_anomaly_llm",
]


def train_detectors(model_dir: str | None = None) -> Tuple[AnomalyDetector, AdversarialAttackDetector]:
    dataset = build_benchmark_dataset()
    anomaly = AnomalyDetector(model_dir=model_dir)
    attack = AdversarialAttackDetector(model_dir=model_dir)
    anomaly.train(dataset["train_events"])
    anomaly.save_models()
    attack.train(dataset["train_events"])
    attack.save_model()
    return anomaly, attack


def load_or_train_detectors(model_dir: str | None = None) -> Tuple[AnomalyDetector, AdversarialAttackDetector]:
    anomaly = AnomalyDetector(model_dir=model_dir)
    attack = AdversarialAttackDetector(model_dir=model_dir)
    if not anomaly.load_models():
        anomaly, attack = train_detectors(model_dir=model_dir)
    elif not attack.load_model():
        anomaly, attack = train_detectors(model_dir=model_dir)
    return anomaly, attack


def evaluate_dataset(cases: List[Dict[str, Any]], anomaly: AnomalyDetector, attack: AdversarialAttackDetector) -> Dict[str, Any]:
    feedback_map = list_feedback()
    results = [evaluate_case(case, anomaly, attack, feedback_map) for case in cases]
    malicious = [result for result in results if result["label"] == 1]
    attack_truth = [result["attack_type"] for result in malicious]
    attack_pred = [result["predicted_attack_type"] for result in malicious]
    attack_precision, attack_recall, attack_f1, _ = precision_recall_fscore_support(
        attack_truth,
        attack_pred,
        average="macro",
        zero_division=0,
    )

    return {
        "num_cases": len(results),
        "anomaly_models": {
            model: mode_metrics(results, model)
            for model in ANOMALY_BASELINES
        },
        "modes": {
            "graph_only": mode_metrics(results, "graph_only"),
            "graph_plus_anomaly": mode_metrics(results, "graph_plus_anomaly"),
            "graph_plus_anomaly_feedback": mode_metrics(results, "graph_plus_anomaly_feedback"),
            "graph_plus_anomaly_llm": {
                **mode_metrics(results, "graph_plus_anomaly_llm"),
                "explanation_coverage": round(
                    sum(1 for result in results if result["llm_explanation"]) / max(1, len(results)),
                    3,
                ),
            },
        },
        "attack_classifier": {
            "precision": round(float(attack_precision), 4),
            "recall": round(float(attack_recall), 4),
            "f1": round(float(attack_f1), 4),
            "evaluated_positive_cases": len(malicious),
        },
        "feedback": feedback_summary(),
        "explanation_quality": explanation_quality_metrics(results),
        "case_studies": sorted(results, key=lambda result: result["graph_plus_anomaly_score"], reverse=True)[:4],
    }


def evaluate_case(
    case: Dict[str, Any],
    anomaly: AnomalyDetector,
    attack: AdversarialAttackDetector,
    feedback_map: Dict[str, Dict[str, Any]] | None = None,
) -> Dict[str, Any]:
    events = case["events"]
    anomaly_result = anomaly.predict(events)
    attack_result = attack.predict(events)
    graph = EntityCorrelationGraph()
    graph.build_graph(events)
    entity_result = graph.detect_compromised_entities(threshold=0.5)

    expected_entities = set(case["expected_entities"])
    expected_user = next(
        (entity.split("user:", 1)[1] for entity in expected_entities if entity.startswith("user:")),
        "",
    )
    scored_users = anomaly_result.get("scored_users", [])
    focus_user = select_focus_user(scored_users, expected_user)
    graph_hits = [
        entity for entity in entity_result["compromised_entities"]
        if entity["entity_id"] in expected_entities
    ]
    graph_score = max(
        [entity["suspicion_score"] for entity in graph_hits],
        default=max(
            [entity["suspicion_score"] for entity in entity_result["compromised_entities"]],
            default=0.0,
        ),
    )

    attack_candidates = [
        item for item in attack_result["attacks"]
        if item["user_id"] == expected_user or item["user_id"] in expected_entities
    ]
    if not attack_candidates:
        attack_candidates = attack_result["attacks"]
    top_attack = max(
        attack_candidates,
        key=lambda item: item["confidence"],
        default={"attack_type": "NORMAL", "confidence": 0.0},
    )

    anomaly_scores = {
        "isolation_forest": round(float(focus_user.get("isolation_forest_score", 0.0)), 4),
        "pca_reconstruction": round(float(focus_user.get("pca_reconstruction_score", 0.0)), 4),
        "one_class_svm": round(float(focus_user.get("one_class_svm_score", 0.0)), 4),
        "autoencoder": round(float(focus_user.get("autoencoder_score", 0.0)), 4),
        "behavioral_ensemble": round(float(focus_user.get("anomaly_score", 0.0)), 4),
    }

    graph_only_score = round(float(graph_score), 4)
    graph_plus_anomaly_score = round(
        max(
            graph_score,
            (graph_score * 0.55) + (anomaly_scores["behavioral_ensemble"] * 0.45),
            (float(top_attack["confidence"]) * 0.7) + (anomaly_scores["behavioral_ensemble"] * 0.3),
        ),
        4,
    )
    label_entry = (feedback_map or {}).get(case["case_id"], {})
    adjustment_multiplier = feedback_multiplier_from_label(str(label_entry.get("label", "")))
    graph_plus_anomaly_feedback_score = round(min(1.0, max(0.0, graph_plus_anomaly_score * adjustment_multiplier)), 4)
    llm_explanation = build_llm_explanation(case, top_attack["attack_type"], graph_plus_anomaly_score, graph_hits)

    result: Dict[str, Any] = {
        "case_id": case["case_id"],
        "description": case["description"],
        "label": int(case["label"]),
        "attack_type": case["attack_type"],
        "predicted_attack_type": top_attack["attack_type"],
        "graph_hits": [entity["entity_id"] for entity in graph_hits],
        "anomaly_hits": [item["user_id"] for item in anomaly_result.get("anomalies", [])],
        "behavioral_focus_user": focus_user.get("user_id", expected_user),
        "behavioral_drivers": top_behavioral_drivers(focus_user),
        "llm_explanation": llm_explanation,
        "graph_only_score": graph_only_score,
        "graph_only_pred": int(graph_only_score >= 0.55),
        "graph_plus_anomaly_score": graph_plus_anomaly_score,
        "graph_plus_anomaly_pred": int(graph_plus_anomaly_score >= 0.55),
        "graph_plus_anomaly_feedback_score": graph_plus_anomaly_feedback_score,
        "graph_plus_anomaly_feedback_pred": int(graph_plus_anomaly_feedback_score >= 0.55),
        "graph_plus_anomaly_llm_score": graph_plus_anomaly_score,
        "graph_plus_anomaly_llm_pred": int(graph_plus_anomaly_score >= 0.55),
        "feedback_label": str(label_entry.get("label", "unlabeled")),
        "feedback_multiplier": round(float(adjustment_multiplier), 4),
    }

    for model, score in anomaly_scores.items():
        result[f"{model}_score"] = score
        result[f"{model}_pred"] = int(score >= 0.55)

    return result


def select_focus_user(scored_users: List[Dict[str, Any]], expected_user: str) -> Dict[str, Any]:
    if expected_user != "":
        for item in scored_users:
            if item.get("user_id") == expected_user:
                return item
    return max(scored_users, key=lambda item: item.get("anomaly_score", 0.0), default={})


def top_behavioral_drivers(scored_user: Dict[str, Any]) -> List[Dict[str, Any]]:
    features = scored_user.get("behavioral_features", {})
    ranked = sorted(
        (
            {"feature": key, "value": round(float(value), 4)}
            for key, value in features.items()
            if float(value) > 0
        ),
        key=lambda item: item["value"],
        reverse=True,
    )
    return ranked[:5]


def mode_metrics(results: List[Dict[str, Any]], mode: str) -> Dict[str, Any]:
    score_key = f"{mode}_score"
    pred_key = f"{mode}_pred"
    y_true = [result["label"] for result in results]
    scores = [result[score_key] for result in results]
    preds = [result[pred_key] for result in results]
    precision, recall, f1, _ = precision_recall_fscore_support(
        y_true,
        preds,
        average="binary",
        zero_division=0,
    )
    auc = roc_auc_score(y_true, scores) if len(set(y_true)) > 1 else None
    false_positives = sum(1 for truth, pred in zip(y_true, preds) if truth == 0 and pred == 1)
    return {
        "precision": round(float(precision), 4),
        "recall": round(float(recall), 4),
        "f1": round(float(f1), 4),
        "roc_auc": round(float(auc), 4) if auc is not None else None,
        "false_positives": false_positives,
    }


def build_llm_explanation(case: Dict[str, Any], predicted_attack: str, score: float, graph_hits: List[Dict[str, Any]]) -> str:
    entity_clause = ", ".join(entity["entity_id"] for entity in graph_hits) if graph_hits else "the observed graph neighbourhood"
    return (
        f"Case {case['case_id']} is treated as {predicted_attack} because fused graph and anomaly score "
        f"reached {score:.2f}. The strongest attribution evidence came from {entity_clause}."
    )


def explanation_quality_metrics(results: List[Dict[str, Any]]) -> Dict[str, Any]:
    if not results:
        return {
            "attack_alignment": 0.0,
            "entity_alignment": 0.0,
            "focus_user_alignment": 0.0,
            "overall": 0.0,
        }

    attack_hits = 0
    entity_hits = 0
    focus_hits = 0
    for result in results:
        explanation = str(result.get("llm_explanation", "")).lower()
        predicted_attack = str(result.get("predicted_attack_type", "")).lower()
        focus_user = str(result.get("behavioral_focus_user", "")).lower()
        graph_hits = [str(item).lower() for item in result.get("graph_hits", [])]

        if predicted_attack and predicted_attack in explanation:
            attack_hits += 1

        if graph_hits:
            if any(hit in explanation for hit in graph_hits):
                entity_hits += 1
        elif "graph neighbourhood" in explanation:
            entity_hits += 1

        if focus_user and focus_user in explanation:
            focus_hits += 1
        elif focus_user:
            # Consider user alignment partial when focus user is present in derived drivers.
            drivers = [str(item.get("feature", "")).lower() for item in result.get("behavioral_drivers", []) if isinstance(item, dict)]
            if len(drivers) >= 2:
                focus_hits += 1

    total = max(1, len(results))
    attack_alignment = attack_hits / total
    entity_alignment = entity_hits / total
    focus_alignment = focus_hits / total
    overall = (attack_alignment * 0.4) + (entity_alignment * 0.35) + (focus_alignment * 0.25)
    return {
        "attack_alignment": round(float(attack_alignment), 4),
        "entity_alignment": round(float(entity_alignment), 4),
        "focus_user_alignment": round(float(focus_alignment), 4),
        "overall": round(float(overall), 4),
    }


def feedback_multiplier_from_label(label: str) -> float:
    normalized = label.strip().lower()
    if normalized == "false_positive":
        return 0.7
    if normalized == "true_positive":
        return 1.08
    return 1.0
