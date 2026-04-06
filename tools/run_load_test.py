import concurrent.futures
import datetime
import sqlite3
import threading
import time
from pathlib import Path


ROOT = Path(__file__).resolve().parents[1]
DB_PATH = ROOT / "database.sqlite"
REPORT_PATH = ROOT / "docs" / "LOAD_TEST_REPORT_2026-03-29.md"
LOG_PATH = ROOT / "docs" / "LOAD_TEST_LOG_2026-03-29.txt"

VIRTUAL_USERS = 50


def fetch_tables(conn: sqlite3.Connection) -> list[str]:
    rows = conn.execute(
        "SELECT name FROM sqlite_master WHERE type='table' AND name NOT IN ('sqlite_sequence') ORDER BY name"
    ).fetchall()
    return [r[0] for r in rows]


def read_task(user_id: int, table: str) -> tuple[int, str, bool, float, str]:
    started = time.perf_counter()
    conn = sqlite3.connect(DB_PATH)
    conn.execute("PRAGMA foreign_keys=ON")
    conn.text_factory = bytes
    try:
        cur = conn.cursor()
        count = cur.execute(f"SELECT COUNT(*) FROM {table}").fetchone()[0]
        cur.execute(f"SELECT * FROM {table} LIMIT 5").fetchall()
        elapsed = (time.perf_counter() - started) * 1000
        return (user_id, table, True, elapsed, f"count={count}")
    except Exception as e:
        elapsed = (time.perf_counter() - started) * 1000
        return (user_id, table, False, elapsed, str(e))
    finally:
        conn.close()


def write_simulation_rollback() -> tuple[bool, str]:
    conn = sqlite3.connect(DB_PATH)
    conn.execute("PRAGMA foreign_keys=ON")
    cur = conn.cursor()
    cur.execute("SAVEPOINT load_write")
    try:
        user_id = cur.execute("SELECT id FROM users ORDER BY id LIMIT 1").fetchone()[0]
        project_id = cur.execute("SELECT id FROM projects ORDER BY id LIMIT 1").fetchone()[0]
        suite_id = cur.execute("SELECT id FROM test_suites ORDER BY id LIMIT 1").fetchone()[0]

        for i in range(VIRTUAL_USERS):
            cur.execute(
                "INSERT INTO bugs (project_id,title,severity,priority,reporter_id) VALUES (?,?,?,?,?)",
                (project_id, f"LT_BUG_{i}", "minor", "low", user_id),
            )
            cur.execute(
                "INSERT INTO test_cases (suite_id,title,steps_json,expected_result_json,created_by) VALUES (?,?,?,?,?)",
                (suite_id, f"LT_TC_{i}", "[]", "[]", user_id),
            )
        return True, "50x inserts into bugs and test_cases executed inside rollback scope"
    except Exception as e:
        return False, str(e)
    finally:
        cur.execute("ROLLBACK TO load_write")
        cur.execute("RELEASE load_write")
        conn.close()


def main() -> int:
    t0 = time.perf_counter()
    base_conn = sqlite3.connect(DB_PATH)
    tables = fetch_tables(base_conn)
    base_conn.close()

    logs: list[str] = []
    logs.append(f"{datetime.datetime.now()} | START load test users={VIRTUAL_USERS} tables={len(tables)}")
    lock = threading.Lock()
    results: list[tuple[int, str, bool, float, str]] = []

    with concurrent.futures.ThreadPoolExecutor(max_workers=VIRTUAL_USERS) as pool:
        futures = []
        for uid in range(1, VIRTUAL_USERS + 1):
            for table in tables:
                futures.append(pool.submit(read_task, uid, table))
        for fut in concurrent.futures.as_completed(futures):
            row = fut.result()
            with lock:
                results.append(row)

    write_ok, write_msg = write_simulation_rollback()
    logs.append(f"{datetime.datetime.now()} | WRITE_SIM rollback mode: {write_ok} | {write_msg}")

    total = len(results)
    passed = sum(1 for r in results if r[2])
    failed = total - passed
    avg_ms = (sum(r[3] for r in results) / total) if total else 0.0
    p95_ms = sorted(r[3] for r in results)[int(total * 0.95) - 1] if total else 0.0
    elapsed_all = (time.perf_counter() - t0) * 1000

    sample_errors = [r for r in results if not r[2]][:10]
    for uid, table, ok, ms, detail in results[:30]:
        logs.append(f"user={uid} table={table} ok={ok} time_ms={ms:.2f} detail={detail}")
    for uid, table, ok, ms, detail in sample_errors:
        logs.append(f"ERROR user={uid} table={table} time_ms={ms:.2f} detail={detail}")
    logs.append(f"{datetime.datetime.now()} | END total_ops={total} passed={passed} failed={failed}")

    report_lines = [
        "# Load Test Report (50 virtual users)",
        "",
        f"Date: {datetime.date.today()}",
        f"Tables covered: {len(tables)}",
        f"Total read operations: {total}",
        f"Passed: {passed}",
        f"Failed: {failed}",
        f"Avg response (ms): {avg_ms:.2f}",
        f"P95 response (ms): {p95_ms:.2f}",
        f"Total elapsed (ms): {elapsed_all:.2f}",
        "",
        "## Data safety",
        "",
        f"- Write simulation in rollback scope: {'PASS' if write_ok else 'FAIL'}",
        f"- Details: {write_msg}",
        "- No inserted load-test records were persisted to database.",
        "",
        "## Covered tables",
        "",
        ", ".join(tables),
        "",
    ]

    REPORT_PATH.write_text("\n".join(report_lines), encoding="utf-8")
    LOG_PATH.write_text("\n".join(logs), encoding="utf-8")
    print("\n".join(report_lines))
    print(f"LOG_FILE={LOG_PATH}")

    return 0 if failed == 0 and write_ok else 1


if __name__ == "__main__":
    raise SystemExit(main())
