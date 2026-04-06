#!/usr/bin/env python3
"""
Ownuh SAIPS - Adversarial Attack Detection
Identifies and classifies attack patterns based on login/IPS behavior.

Research alignment: Dr. Euijin Choo - Adversarial Attacks & Defenses
Uses supervised learning to detect brute force, credential stuffing, and sophisticated attacks.
"""

import json
import sys
import logging
from typing import Dict, List, Tuple, Any
import numpy as np
from pathlib import Path
from collections import defaultdict

from sklearn.ensemble import RandomForestClassifier
from sklearn.preprocessing import StandardScaler
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


class AdversarialAttackDetector:
    """
    Supervised attack detection system trained on labeled attack patterns.
    
    Attack types:
    1. BRUTE_FORCE: High-frequency failed attempts from single IP
    2. CREDENTIAL_STUFFING: Failed attempts across multiple accounts from single IP
    3. DISTRIBUTED: Attempts from multiple IPs, same account
    4. ACCOUNT_TAKEOVER: Successful login from anomalous location/device
    5. MFA_BYPASS: Repeated MFA failures or bypass attempts
    6. PRIVILEGE_ESCALATION: Rapid role elevation
    7. NORMAL: Benign activity
    """
    
    ATTACK_TYPES = [
        'BRUTE_FORCE',
        'CREDENTIAL_STUFFING',
        'DISTRIBUTED',
        'ACCOUNT_TAKEOVER',
        'MFA_BYPASS',
        'PRIVILEGE_ESCALATION',
        'NORMAL'
    ]
    
    def __init__(self, model_dir: str = None):
        self.model_dir = Path(model_dir) if model_dir else Path(__file__).parent / 'models'
        self.model_dir.mkdir(exist_ok=True)
        
        self.classifier = None
        self.parallel_jobs = 1
        self.scaler = StandardScaler()
        self.feature_names = []
        self.model_trained = False
        self.label_to_idx = {label: i for i, label in enumerate(self.ATTACK_TYPES)}
        self.idx_to_label = {i: label for i, label in enumerate(self.ATTACK_TYPES)}

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

    def _is_role_change(self, event: Dict[str, Any]) -> bool:
        code = self._event_code(event)
        name = self._event_name(event)
        details = str(event.get('details', '')).lower()
        return code.startswith('RBAC-') or 'role changed' in name or 'admin' in details
    
    def label_training_data(self, audit_events: List[Dict[str, Any]]) -> Tuple[List[str], List[Dict]]:
        """
        Label audit events as attack types based on heuristics.
        
        Heuristics (production system would use manual labels):
        """
        labels = []
        labeled_events = []
        
        # Group events by user
        by_user = defaultdict(list)
        for event in audit_events:
            uid = event.get('user_id', 'unknown')
            by_user[uid].append(event)
        
        # Group by IP
        by_ip = defaultdict(list)
        for event in audit_events:
            ip = event.get('source_ip', 'unknown')
            by_ip[ip].append(event)
        
        for event in audit_events:
            uid = event.get('user_id', 'unknown')
            ip = event.get('source_ip', 'unknown')
            
            user_events = by_user[uid]
            ip_events = by_ip[ip]
            
            # Count metrics
            failed_logins_by_user = sum(1 for e in user_events if self._is_login_failure(e))
            failed_logins_by_ip = sum(1 for e in ip_events if self._is_login_failure(e))
            unique_users_from_ip = len(set(e.get('user_id') for e in ip_events))
            unique_ips_for_user = len(set(e.get('source_ip') for e in user_events if e.get('source_ip')))
            successful_logins_user = sum(1 for e in user_events if self._is_login_success(e))
            bypass_events = sum(1 for e in user_events if self._is_mfa_bypass(e))
            geo_diversity = len(set(e.get('country_code') for e in user_events if e.get('country_code')))
            avg_risk = float(np.mean([e.get('risk_score', 0) for e in user_events])) if user_events else 0.0
            
            # Labeling logic
            label = 'NORMAL'
            
            if failed_logins_by_ip >= 3 and unique_users_from_ip >= 3:
                label = 'CREDENTIAL_STUFFING'
            elif failed_logins_by_user >= 3 and unique_ips_for_user >= 3:
                label = 'DISTRIBUTED'
            elif bypass_events >= 1:
                label = 'MFA_BYPASS'
            elif successful_logins_user >= 2 and geo_diversity >= 2 and avg_risk >= 45:
                label = 'ACCOUNT_TAKEOVER'
            elif failed_logins_by_user >= 4 and unique_ips_for_user <= 1:
                label = 'BRUTE_FORCE'
            elif self._is_role_change(event) and failed_logins_by_user > 1:
                label = 'PRIVILEGE_ESCALATION'
            
            labels.append(label)
            labeled_events.append(event)
        
        return labels, labeled_events
    
    def extract_features(self, audit_events: List[Dict[str, Any]]) -> Tuple[np.ndarray, List[str]]:
        """Extract features for attack classification."""
        
        features_list = []
        event_ids = []
        
        by_user = defaultdict(list)
        by_ip = defaultdict(list)
        
        for event in audit_events:
            uid = event.get('user_id', 'unknown')
            ip = event.get('source_ip', 'unknown')
            by_user[uid].append(event)
            by_ip[ip].append(event)
        
        for event in audit_events:
            uid = event.get('user_id', 'unknown')
            ip = event.get('source_ip', 'unknown')
            
            user_events = by_user[uid]
            ip_events = by_ip[ip]
            
            # Features
            failed_logins_user = sum(1 for e in user_events if self._is_login_failure(e))
            failed_logins_ip = sum(1 for e in ip_events if self._is_login_failure(e))
            unique_users_ip = len(set(e.get('user_id') for e in ip_events))
            unique_ips_user = len(set(e.get('source_ip') for e in user_events))
            unique_countries_user = len(set(e.get('country_code') for e in user_events if e.get('country_code')))
            mfa_attempts = sum(1 for e in user_events if 'mfa' in self._event_name(e) or str(e.get('mfa_method', 'none')).lower() != 'none')
            mfa_failures = sum(1 for e in user_events if self._is_mfa_bypass(e))
            successful_logins_user = sum(1 for e in user_events if self._is_login_success(e))
            risk_score = event.get('risk_score', 0)
            
            feature_vector = [
                failed_logins_user,
                failed_logins_ip,
                unique_users_ip,
                unique_ips_user,
                unique_countries_user,
                mfa_attempts,
                mfa_failures,
                successful_logins_user,
                risk_score,
            ]
            
            features_list.append(feature_vector)
            event_ids.append(uid)
        
        self.feature_names = [
            'failed_logins_user',
            'failed_logins_ip',
            'unique_users_ip',
            'unique_ips_user',
            'unique_countries_user',
            'mfa_attempts',
            'mfa_failures',
            'successful_logins_user',
            'risk_score'
        ]
        
        return np.array(features_list) if features_list else np.array([]).reshape(0, 9), event_ids
    
    def train(self, audit_events: List[Dict[str, Any]]) -> bool:
        """Train attack classifier."""
        logger.info(f"Training attack detector on {len(audit_events)} events...")
        
        labels, labeled_events = self.label_training_data(audit_events)
        X, _ = self.extract_features(labeled_events)
        y = np.array([self.label_to_idx[label] for label in labels])
        
        if X.shape[0] < 5:
            logger.warning("Insufficient training data")
            return False
        
        X_scaled = self.scaler.fit_transform(X)
        
        self.classifier = RandomForestClassifier(
            n_estimators=100,
            max_depth=10,
            random_state=42,
            n_jobs=self.parallel_jobs
        )
        self.classifier.fit(X_scaled, y)
        
        self.model_trained = True
        logger.info(f"✓ Attack classifier trained on {len(audit_events)} events")
        
        return True
    
    def predict(self, audit_events: List[Dict[str, Any]]) -> Dict[str, Any]:
        """Classify events as attack types."""
        
        if not self.model_trained:
            logger.warning("Model not trained")
            return {
                'attacks': [],
                'summary': {
                    'total_events': 0,
                    'attack_counts': {},
                    'warning': 'Model not trained'
                }
            }
        
        X, event_ids = self.extract_features(audit_events)
        
        if X.shape[0] == 0:
            return {
                'attacks': [],
                'summary': {
                    'total_events': 0,
                    'attack_counts': {}
                }
            }
        
        X_scaled = self.scaler.transform(X)
        if hasattr(self.classifier, 'n_jobs'):
            self.classifier.n_jobs = self.parallel_jobs
        predictions = self.classifier.predict(X_scaled)
        probabilities = self.classifier.predict_proba(X_scaled)
        
        attacks = []
        attack_counts = defaultdict(int)
        
        for i, (user_id, pred_idx) in enumerate(zip(event_ids, predictions)):
            attack_type = self.idx_to_label[pred_idx]
            confidence = float(probabilities[i].max())
            
            if attack_type != 'NORMAL':
                attacks.append({
                    'user_id': user_id,
                    'attack_type': attack_type,
                    'confidence': confidence,
                    'probabilities': {
                        self.idx_to_label[j]: float(prob) 
                        for j, prob in enumerate(probabilities[i])
                    }
                })
                attack_counts[attack_type] += 1
        
        return {
            'attacks': sorted(attacks, key=lambda x: x['confidence'], reverse=True),
            'summary': {
                'total_events': len(audit_events),
                'attacks_detected': len(attacks),
                'attack_counts': dict(attack_counts),
            }
        }
    
    def save_model(self, prefix: str = 'attack_detector'):
        """Save trained models."""
        if not self.model_trained:
            return False
        
        try:
            pickle.dump(self.classifier, open(self.model_dir / f'{prefix}_clf.pkl', 'wb'))
            pickle.dump(self.scaler, open(self.model_dir / f'{prefix}_scaler.pkl', 'wb'))
            logger.info(f"✓ Attack detector saved")
            return True
        except Exception as e:
            logger.error(f"Save failed: {e}")
            return False
    
    def load_model(self, prefix: str = 'attack_detector'):
        """Load trained models."""
        try:
            self.classifier = pickle.load(open(self.model_dir / f'{prefix}_clf.pkl', 'rb'))
            self.scaler = pickle.load(open(self.model_dir / f'{prefix}_scaler.pkl', 'rb'))
            if hasattr(self.classifier, 'n_jobs'):
                self.classifier.n_jobs = self.parallel_jobs
            self.model_trained = True
            logger.info(f"✓ Attack detector loaded")
            return True
        except Exception as e:
            logger.warning(f"Load failed: {e}")
            return False


def main():
    if len(sys.argv) < 2:
        print("Usage: python attack_detector.py <train|predict> <json_data>")
        sys.exit(1)
    
    mode = sys.argv[1]
    data_json = sys.argv[2] if len(sys.argv) > 2 else '{}'
    
    try:
        data = load_input_payload(data_json)
    except (json.JSONDecodeError, FileNotFoundError) as exc:
        print(f"Error: Invalid JSON ({exc})", file=sys.stderr)
        sys.exit(1)
    
    detector = AdversarialAttackDetector()
    
    if mode == 'train':
        events = data.get('events', [])
        if detector.train(events):
            detector.save_model()
            print(json.dumps({'status': 'success', 'message': 'Attack detector trained'}))
        else:
            print(json.dumps({'status': 'error', 'message': 'Training failed'}))
    
    elif mode == 'predict':
        detector.load_model()
        events = data.get('events', [])
        result = detector.predict(events)
        print(json.dumps(result, indent=2, default=str))
    
    else:
        print(f"Unknown mode: {mode}", file=sys.stderr)
        sys.exit(1)


if __name__ == '__main__':
    main()
