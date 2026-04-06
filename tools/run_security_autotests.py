import datetime
import sqlite3
from pathlib import Path


ROOT = Path(__file__).resolve().parents[1]
DB_PATH = ROOT / "database.sqlite"
DOC_PATH = ROOT / "docs" / "SECURITY_AUTOTEST_REPORT.md"
LOG_PATH = ROOT / "docs" / "SECURITY_AUTOTEST_LOG.txt"
AI_API_PATH = ROOT / "api" / "ai.php"
ENC_PATH = ROOT / "includes" / "encryption.php"


def now_tag() -> str:
    return datetime.datetime.now().strftime("%Y-%m-%d %H:%M:%S")


def main() -> int:
    logs: list[str] = [f"{now_tag()} | START security autotests"]
    results: list[tuple[str, bool, str]] = []

    conn = sqlite3.connect(DB_PATH)
    conn.execute("PRAGMA foreign_keys=ON")
    cur = conn.cursor()
    cur.execute("SAVEPOINT security_autotest")

    try:
        # ST-1: security tables exist
        tables = {
            row[0]
            for row in cur.execute(
                "SELECT name FROM sqlite_master WHERE type='table' AND name IN ('security_log','rate_limit_entries','ai_logs')"
            ).fetchall()
        }
        ok_tables = {"security_log", "rate_limit_entries", "ai_logs"}.issubset(tables)
        results.append(("ST-1 required security tables", ok_tables, ",".join(sorted(tables))))

        # ST-2: AI API includes rate limiting hook
        ai_api = AI_API_PATH.read_text(encoding="utf-8")
        has_rate_limit = "check_rate_limit(" in ai_api and "add_rate_limit_attempt(" in ai_api
        results.append(("ST-2 ai rate limit wired", has_rate_limit, "rate limit calls found" if has_rate_limit else "missing calls"))

        # ST-3: AI API avoids leaking raw exception details
        no_details_leak = "'details' => $e->getMessage()" not in ai_api
        results.append(("ST-3 ai exception details hidden", no_details_leak, "details not exposed" if no_details_leak else "details leak found"))

        # ST-4: AI logs encryption marker
        has_enc_marker = "enc:' . encrypt_value(" in ai_api
        results.append(("ST-4 ai log encryption enabled", has_enc_marker, "enc marker write found" if has_enc_marker else "enc marker write missing"))

        # ST-5: encryption fallback key check (warning if present)
        enc_php = ENC_PATH.read_text(encoding="utf-8")
        has_dev_fallback = "change-this-dev-key-32-bytes-minimum" in enc_php
        # This test passes only when fallback is NOT present.
        results.append(("ST-5 no hardcoded fallback key", not has_dev_fallback, "fallback key present in code" if has_dev_fallback else "fallback key absent"))

        # ST-6: DB constraint check for invalid bug status still blocks
        status_ok = False
        try:
            cur.execute(
                "INSERT INTO bugs (project_id,title,severity,priority,status,reporter_id) VALUES (?,?,?,?,?,?)",
                (4, "SEC_BAD_STATUS_" + datetime.datetime.now().strftime("%H%M%S"), "major", "high", "invalid_status", 1),
            )
        except sqlite3.IntegrityError as e:
            status_ok = True
            logs.append(f"{now_tag()} | ST-6 blocked invalid bug status: {e}")
        results.append(("ST-6 db status constraint", status_ok, "blocked" if status_ok else "not blocked"))

    finally:
        cur.execute("ROLLBACK TO security_autotest")
        cur.execute("RELEASE security_autotest")
        conn.close()
        logs.append(f"{now_tag()} | ROLLBACK complete; no data persisted")

    overall = all(item[1] for item in results)
    lines = [
        "# Security Autotest Report",
        "",
        f"Date: {datetime.date.today()}",
        "Mode: read/static checks + transactional DB checks",
        "",
        "## Results",
        "",
    ]
    for name, ok, detail in results:
        lines.append(f"- {'PASS' if ok else 'FAIL'} | {name} | {detail}")
    lines.extend(
        [
            "",
            f"**OVERALL:** {'PASS' if overall else 'FAIL'}",
            "",
            "## Notes",
            "",
            "- DB checks run under savepoint and rolled back.",
            "- Static checks validate presence of security controls in source code.",
            "",
        ]
    )

    DOC_PATH.write_text("\n".join(lines), encoding="utf-8")
    LOG_PATH.write_text("\n".join(logs), encoding="utf-8")
    print("\n".join(lines))
    print(f"LOG_FILE={LOG_PATH}")
    return 0 if overall else 1


if __name__ == "__main__":
    raise SystemExit(main())

