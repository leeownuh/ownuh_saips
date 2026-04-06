# Benchmark Dataset

The bundled benchmark is a small synthetic dataset designed to exercise the SAIPS attack-attribution workflow in a reproducible way.

## Structure

`benchmark_dataset.py` generates:

- `train_events`: benign and suspicious historical events used to train the local anomaly and attack models.
- `test_cases`: case windows used for evaluation and ablation.

Each test case includes:

- `case_id`
- `label`: `1` for malicious, `0` for benign
- `attack_type`
- `expected_entities`
- `description`
- `events`

## Included case patterns

- Benign known-device behaviour
- Benign new-device but verified behaviour
- Account takeover / impossible travel
- Credential stuffing
- Distributed pressure on one account
- MFA bypass abuse

## Why synthetic

This repository is a portfolio and research prototype, so the benchmark must be:

- Shareable
- Repeatable
- Small enough to run locally
- Free of private or sensitive operational data

## What it is not

- It is not a replacement for enterprise-scale telemetry.
- It is not statistically representative of a production SOC.
- It is not a claim of external validation.
