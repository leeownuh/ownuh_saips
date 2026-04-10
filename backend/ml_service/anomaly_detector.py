#!/usr/bin/env python3
"""
Ownuh SAIPS - ML-Based Anomaly Detection Service
Detects anomalous login patterns, IPS events, and entity behavior using a
behavioral feature engine plus multiple anomaly baselines.

Research alignment: Dr. Euijin Choo - Anomaly Detection in Network Traffic and
Enterprise Logs
"""

from __future__ import annotations

import json
import logging
import pickle
import sys
from pathlib import Path
from typing import Any, Dict, List, Tuple

import numpy as np
from sklearn.decomposition import PCA
from sklearn.ensemble import IsolationForest
from sklearn.neural_network import MLPRegressor
from sklearn.preprocessing import StandardScaler
from sklearn.svm import OneClassSVM

from behavioral_features import (
    build_user_behavioral_summaries,
    summaries_to_matrix,
    user_behavior_feature_names,
)

logging.basicConfig(level=logging.INFO, format="%(asctime)s [%(levelname)s] %(message)s")
logger = logging.getLogger(__name__)


def load_input_payload(argument: str) -> Dict[str, Any]:
    if argument.startswith("@"):
        payload_path = Path(argument[1:])
        if not payload_path.exists():
            raise FileNotFoundError(f"Payload file not found: {payload_path}")
        return json.loads(payload_path.read_text(encoding="utf-8-sig"))
    return json.loads(argument)


