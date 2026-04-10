# ML Research Positioning

## Working title

`Explainable Graph-Based Attack Attribution for Enterprise Authentication and Audit Logs`

## One-sentence summary

This project studies whether combining graph-based entity correlation, behavioral anomaly detection, and attack classification can surface compromised users or malicious infrastructure in enterprise authentication logs more effectively than a graph-only attribution view.

## Research problem

Traditional alerting often misses suspicious activity when each individual signal looks benign in isolation. A compromised insider or account takeover can present with:

- valid credentials
- successful logins
- low-friction geographic context
- no single obviously malicious IP

The core problem is therefore not just anomaly detection. It is attribution under weak, distributed evidence.

## Research question

Can a fused attack-attribution pipeline that combines graph correlation, anomaly scoring, and attack classification detect suspicious entities in enterprise authentication and audit logs better than graph-only linking, while remaining explainable to human analysts?

## Hypothesis

Compared with graph-only attribution, a fused pipeline should:

- improve case-level detection quality
- reduce missed suspicious entities that look normal individually
- remain interpretable through linked evidence, analyst notes, and relationship views

## Current method

The current system has five main layers.

### 1. Graph and entity correlation

The pipeline builds relationships across:

- users
- IP addresses
- devices
- countries
- related incidents

It then assigns entity-level suspicion using connected activity and shared infrastructure patterns.

### 2. Behavioral anomaly detection

The anomaly layer currently uses:

- Isolation Forest
- PCA reconstruction scoring
- One-Class SVM
- Autoencoder-style reconstruction baseline
- Ensemble behavioral score over these baselines

This layer focuses on unusual behavior patterns extracted from authentication and audit events, including failed-login velocity, burstiness, device novelty, country switching, MFA failure rate, and session reuse signals.

### 3. Attack classification

The attack-classification layer estimates attack labels such as:

- credential stuffing
- brute force
- distributed attack activity
- account takeover
- MFA bypass

### 4. Temporal graph attribution baseline

In addition to static correlation, the benchmark includes a lightweight temporal heterogeneous graph scorer:

- recency-decayed edge weighting
- mixed entity types (user, device, IP, country, session)
- temporal volatility signal

### 5. Analyst-facing explanation

The product surface includes:

- deterministic local explanations
- optional LLM-generated analyst narratives
- graph-style relationship visualization across linked entities
- incident creation directly from surfaced attribution cases

Important:
The LLM layer is optional explainability, not the core detector.

## Current baselines and ablations

The current offline benchmark compares:

- `graph_only`
- `dynamic_graph_temporal`
- `graph_plus_anomaly`
- `temporal_graph_plus_anomaly`
- `graph_plus_anomaly_feedback`
- `graph_plus_anomaly_llm`

Interpretation:

- `graph_only` tests static linking only
- `dynamic_graph_temporal` tests temporal graph-only scoring
- `graph_plus_anomaly` tests static graph plus anomaly fusion
- `temporal_graph_plus_anomaly` tests temporal graph plus anomaly fusion
- `graph_plus_anomaly_feedback` applies analyst-confirmation feedback weights
- `graph_plus_anomaly_llm` measures explanation coverage on top of fused detection

Important:
The current `graph_plus_anomaly_llm` mode does not change the detection threshold. It evaluates explanation coverage separately.

## Evaluation design

The current evaluation framework measures:

- precision
- recall
- F1
- ROC-AUC
- false positives
- case studies
- adversarial robustness
- time-window generalization (train on earlier windows, test on later windows)
- cross-environment generalization (global enterprise, remote workforce, high MFA organization)

The adversarial suite currently tests:

- IP rotation
- timing jitter
- device spoofing
- MFA-failure noise

## Threat model

The system is most relevant to:

- compromised user accounts
- account takeover attempts
- credential stuffing and distributed password attacks
- suspicious infrastructure reuse across entities
- ambiguous enterprise log patterns that require analyst triage

It is not designed as a malware classifier or endpoint sensor replacement.

## Main contribution right now

The strongest contribution is not a novel standalone model. It is an end-to-end, explainable attribution pipeline that joins:

- data fusion
- graph-based correlation
- anomaly detection
- attack labeling
- analyst workflow integration

That makes it stronger as a cybersecurity systems project than as a pure-model novelty paper.

## What is strong about the project

- It addresses a real cybersecurity problem.
- It is end to end, not just notebook ML.
- It has an analyst-facing interface and incident handoff.
- It includes ablation and adversarial testing.
- It is documented honestly enough to discuss with a supervisor.

## What is weak right now

- The bundled benchmark is synthetic and small.
- Perfect-looking synthetic metrics are not strong evidence on their own.
- The anomaly models are still relatively lightweight.
- The current novelty is more about integration and explainability than a new detection algorithm.
- There is no analyst-labeled enterprise validation yet.

## What you can safely claim

- You built an explainable attack-attribution pipeline for enterprise authentication and audit logs.
- The system combines graph-based correlation, anomaly detection, and attack classification.
- You evaluated it with ablation and adversarial testing on a reproducible benchmark.
- You integrated the detection pipeline into a usable analyst dashboard with incident handoff.
- You are interested in extending it toward stronger research-grade evaluation.

## What you should not claim

- Do not claim production validation.
- Do not claim publication-grade evidence from the current benchmark alone.
- Do not claim the LLM layer is the core detector.
- Do not claim novel state-of-the-art performance.
- Do not claim real-world generalization without larger labeled data.

## Best professor-facing framing

Use this framing:

`I built an explainable attack-attribution prototype for enterprise authentication and audit logs that combines graph-based entity correlation, behavioral anomaly detection, and attack classification. I evaluated the pipeline using ablation and adversarial robustness tests, and integrated it into an analyst-facing dashboard for case review and incident handoff. I see this as a strong systems-and-evaluation starting point, and I want to extend it with stronger baselines and real enterprise-style validation in graduate research.`

## Best next steps if you want this to feel paper-worthy

### Data

- evaluate on a larger, noisier dataset
- replace synthetic labels with analyst-reviewed labels where possible
- test over longer temporal windows

### Baselines

- add stronger temporal graph baselines beyond the current lightweight decayed graph
- evaluate model calibration quality, not only thresholded detection metrics
- test ranking quality (top-k precision / recall) for analyst triage queues

### Analysis

- include error analysis, not just aggregate scores
- show failure cases and false positives
- report which attack types are hardest

### Human factors

- measure whether the explanation layer improves analyst usability
- compare local explanations versus LLM-assisted explanations for triage clarity

## Bottom line

Right now, this is best presented as:

`a strong, research-oriented cybersecurity systems project with honest evaluation and clear next-step research potential`

That is already a good position for reaching out to a supervisor.
