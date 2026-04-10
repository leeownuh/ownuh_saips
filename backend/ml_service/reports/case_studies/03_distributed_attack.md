# Case Study: distributed_attack

- True label: `DISTRIBUTED`
- Predicted label: `DISTRIBUTED`
- Fused score: `0.7614`
- Temporal fused score: `0.7454`
- Feedback label: `unlabeled`

## Description
One target account pressured from multiple IPs in a short window

## Local Explanation
Case distributed_attack shows DISTRIBUTED traits with score 0.76. Primary evidence came from graph neighbourhood links and temporal/behavioral drivers (max_risk_score, avg_risk_score, failed_login_velocity_per_hour).

## LLM Explanation
Case distributed_attack is treated as DISTRIBUTED because fused graph and anomaly score reached 0.76. The strongest attribution evidence came from the observed graph neighbourhood.

## Top Behavioral Drivers
- max_risk_score: 90.0
- avg_risk_score: 87.0
- failed_login_velocity_per_hour: 12.0
- event_count: 3.0
- failed_login_attempts: 3.0

## Linked Entities
none

## Notes
Generated automatically from latest evaluation report.
