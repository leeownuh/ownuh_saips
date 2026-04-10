# Case Study: account_takeover_travel

- True label: `ACCOUNT_TAKEOVER`
- Predicted label: `ACCOUNT_TAKEOVER`
- Fused score: `0.7697`
- Temporal fused score: `0.6917`
- Feedback label: `true_positive`

## Description
Impossible travel followed by a successful login from a cloned device

## Local Explanation
Case account_takeover_travel shows ACCOUNT_TAKEOVER traits with score 0.77. Primary evidence came from graph neighbourhood links and temporal/behavioral drivers (max_risk_score, avg_risk_score, risk_trend_slope).

## LLM Explanation
Case account_takeover_travel is treated as ACCOUNT_TAKEOVER because fused graph and anomaly score reached 0.77. The strongest attribution evidence came from the observed graph neighbourhood.

## Top Behavioral Drivers
- max_risk_score: 81.0
- avg_risk_score: 57.6667
- risk_trend_slope: 32.5
- min_login_interval_minutes: 7.0
- event_count: 3.0

## Linked Entities
none

## Notes
Generated automatically from latest evaluation report.
