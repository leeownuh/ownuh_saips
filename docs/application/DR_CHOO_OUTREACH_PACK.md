# Dr. Choo Outreach Pack (Level 2)

Date: April 10, 2026  
Target: MSc application outreach (AI Security direction)

## 1. CV Project Bullets (paste-ready)

Use 3-5 bullets:

- Built an explainable attack-attribution pipeline for enterprise authentication and audit logs, combining graph-based entity linking, anomaly modeling, and attack classification.
- Designed a Level 2 temporal-behavior feature engine (29 features), including short-window failed-login velocity, burst peak activity, recent failure ratio, failure streaks, and risk trend slope.
- Implemented analyst-in-the-loop feedback labels (`true_positive`, `false_positive`, `needs_review`) and integrated feedback-aware evaluation for iterative triage quality improvement.
- Evaluated the pipeline using ablation and adversarial robustness tests (IP rotation, timing jitter, device spoofing, MFA-noise), with precision/recall/F1/ROC-AUC reporting and case-study analysis.
- Built analyst-facing dashboards for attribution review, incident handoff, and model-evaluation visibility, including explanation quality and feedback summary panels.

## 2. LinkedIn Post (research-focused)

Then I thought: How do you detect a compromised insider when their credentials are valid, their login times are normal, and their IP is clean?

That question led me to build a Level 2 explainable attack-attribution pipeline for enterprise authentication and audit logs.

The system combines graph-based linking, behavioral anomaly modeling, attack classification, and analyst feedback labels to surface suspicious behavior that looks normal in isolation.

What I added in Level 2:
- temporal behavior features
- feedback-aware attribution scoring
- explanation-quality evaluation
- adversarial robustness testing

Big lesson: useful cybersecurity AI is not about adding a chatbot. It is about helping analysts reason through weak signals, linked entities, and uncertainty at decision time.

#CyberSecurity #AI #MachineLearning #AnomalyDetection #GraphAnalytics #EnterpriseSecurity #Research

## 3. Email Draft to Dr. Choo

Subject: Prospective MSc Student - AI-Based Attack Attribution in Enterprise Logs

Dear Dr. Choo,

I hope you are doing well. I am preparing my MSc application and would be grateful for the opportunity to work in your research direction on AI-based cybersecurity.

I recently built a Level 2 explainable attack-attribution pipeline for enterprise authentication and audit logs. The system combines graph-based entity correlation, behavioral anomaly modeling, attack classification, and analyst feedback labels for iterative improvement. I also integrated it into an analyst-facing dashboard with incident handoff and a reproducible evaluation workflow.

My evaluation currently includes ablation, adversarial robustness testing, and explanation-quality checks. The benchmark is synthetic at this stage, and I have documented limitations clearly; my next goal is larger enterprise-style evaluation with stronger analyst-labeled validation.

Your research in compromised-entity detection, anomaly detection in enterprise logs, and AI-assisted cyber attribution directly motivated this work, especially your graph-oriented and multi-detector perspective.

If possible, I would be honored to be considered for MSc supervision in your lab. I can share my CV, transcript, and a concise project brief with my exact role and contributions.

Thank you for your time and consideration.

Sincerely,  
[Your Name]  
[University / Program]  
[Email]  
[GitHub / Portfolio Link]

## 4. SOP Paragraph (paste-ready)

I developed an explainable Level 2 attack-attribution pipeline for enterprise authentication and audit logs to study how weak, distributed security signals can be fused into actionable analyst decisions. The system combines graph-based entity linking, temporal behavioral anomaly features, attack classification, and analyst feedback labels, and it is evaluated through ablation, adversarial robustness tests, and explanation-quality scoring. This project strengthened my interest in AI-based cybersecurity research at the intersection of anomaly detection, graph reasoning, and cyber attribution, which aligns closely with Dr. Euijin Choo's work on compromised-entity detection and AI-assisted attribution in enterprise security settings.

## 5. Honesty-safe Claims

Safe to claim:

- end-to-end working attribution pipeline
- reproducible benchmark and reporting
- analyst feedback loop implemented
- adversarial evaluation and limitations documented

Do not claim:

- production validation
- state-of-the-art performance
- generalization without larger external datasets
