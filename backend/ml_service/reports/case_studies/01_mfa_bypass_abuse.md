# Case Study: mfa_bypass_abuse

- True label: `MFA_BYPASS`
- Predicted label: `MFA_BYPASS`
- Fused score: `0.9082`
- Temporal fused score: `0.8775`
- Feedback label: `unlabeled`

## Description
Recovery bypass used immediately from a new device and region

## Local Explanation
Case mfa_bypass_abuse shows MFA_BYPASS traits with score 0.91. Primary evidence came from graph neighbourhood links and temporal/behavioral drivers (max_risk_score, avg_risk_score, event_count).

## LLM Explanation
Case mfa_bypass_abuse is treated as MFA_BYPASS because fused graph and anomaly score reached 0.91. The strongest attribution evidence came from the observed graph neighbourhood.

## Top Behavioral Drivers
- max_risk_score: 74.0
- avg_risk_score: 66.6667
- event_count: 3.0
- mfa_event_count: 3.0
- mfa_bypass_attempts: 3.0

## Linked Entities
none

## Notes
Generated automatically from latest evaluation report.
