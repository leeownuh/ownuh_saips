# Case Study: credential_stuffing

- True label: `CREDENTIAL_STUFFING`
- Predicted label: `CREDENTIAL_STUFFING`
- Fused score: `0.756`
- Temporal fused score: `0.6988`
- Feedback label: `unlabeled`

## Description
Spray behaviour against multiple accounts from one source IP

## Local Explanation
Case credential_stuffing shows CREDENTIAL_STUFFING traits with score 0.76. Primary evidence came from graph neighbourhood links and temporal/behavioral drivers (avg_risk_score, max_risk_score, failed_login_velocity_per_hour).

## LLM Explanation
Case credential_stuffing is treated as CREDENTIAL_STUFFING because fused graph and anomaly score reached 0.76. The strongest attribution evidence came from the observed graph neighbourhood.

## Top Behavioral Drivers
- avg_risk_score: 88.0
- max_risk_score: 88.0
- failed_login_velocity_per_hour: 4.0
- event_count: 1.0
- failed_login_attempts: 1.0

## Linked Entities
none

## Notes
Generated automatically from latest evaluation report.