class AnomalyDetector:
    """
    Multi-model anomaly detection system for security events.

    Models:
    1. Isolation Forest
    2. PCA reconstruction error
    3. One-Class SVM
    4. Autoencoder-style MLP reconstruction
    """

    def __init__(self, model_dir: str | None = None):
        self.model_dir = Path(model_dir) if model_dir else Path(__file__).parent / "models"
        self.model_dir.mkdir(exist_ok=True)

        self.isolation_forest: IsolationForest | None = None
        self.pca_model: PCA | None = None
        self.one_class_svm: OneClassSVM | None = None
        self.autoencoder: MLPRegressor | None = None
        self.scaler = StandardScaler()
        self.feature_names = user_behavior_feature_names()

        self.if_score_threshold = 0.0
        self.pca_error_threshold = 0.0
        self.ocsvm_score_threshold = 0.0
        self.autoencoder_error_threshold = 0.0

        self.models_trained = False

    def _extract_behavioral_matrix(
        self, audit_events: List[Dict[str, Any]]
    ) -> Tuple[np.ndarray, List[str], List[Dict[str, Any]]]:
        summaries = build_user_behavioral_summaries(audit_events)
        matrix, event_ids, ordered = summaries_to_matrix(summaries)
        self.feature_names = user_behavior_feature_names()
        return matrix, event_ids, ordered

    def extract_features_from_audit_log(self, audit_events: List[Dict[str, Any]]) -> Tuple[np.ndarray, List[str]]:
        matrix, event_ids, _ = self._extract_behavioral_matrix(audit_events)
        return matrix, event_ids

    @staticmethod
    def _threshold_from_values(values: np.ndarray, quantile: float = 0.85) -> float:
        flattened = np.asarray(values, dtype=float).ravel()
        if flattened.size == 0:
            return 0.0
        candidate = float(np.quantile(flattened, quantile))
        mean = float(np.mean(flattened))
        std = float(np.std(flattened))
        return max(candidate, mean + (0.5 * std), 1e-6)

    @staticmethod
    def _normalize_positive(values: np.ndarray, threshold: float) -> np.ndarray:
        flattened = np.maximum(np.asarray(values, dtype=float).ravel(), 0.0)
        scale = max(float(threshold) * 1.5, 1e-6)
        return np.clip(flattened / scale, 0.0, 1.0)

    @staticmethod
    def _risk_level(score: float) -> str:
        if score >= 0.75:
            return "high"
        if score >= 0.45:
            return "medium"
        return "low"

    def _models_ready(self) -> bool:
        return all(
            model is not None
            for model in (
                self.isolation_forest,
                self.pca_model,
                self.one_class_svm,
                self.autoencoder,
            )
        )

    def train(self, audit_events: List[Dict[str, Any]]) -> bool:
        """Train anomaly detection models on historical audit data."""
        logger.info("Training anomaly detection on %d events...", len(audit_events))

        matrix, _, _ = self._extract_behavioral_matrix(audit_events)
        if matrix.shape[0] < 4:
            logger.warning("Insufficient behavioral samples to train anomaly models (need at least 4 users)")
            return False

        scaled = self.scaler.fit_transform(matrix)

        self.isolation_forest = IsolationForest(
            contamination=0.18,
            random_state=42,
            n_jobs=1,
        )
        self.isolation_forest.fit(scaled)

        n_components = max(1, min(6, scaled.shape[0] - 1, scaled.shape[1] - 1))
        self.pca_model = PCA(n_components=n_components, random_state=42)
        self.pca_model.fit(scaled)

        self.one_class_svm = OneClassSVM(kernel="rbf", gamma="scale", nu=0.2)
        self.one_class_svm.fit(scaled)

        hidden_width = max(6, scaled.shape[1] // 2)
        self.autoencoder = MLPRegressor(
            hidden_layer_sizes=(hidden_width,),
            activation="relu",
            solver="adam",
            random_state=42,
            max_iter=1200,
            learning_rate_init=0.005,
        )
        self.autoencoder.fit(scaled, scaled)

        if_scores = -self.isolation_forest.score_samples(scaled)
        pca_errors = self._pca_errors(scaled)
        ocsvm_scores = -self.one_class_svm.decision_function(scaled)
        autoencoder_errors = self._autoencoder_errors(scaled)

        self.if_score_threshold = self._threshold_from_values(if_scores)
        self.pca_error_threshold = self._threshold_from_values(pca_errors)
        self.ocsvm_score_threshold = self._threshold_from_values(ocsvm_scores)
        self.autoencoder_error_threshold = self._threshold_from_values(autoencoder_errors)
        self.models_trained = True

        logger.info(
            "Models trained. Features=%d, PCA components=%d, thresholds(if=%.4f, pca=%.4f, ocsvm=%.4f, auto=%.4f)",
            scaled.shape[1],
            n_components,
            self.if_score_threshold,
            self.pca_error_threshold,
            self.ocsvm_score_threshold,
            self.autoencoder_error_threshold,
        )
        return True

    def _pca_errors(self, scaled: np.ndarray) -> np.ndarray:
        if self.pca_model is None:
            return np.zeros(scaled.shape[0], dtype=float)
        projected = self.pca_model.transform(scaled)
        reconstructed = self.pca_model.inverse_transform(projected)
        return np.linalg.norm(scaled - reconstructed, axis=1)

    def _autoencoder_errors(self, scaled: np.ndarray) -> np.ndarray:
        if self.autoencoder is None:
            return np.zeros(scaled.shape[0], dtype=float)
        reconstructed = self.autoencoder.predict(scaled)
        return np.mean(np.square(scaled - reconstructed), axis=1)

    def predict(self, audit_events: List[Dict[str, Any]]) -> Dict[str, Any]:
        """
        Detect anomalies in new or recent audit events and expose per-model
        baseline scores for evaluation and dashboard reporting.
        """
        if not self.models_trained or not self._models_ready():
            logger.warning("Models not trained. Returning empty predictions.")
            return {
                "anomalies": [],
                "scored_users": [],
                "summary": {
                    "total_users": 0,
                    "anomalous_users": 0,
                    "models_used": [],
                    "warning": "Models not trained",
                },
            }

        matrix, event_ids, summaries = self._extract_behavioral_matrix(audit_events)
        if matrix.shape[0] == 0:
            return {
                "anomalies": [],
                "scored_users": [],
                "summary": {
                    "total_users": 0,
                    "anomalous_users": 0,
                    "models_used": [
                        "isolation_forest",
                        "pca_reconstruction",
                        "one_class_svm",
                        "autoencoder",
                    ],
                },
            }

        scaled = self.scaler.transform(matrix)

        if_raw = -self.isolation_forest.score_samples(scaled)
        pca_errors = self._pca_errors(scaled)
        ocsvm_raw = -self.one_class_svm.decision_function(scaled)
        autoencoder_errors = self._autoencoder_errors(scaled)

        if_scores = self._normalize_positive(if_raw, self.if_score_threshold)
        pca_scores = self._normalize_positive(pca_errors, self.pca_error_threshold)
        ocsvm_scores = self._normalize_positive(ocsvm_raw, self.ocsvm_score_threshold)
        autoencoder_scores = self._normalize_positive(autoencoder_errors, self.autoencoder_error_threshold)

        scored_users: List[Dict[str, Any]] = []
        anomalies: List[Dict[str, Any]] = []

        for index, user_id in enumerate(event_ids):
            summary = summaries[index]
            if_flag = bool(if_raw[index] >= self.if_score_threshold)
            pca_flag = bool(pca_errors[index] >= self.pca_error_threshold)
            ocsvm_flag = bool(ocsvm_raw[index] >= self.ocsvm_score_threshold)
            auto_flag = bool(autoencoder_errors[index] >= self.autoencoder_error_threshold)

            ensemble_score = float(
                (0.35 * if_scores[index])
                + (0.20 * pca_scores[index])
                + (0.20 * ocsvm_scores[index])
                + (0.25 * autoencoder_scores[index])
            )
            risk_level = self._risk_level(ensemble_score)
            flagged = bool(if_flag or pca_flag or ocsvm_flag or auto_flag or ensemble_score >= 0.55)

            user_result = {
                "user_id": user_id,
                "anomaly_score": round(ensemble_score, 4),
                "risk_level": risk_level,
                "isolation_forest_score": round(float(if_scores[index]), 4),
                "pca_reconstruction_score": round(float(pca_scores[index]), 4),
                "one_class_svm_score": round(float(ocsvm_scores[index]), 4),
                "autoencoder_score": round(float(autoencoder_scores[index]), 4),
                "isolation_forest_raw": round(float(if_raw[index]), 4),
                "pca_error": round(float(pca_errors[index]), 4),
                "one_class_svm_raw": round(float(ocsvm_raw[index]), 4),
                "autoencoder_error": round(float(autoencoder_errors[index]), 4),
                "flags": {
                    "isolation_forest": if_flag,
                    "pca_reconstruction": pca_flag,
                    "one_class_svm": ocsvm_flag,
                    "autoencoder": auto_flag,
                },
                "behavioral_features": {
                    name: round(float(summary[name]), 4)
                    for name in self.feature_names
                },
            }
            scored_users.append(user_result)
            if flagged:
                anomalies.append(user_result)

        models_used = [
            "isolation_forest",
            "pca_reconstruction",
            "one_class_svm",
            "autoencoder",
        ]
        return {
            "anomalies": sorted(anomalies, key=lambda item: item["anomaly_score"], reverse=True),
            "scored_users": sorted(scored_users, key=lambda item: item["anomaly_score"], reverse=True),
            "summary": {
                "total_users": len(event_ids),
                "anomalous_users": len(anomalies),
                "anomaly_rate": len(anomalies) / len(event_ids) if event_ids else 0.0,
                "models_used": models_used,
                "thresholds": {
                    "isolation_forest": round(float(self.if_score_threshold), 4),
                    "pca_reconstruction": round(float(self.pca_error_threshold), 4),
                    "one_class_svm": round(float(self.ocsvm_score_threshold), 4),
                    "autoencoder": round(float(self.autoencoder_error_threshold), 4),
                },
            },
        }

    def save_models(self, prefix: str = "anomaly_detector") -> bool:
        """Persist trained models to disk."""
        if not self.models_trained or not self._models_ready():
            logger.warning("No trained models to save")
            return False

        try:
            pickle.dump(self.isolation_forest, open(self.model_dir / f"{prefix}_if.pkl", "wb"))
            pickle.dump(self.pca_model, open(self.model_dir / f"{prefix}_pca.pkl", "wb"))
            pickle.dump(self.one_class_svm, open(self.model_dir / f"{prefix}_ocsvm.pkl", "wb"))
            pickle.dump(self.autoencoder, open(self.model_dir / f"{prefix}_autoencoder.pkl", "wb"))
            pickle.dump(self.scaler, open(self.model_dir / f"{prefix}_scaler.pkl", "wb"))
            pickle.dump(
                {
                    "feature_names": self.feature_names,
                    "if_score_threshold": self.if_score_threshold,
                    "pca_error_threshold": self.pca_error_threshold,
                    "ocsvm_score_threshold": self.ocsvm_score_threshold,
                    "autoencoder_error_threshold": self.autoencoder_error_threshold,
                },
                open(self.model_dir / f"{prefix}_meta.pkl", "wb"),
            )
            logger.info("Models saved to %s", self.model_dir)
            return True
        except Exception as exc:
            logger.error("Failed to save models: %s", exc)
            return False

    def load_models(self, prefix: str = "anomaly_detector") -> bool:
        """Load pre-trained models from disk."""
        try:
            self.isolation_forest = pickle.load(open(self.model_dir / f"{prefix}_if.pkl", "rb"))
            self.pca_model = pickle.load(open(self.model_dir / f"{prefix}_pca.pkl", "rb"))
            self.one_class_svm = pickle.load(open(self.model_dir / f"{prefix}_ocsvm.pkl", "rb"))
            self.autoencoder = pickle.load(open(self.model_dir / f"{prefix}_autoencoder.pkl", "rb"))
            self.scaler = pickle.load(open(self.model_dir / f"{prefix}_scaler.pkl", "rb"))
            metadata = pickle.load(open(self.model_dir / f"{prefix}_meta.pkl", "rb"))
            self.feature_names = metadata.get("feature_names", user_behavior_feature_names())
            self.if_score_threshold = float(metadata.get("if_score_threshold", 0.0))
            self.pca_error_threshold = float(metadata.get("pca_error_threshold", 0.0))
            self.ocsvm_score_threshold = float(metadata.get("ocsvm_score_threshold", 0.0))
            self.autoencoder_error_threshold = float(metadata.get("autoencoder_error_threshold", 0.0))
            self.models_trained = True
            logger.info("Models loaded from %s", self.model_dir)
            return True
        except Exception as exc:
            logger.warning("Could not load models: %s", exc)
            return False


def main() -> None:
    """CLI entry point for model training and prediction."""
    if len(sys.argv) < 2:
        print("Usage: python anomaly_detector.py <train|predict> <json_data>")
        sys.exit(1)

    mode = sys.argv[1]
    data_json = sys.argv[2] if len(sys.argv) > 2 else "{}"

    try:
        data = load_input_payload(data_json)
    except (json.JSONDecodeError, FileNotFoundError) as exc:
        print(f"Error: Invalid JSON input ({exc})", file=sys.stderr)
        sys.exit(1)

    detector = AnomalyDetector()

    if mode == "train":
        events = data.get("events", [])
        if detector.train(events):
            detector.save_models()
            print(json.dumps({"status": "success", "message": "Models trained and saved"}))
        else:
            print(json.dumps({"status": "error", "message": "Training failed"}))
    elif mode == "predict":
        detector.load_models()
        events = data.get("events", [])
        result = detector.predict(events)
        print(json.dumps(result, indent=2))
    else:
        print(f"Unknown mode: {mode}", file=sys.stderr)
        sys.exit(1)


if __name__ == "__main__":
    main()
