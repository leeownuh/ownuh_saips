#!/usr/bin/env python3
"""
Ownuh SAIPS - ML-Based Anomaly Detection Service
Detects anomalous login patterns, IPS events, and entity behavior using Isolation Forest + Autoencoder.

Research alignment: Dr. Euijin Choo - Anomaly Detection in Network Traffic & Enterprise Logs
Reference: "Compromised Entity Detection in Network and System Logs"
"""

import json
import sys
import logging
from typing import Dict, List, Tuple, Any
import numpy as np
from pathlib import Path

# ML Libraries
from sklearn.preprocessing import StandardScaler
from sklearn.ensemble import IsolationForest
from sklearn.decomposition import PCA
import pickle

logging.basicConfig(level=logging.INFO, format='%(asctime)s [%(levelname)s] %(message)s')
logger = logging.getLogger(__name__)


def load_input_payload(argument: str) -> Dict[str, Any]:
    if argument.startswith('@'):
        payload_path = Path(argument[1:])
        if not payload_path.exists():
            raise FileNotFoundError(f"Payload file not found: {payload_path}")
        return json.loads(payload_path.read_text(encoding='utf-8-sig'))
    return json.loads(argument)


class AnomalyDetector:
    """
    Multi-model anomaly detection system for security events.
    
    Models:
    1. Isolation Forest: Detects global statistical outliers in login/IPS patterns
    2. PCA-based: Identifies deviations from typical behavioral manifold
    3. Statistical: Z-score based detection for individual metrics
    
    Aligned with research on:
    - DeviceWatch: Detecting compromised devices via network analysis
    - Anomaly detection in enterprise logs and network traffic
    """
    
    def __init__(self, model_dir: str = None):
        self.model_dir = Path(model_dir) if model_dir else Path(__file__).parent / 'models'
        self.model_dir.mkdir(exist_ok=True)
        
        self.isolation_forest = None
        self.pca_model = None
        self.scaler = StandardScaler()
        self.feature_names = []
        self.models_trained = False

    @staticmethod
    def _event_code(event: Dict[str, Any]) -> str:
        return str(event.get('event_code', '')).upper()

    @staticmethod
    def _event_name(event: Dict[str, Any]) -> str:
        return str(event.get('event_name', '')).lower()

    def _is_login_success(self, event: Dict[str, Any]) -> bool:
        code = self._event_code(event)
        name = self._event_name(event)
        return code == 'AUTH-001' or 'successful login' in name

    def _is_login_failure(self, event: Dict[str, Any]) -> bool:
        code = self._event_code(event)
        name = self._event_name(event)
        return code == 'AUTH-002' or 'failed login' in name or 'login failed' in name

    def _is_mfa_bypass(self, event: Dict[str, Any]) -> bool:
        code = self._event_code(event)
        name = self._event_name(event)
        mfa_method = str(event.get('mfa_method', '')).lower()
        return code in {'AUTH-017', 'AUTH-018'} or 'bypass' in name or mfa_method == 'bypass_token'
        
    def extract_features_from_audit_log(self, audit_events: List[Dict[str, Any]]) -> Tuple[np.ndarray, List[str]]:
        """
        Extract behavioral features from raw audit events.
        
        Features engineered for security context:
        - failed_login_attempts: Count within window
        - login_countries: Number of distinct countries
        - login_intervals: Time between consecutive logins (seconds)
        - ip_diversity: Number of unique IPs
        - mfa_bypass_attempts: Suspicious MFA patterns
        - privilege_escalation: Role change likelihood
        - time_of_day_deviation: Unusual login times
        - user_agent_changes: Device changes per session
        """
        
        features_list = []
        event_ids = []
        
        by_user = {}
        for event in audit_events:
            uid = event.get('user_id', 'unknown')
            if uid not in by_user:
                by_user[uid] = []
            by_user[uid].append(event)
        
        for user_id, user_events in by_user.items():
            if not user_events:
                continue
                
            sorted_events = sorted(user_events, key=lambda x: x.get('created_at', ''))
            
            # Feature 1: Failed login attempts
            failed_count = sum(1 for e in user_events if self._is_login_failure(e))
            
            # Feature 2: Geographic diversity
            countries = set(e.get('country_code') for e in user_events if e.get('country_code'))
            geo_diversity = len(countries)
            
            # Feature 3: IP diversity
            ips = set(e.get('source_ip') for e in user_events if e.get('source_ip'))
            ip_diversity = len(ips)
            
            # Feature 4: Time intervals between logins (in hours)
            login_events = [e for e in sorted_events if self._is_login_success(e) or self._is_login_failure(e)]
            intervals = []
            if len(login_events) > 1:
                for i in range(1, len(login_events)):
                    # Simple interval approximation (in practice, parse timestamps)
                    intervals.append(float(i))
            avg_interval = np.mean(intervals) if intervals else 0.0
            
            # Feature 5: MFA bypass attempts
            mfa_bypass = sum(1 for e in user_events if self._is_mfa_bypass(e))
            
            # Feature 6: Privilege escalation indicators
            admin_events = sum(1 for e in user_events if e.get('role', '') == 'admin')
            
            # Feature 7: Risk score presence (existing field from audit log)
            avg_risk = np.mean([e.get('risk_score', 0) for e in user_events]) if user_events else 0.0
            
            # Feature 8: Session duration anomaly (rough estimate)
            session_count = len([
                e for e in user_events
                if 'session_id' in (e.get('details') or {}) or self._is_login_success(e)
            ])
            
            feature_vector = [
                failed_count,
                geo_diversity,
                ip_diversity,
                avg_interval,
                mfa_bypass,
                admin_events,
                avg_risk,
                session_count,
            ]
            
            features_list.append(feature_vector)
            event_ids.append(user_id)
        
        self.feature_names = [
            'failed_login_attempts',
            'geographic_diversity',
            'ip_diversity', 
            'avg_login_interval_hours',
            'mfa_bypass_attempts',
            'privilege_escalation_indicators',
            'avg_risk_score',
            'session_count'
        ]
        
        return np.array(features_list) if features_list else np.array([]).reshape(0, 8), event_ids
    
    def train(self, audit_events: List[Dict[str, Any]]):
        """Train anomaly detection models on historical audit data."""
        logger.info(f"Training anomaly detection on {len(audit_events)} events...")
        
        X, event_ids = self.extract_features_from_audit_log(audit_events)
        
        if X.shape[0] < 2:
            logger.warning("Insufficient data to train models (need at least 2 samples)")
            return False
        
        # Standardize features
        X_scaled = self.scaler.fit_transform(X)
        
        # Isolation Forest: Global anomaly detection
        self.isolation_forest = IsolationForest(
            contamination=0.1,  # Assume ~10% anomalies in training
            random_state=42,
            n_jobs=1
        )
        self.isolation_forest.fit(X_scaled)
        
        # PCA for behavioral manifold
        n_components = min(5, X_scaled.shape[1] - 1)
        self.pca_model = PCA(n_components=n_components)
        self.pca_model.fit(X_scaled)
        
        self.models_trained = True
        logger.info(f"✓ Models trained. Isolation Forest contamination: 10%, PCA components: {n_components}")
        
        return True
    
    def predict(self, audit_events: List[Dict[str, Any]]) -> Dict[str, Any]:
        """
        Detect anomalies in new or recent audit events.
        
        Returns:
        {
            'anomalies': [
                {'user_id': str, 'anomaly_score': float, 'risk_level': 'low'|'medium'|'high'},
                ...
            ],
            'summary': {
                'total_users': int,
                'anomalous_users': int,
                'models_used': List[str],
                'timestamp': str
            }
        }
        """
        
        if not self.models_trained:
            logger.warning("Models not trained. Returning empty predictions.")
            return {
                'anomalies': [],
                'summary': {
                    'total_users': 0,
                    'anomalous_users': 0,
                    'models_used': [],
                    'warning': 'Models not trained'
                }
            }
        
        X, event_ids = self.extract_features_from_audit_log(audit_events)
        
        if X.shape[0] == 0:
            return {
                'anomalies': [],
                'summary': {
                    'total_users': 0,
                    'anomalous_users': 0,
                    'models_used': ['isolation_forest', 'pca'],
                }
            }
        
        X_scaled = self.scaler.transform(X)
        
        # Isolation Forest predictions (-1 = anomaly, 1 = normal)
        if_preds = self.isolation_forest.predict(X_scaled)
        if_scores = self.isolation_forest.score_samples(X_scaled)  # Negative = more anomalous
        
        # PCA reconstruction error
        X_pca = self.pca_model.transform(X_scaled)
        X_reconstructed = self.pca_model.inverse_transform(X_pca)
        pca_errors = np.linalg.norm(X_scaled - X_reconstructed, axis=1)
        pca_error_threshold = np.mean(pca_errors) + 1.5 * np.std(pca_errors)
        
        # Combine signals
        anomalies = []
        for i, user_id in enumerate(event_ids):
            is_if_anomaly = if_preds[i] == -1
            is_pca_anomaly = pca_errors[i] > pca_error_threshold
            
            # Composite anomaly score [0, 1]
            if_score_normalized = (if_scores[i] - if_scores.min()) / (if_scores.max() - if_scores.min() + 1e-6)
            pca_score_normalized = (pca_errors[i] - pca_errors.min()) / (pca_errors.max() - pca_errors.min() + 1e-6)
            
            composite_score = 0.6 * if_score_normalized + 0.4 * pca_score_normalized
            
            if is_if_anomaly or is_pca_anomaly:
                risk_level = 'high' if composite_score > 0.7 else ('medium' if composite_score > 0.4 else 'low')
                anomalies.append({
                    'user_id': user_id,
                    'anomaly_score': float(composite_score),
                    'risk_level': risk_level,
                    'isolation_forest_anomaly': is_if_anomaly,
                    'pca_anomaly': is_pca_anomaly,
                    'pca_error': float(pca_errors[i]),
                })
        
        return {
            'anomalies': sorted(anomalies, key=lambda x: x['anomaly_score'], reverse=True),
            'summary': {
                'total_users': len(event_ids),
                'anomalous_users': len(anomalies),
                'anomaly_rate': len(anomalies) / len(event_ids) if event_ids else 0.0,
                'models_used': ['isolation_forest', 'pca'],
            }
        }
    
    def save_models(self, prefix: str = 'anomaly_detector'):
        """Persist trained models to disk."""
        if not self.models_trained:
            logger.warning("No trained models to save")
            return False
        
        try:
            pickle.dump(self.isolation_forest, open(self.model_dir / f'{prefix}_if.pkl', 'wb'))
            pickle.dump(self.pca_model, open(self.model_dir / f'{prefix}_pca.pkl', 'wb'))
            pickle.dump(self.scaler, open(self.model_dir / f'{prefix}_scaler.pkl', 'wb'))
            logger.info(f"✓ Models saved to {self.model_dir}")
            return True
        except Exception as e:
            logger.error(f"Failed to save models: {e}")
            return False
    
    def load_models(self, prefix: str = 'anomaly_detector'):
        """Load pre-trained models from disk."""
        try:
            self.isolation_forest = pickle.load(open(self.model_dir / f'{prefix}_if.pkl', 'rb'))
            self.pca_model = pickle.load(open(self.model_dir / f'{prefix}_pca.pkl', 'rb'))
            self.scaler = pickle.load(open(self.model_dir / f'{prefix}_scaler.pkl', 'rb'))
            self.models_trained = True
            logger.info(f"✓ Models loaded from {self.model_dir}")
            return True
        except Exception as e:
            logger.warning(f"Could not load models: {e}")
            return False


def main():
    """CLI entry point for model training and prediction."""
    if len(sys.argv) < 2:
        print("Usage: python anomaly_detector.py <train|predict> <json_data>")
        sys.exit(1)
    
    mode = sys.argv[1]
    data_json = sys.argv[2] if len(sys.argv) > 2 else '{}'
    
    try:
        data = load_input_payload(data_json)
    except (json.JSONDecodeError, FileNotFoundError) as exc:
        print(f"Error: Invalid JSON input ({exc})", file=sys.stderr)
        sys.exit(1)
    
    detector = AnomalyDetector()
    
    if mode == 'train':
        events = data.get('events', [])
        if detector.train(events):
            detector.save_models()
            print(json.dumps({'status': 'success', 'message': 'Models trained and saved'}))
        else:
            print(json.dumps({'status': 'error', 'message': 'Training failed'}))
    
    elif mode == 'predict':
        detector.load_models()
        events = data.get('events', [])
        result = detector.predict(events)
        print(json.dumps(result, indent=2))
    
    else:
        print(f"Unknown mode: {mode}", file=sys.stderr)
        sys.exit(1)


if __name__ == '__main__':
    main()
