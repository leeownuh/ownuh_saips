from __future__ import annotations

from copy import deepcopy
from datetime import datetime, timedelta
from typing import Any, Dict, List


def _stamp(base: datetime, minutes: int) -> str:
    return (base + timedelta(minutes=minutes)).strftime("%Y-%m-%d %H:%M:%S")


def _event(
    event_id: int,
    base: datetime,
    minutes: int,
    event_code: str,
    event_name: str,
    user_id: str,
    source_ip: str,
    country: str,
    device: str,
    risk: int,
    details: Dict[str, Any] | None = None,
    mfa_method: str = "none",
    region: str = "",
    user_agent: str = "Mozilla/5.0",
) -> Dict[str, Any]:
    return {
        "id": event_id,
        "event_code": event_code,
        "event_name": event_name,
        "user_id": user_id,
        "source_ip": source_ip,
        "user_agent": user_agent,
        "country_code": country,
        "region": region,
        "device_fingerprint": device,
        "mfa_method": mfa_method,
        "risk_score": risk,
        "details": details or {},
        "created_at": _stamp(base, minutes),
        "display_name": "",
        "email": user_id if "@" in user_id else "",
        "role": "user",
        "admin_id": "",
        "target_user_id": "",
    }


def build_benchmark_dataset() -> Dict[str, Any]:
    base = datetime(2026, 4, 1, 9, 0, 0)
    train_events: List[Dict[str, Any]] = []
    next_id = 1

    def add(event: Dict[str, Any], target: List[Dict[str, Any]]) -> None:
        target.append(event)

    def normal_sequence(user_id: str, ip: str, country: str, device: str, start: int) -> None:
        nonlocal next_id
        add(_event(next_id, base, start, "AUTH-001", "Successful Login", user_id, ip, country, device, 12, {"session_id": f"s-{next_id}"}, "totp"), train_events)
        next_id += 1
        add(_event(next_id, base, start + 60, "AUTH-001", "Successful Login", user_id, ip, country, device, 14, {"session_id": f"s-{next_id}"}, "totp"), train_events)
        next_id += 1

    normal_sequence("sophia.johnson@ownuh-saips.com", "203.0.113.10", "SG", "device_sophia_main", 0)
    normal_sequence("marcus.chen@ownuh-saips.com", "198.51.100.22", "US", "device_marcus_main", 30)
    normal_sequence("nina.schultz@ownuh-saips.com", "203.0.113.91", "DE", "device_nina_linux", 90)

    add(_event(next_id, base, 180, "AUTH-002", "Failed Login Attempt", "priya.patel@ownuh-saips.com", "198.54.117.212", "US", "", 92, {"username": "priya.patel@ownuh-saips.com", "reason": "bad_password"}), train_events)
    next_id += 1
    add(_event(next_id, base, 181, "AUTH-002", "Failed Login Attempt", "priya.patel@ownuh-saips.com", "198.54.117.212", "US", "", 95, {"username": "priya.patel@ownuh-saips.com", "reason": "bad_password"}), train_events)
    next_id += 1
    add(_event(next_id, base, 182, "IPS-001", "IP Blocked", "unknown", "198.54.117.212", "US", "", 97, {"block_type": "brute_force"}), train_events)
    next_id += 1

    # Additional attack-shaped training sequences so the benchmark covers the
    # same behavioural families evaluated in the test set.
    add(_event(next_id, base, 240, "AUTH-001", "Successful Login", "ava.thompson@ownuh-saips.com", "203.0.113.120", "CA", "device_ava_main", 18, {"session_id": f"s-{next_id}"}, "totp"), train_events)
    next_id += 1
    add(_event(next_id, base, 246, "AUTH-009", "Anomalous Login Reviewed", "ava.thompson@ownuh-saips.com", "185.244.25.71", "FR", "device_ava_clone", 79, {"reason": "new_country", "review_status": "open"}, "totp"), train_events)
    next_id += 1
    add(_event(next_id, base, 248, "AUTH-001", "Successful Login", "ava.thompson@ownuh-saips.com", "185.244.25.71", "FR", "device_ava_clone", 84, {"session_id": f"s-{next_id}"}, "totp"), train_events)
    next_id += 1

    add(_event(next_id, base, 300, "AUTH-002", "Failed Login Attempt", "omar.farouk@ownuh-saips.com", "203.55.71.10", "US", "", 87, {"username": "omar.farouk@ownuh-saips.com", "reason": "password_spray"}), train_events)
    next_id += 1
    add(_event(next_id, base, 301, "AUTH-002", "Failed Login Attempt", "james.harris@ownuh-saips.com", "203.55.71.10", "US", "", 89, {"username": "james.harris@ownuh-saips.com", "reason": "password_spray"}), train_events)
    next_id += 1
    add(_event(next_id, base, 302, "AUTH-002", "Failed Login Attempt", "rahul.mehta@ownuh-saips.com", "203.55.71.10", "US", "", 91, {"username": "rahul.mehta@ownuh-saips.com", "reason": "password_spray"}), train_events)
    next_id += 1
    add(_event(next_id, base, 304, "IPS-001", "IP Blocked", "unknown", "203.55.71.10", "US", "", 96, {"block_type": "credential_stuffing"}), train_events)
    next_id += 1

    add(_event(next_id, base, 360, "AUTH-002", "Failed Login Attempt", "lucia.alvarez@ownuh-saips.com", "45.83.64.21", "DE", "", 82, {"username": "lucia.alvarez@ownuh-saips.com", "reason": "bad_password"}), train_events)
    next_id += 1
    add(_event(next_id, base, 361, "AUTH-002", "Failed Login Attempt", "lucia.alvarez@ownuh-saips.com", "185.76.9.45", "NL", "", 85, {"username": "lucia.alvarez@ownuh-saips.com", "reason": "bad_password"}), train_events)
    next_id += 1
    add(_event(next_id, base, 362, "AUTH-002", "Failed Login Attempt", "lucia.alvarez@ownuh-saips.com", "154.73.12.91", "NG", "", 88, {"username": "lucia.alvarez@ownuh-saips.com", "reason": "bad_password"}), train_events)
    next_id += 1

    add(_event(next_id, base, 420, "AUTH-017", "MFA Bypass Token Issued", "alex.rivera@ownuh-saips.com", "", "US", "", 68, {"reason": "device_lost", "delivery": "manual"}), train_events)
    next_id += 1
    add(_event(next_id, base, 425, "AUTH-018", "MFA Bypass Token Consumed", "alex.rivera@ownuh-saips.com", "198.51.100.101", "CA", "device_alex_mobile", 75, {"result": "success", "next_step": "re-enroll_mfa"}, "bypass_token"), train_events)
    next_id += 1
    add(_event(next_id, base, 429, "AUTH-001", "Successful Login", "alex.rivera@ownuh-saips.com", "198.51.100.101", "CA", "device_alex_mobile", 57, {"session_id": f"s-{next_id}"}, "bypass_token"), train_events)
    next_id += 1

    cases: List[Dict[str, Any]] = []

    def case(case_id: str, label: int, attack_type: str, expected_entities: List[str], events: List[Dict[str, Any]], description: str) -> None:
        cases.append({
            "case_id": case_id,
            "label": label,
            "attack_type": attack_type,
            "expected_entities": expected_entities,
            "description": description,
            "events": events,
        })

    case(
        "benign_known_device",
        0,
        "NORMAL",
        [],
        [
            _event(1001, base, 400, "AUTH-001", "Successful Login", "james.harris@ownuh-saips.com", "203.0.113.45", "AE", "device_james_phone", 14, {"session_id": "demo-1"}, "email_otp"),
            _event(1002, base, 470, "AUTH-001", "Successful Login", "james.harris@ownuh-saips.com", "203.0.113.45", "AE", "device_james_phone", 13, {"session_id": "demo-2"}, "email_otp"),
        ],
        "Stable known-device behaviour",
    )

    case(
        "account_takeover_travel",
        1,
        "ACCOUNT_TAKEOVER",
        ["user:marcus.chen@ownuh-saips.com", "ip:185.220.101.47"],
        [
            _event(1101, base, 500, "AUTH-001", "Successful Login", "marcus.chen@ownuh-saips.com", "198.51.100.22", "US", "device_marcus_main", 16, {"session_id": "atk-1"}, "totp"),
            _event(1102, base, 505, "AUTH-009", "Anomalous Login Reviewed", "marcus.chen@ownuh-saips.com", "185.220.101.47", "NL", "device_marcus_clone", 76, {"reason": "new_country", "review_status": "open"}, "totp"),
            _event(1103, base, 507, "AUTH-001", "Successful Login", "marcus.chen@ownuh-saips.com", "185.220.101.47", "NL", "device_marcus_clone", 81, {"session_id": "atk-2"}, "totp"),
        ],
        "Impossible travel followed by a successful login from a cloned device",
    )

    case(
        "credential_stuffing",
        1,
        "CREDENTIAL_STUFFING",
        ["user:priya.patel@ownuh-saips.com", "ip:198.54.117.212"],
        [
            _event(1201, base, 600, "AUTH-002", "Failed Login Attempt", "priya.patel@ownuh-saips.com", "198.54.117.212", "US", "", 88, {"username": "priya.patel@ownuh-saips.com", "reason": "password_spray"}),
            _event(1202, base, 601, "AUTH-002", "Failed Login Attempt", "alex.rivera@ownuh-saips.com", "198.54.117.212", "US", "", 91, {"username": "alex.rivera@ownuh-saips.com", "reason": "password_spray"}),
            _event(1203, base, 602, "AUTH-002", "Failed Login Attempt", "lucia.alvarez@ownuh-saips.com", "198.54.117.212", "US", "", 93, {"username": "lucia.alvarez@ownuh-saips.com", "reason": "password_spray"}),
            _event(1204, base, 604, "IPS-001", "IP Blocked", "unknown", "198.54.117.212", "US", "", 97, {"block_type": "brute_force"}),
        ],
        "Spray behaviour against multiple accounts from one source IP",
    )

    case(
        "distributed_attack",
        1,
        "DISTRIBUTED",
        ["user:sophia.johnson@ownuh-saips.com"],
        [
            _event(1301, base, 700, "AUTH-002", "Failed Login Attempt", "sophia.johnson@ownuh-saips.com", "45.83.64.19", "DE", "", 84, {"username": "sophia.johnson@ownuh-saips.com", "reason": "bad_password"}),
            _event(1302, base, 701, "AUTH-002", "Failed Login Attempt", "sophia.johnson@ownuh-saips.com", "185.76.9.31", "NL", "", 87, {"username": "sophia.johnson@ownuh-saips.com", "reason": "bad_password"}),
            _event(1303, base, 702, "AUTH-002", "Failed Login Attempt", "sophia.johnson@ownuh-saips.com", "154.73.12.8", "NG", "", 90, {"username": "sophia.johnson@ownuh-saips.com", "reason": "bad_password"}),
        ],
        "One target account pressured from multiple IPs in a short window",
    )

    case(
        "mfa_bypass_abuse",
        1,
        "MFA_BYPASS",
        ["user:alex.rivera@ownuh-saips.com", "ip:198.51.100.101"],
        [
            _event(1401, base, 820, "AUTH-017", "MFA Bypass Token Issued", "alex.rivera@ownuh-saips.com", "", "US", "", 71, {"reason": "device_lost", "delivery": "manual"}),
            _event(1402, base, 825, "AUTH-018", "MFA Bypass Token Consumed", "alex.rivera@ownuh-saips.com", "198.51.100.101", "CA", "device_alex_mobile", 74, {"result": "success", "next_step": "re-enroll_mfa"}, "bypass_token"),
            _event(1403, base, 830, "AUTH-001", "Successful Login", "alex.rivera@ownuh-saips.com", "198.51.100.101", "CA", "device_alex_mobile", 55, {"session_id": "mfa-1"}, "bypass_token"),
        ],
        "Recovery bypass used immediately from a new device and region",
    )

    case(
        "benign_new_device_verified",
        0,
        "NORMAL",
        [],
        [
            _event(1501, base, 900, "AUTH-001", "Successful Login", "rahul.mehta@ownuh-saips.com", "198.51.100.88", "AU", "device_rahul_phone", 18, {"session_id": "ok-1", "trust_level": "new_device_verified"}, "email_otp"),
            _event(1502, base, 920, "AUTH-001", "Successful Login", "rahul.mehta@ownuh-saips.com", "198.51.100.88", "AU", "device_rahul_phone", 15, {"session_id": "ok-2", "trust_level": "known_device"}, "email_otp"),
        ],
        "Legitimate verified device enrolment",
    )

    return {"train_events": train_events, "test_cases": cases}


def clone_cases(cases: List[Dict[str, Any]]) -> List[Dict[str, Any]]:
    return deepcopy(cases)
