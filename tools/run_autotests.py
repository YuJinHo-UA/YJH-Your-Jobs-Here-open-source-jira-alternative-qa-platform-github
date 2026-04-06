import datetime
import sqlite3
from pathlib import Path


ROOT = Path(__file__).resolve().parents[1]
DB_PATH = ROOT / "database.sqlite"
DOC_PATH = ROOT / "docs" / "AUTOTEST_REPORT.md"
LOG_PATH = ROOT / "docs" / "AUTOTEST_LOG.txt"


def now_tag() -> str:
    return datetime.datetime.now().strftime("%Y-%m-%d %H:%M:%S")


def main() -> int:
    conn = sqlite3.connect(DB_PATH)
    conn.execute("PRAGMA foreign_keys=ON")
    cur = conn.cursor()
    cur.execute("SAVEPOINT autotest")

    logs: list[str] = []
    results: list[tuple[str, bool, str]] = []

    try:
        logs.append(f"{now_tag()} | START autotests")
        user_id = cur.execute("SELECT id FROM users ORDER BY id LIMIT 1").fetchone()[0]
        project_id = cur.execute("SELECT id FROM projects ORDER BY id LIMIT 1").fetchone()[0]
        suite_id = cur.execute("SELECT id FROM test_suites ORDER BY id LIMIT 1").fetchone()[0]
        plan_id = cur.execute("SELECT id FROM test_plans ORDER BY id LIMIT 1").fetchone()[0]
        logs.append(f"{now_tag()} | INIT user={user_id} project={project_id} suite={suite_id} plan={plan_id}")

        # TEST 1: create/read bug
        bug_title = "AT_BUG_" + datetime.datetime.now().strftime("%H%M%S")
        cur.execute(
            "INSERT INTO bugs (project_id,title,description,severity,priority,reporter_id) VALUES (?,?,?,?,?,?)",
            (project_id, bug_title, "autotest", "major", "high", user_id),
        )
        row = cur.execute("SELECT id,title FROM bugs WHERE title=?", (bug_title,)).fetchone()
        ok = row is not None and row[1] == bug_title
        results.append(("AT-1 bug create/read", ok, str(row)))
        logs.append(f"{now_tag()} | AT-1 row={row}")

        # TEST 2: unique email protection
        email = "at_unique_" + datetime.datetime.now().strftime("%H%M%S") + "@example.com"
        cur.execute(
            "INSERT INTO users (username,email,password_hash,role) VALUES (?,?,?,?)",
            ("at_user_" + datetime.datetime.now().strftime("%H%M%S"), email, "hash", "qa"),
        )
        unique_ok = False
        try:
            cur.execute(
                "INSERT INTO users (username,email,password_hash,role) VALUES (?,?,?,?)",
                ("at_user_dup_" + datetime.datetime.now().strftime("%H%M%S"), email, "hash", "qa"),
            )
        except sqlite3.IntegrityError as e:
            unique_ok = True
            logs.append(f"{now_tag()} | AT-2 duplicate blocked: {e}")
        results.append(("AT-2 unique email", unique_ok, "duplicate blocked" if unique_ok else "duplicate inserted"))

        # TEST 3: testcase linked to bug via test_executions
        tc_title = "AT_TC_" + datetime.datetime.now().strftime("%H%M%S")
        cur.execute(
            "INSERT INTO test_cases (suite_id,title,steps_json,expected_result_json,created_by) VALUES (?,?,?,?,?)",
            (suite_id, tc_title, "[]", "[]", user_id),
        )
        tc_id = cur.lastrowid
        bug2_title = "AT_LINKBUG_" + datetime.datetime.now().strftime("%H%M%S")
        cur.execute(
            "INSERT INTO bugs (project_id,title,severity,priority,reporter_id) VALUES (?,?,?,?,?)",
            (project_id, bug2_title, "critical", "highest", user_id),
        )
        bug_id = cur.lastrowid
        cur.execute(
            "INSERT INTO test_runs (plan_id,name,created_by) VALUES (?,?,?)",
            (plan_id, "AT_RUN_" + datetime.datetime.now().strftime("%H%M%S"), user_id),
        )
        run_id = cur.lastrowid
        cur.execute(
            "INSERT INTO test_executions (test_run_id,test_case_id,executed_by,status,bug_id) VALUES (?,?,?,?,?)",
            (run_id, tc_id, user_id, "fail", bug_id),
        )
        joined = cur.execute(
            "SELECT tc.title,b.title FROM test_cases tc "
            "LEFT JOIN test_executions te ON tc.id=te.test_case_id "
            "LEFT JOIN bugs b ON te.bug_id=b.id WHERE tc.id=?",
            (tc_id,),
        ).fetchone()
        rel_ok = bool(joined and joined[0] and joined[1])
        results.append(("AT-3 testcase-bug link", rel_ok, str(joined)))
        logs.append(f"{now_tag()} | AT-3 join={joined}")

        # TEST 4: reject invalid bug status by CHECK constraint
        invalid_status_ok = False
        try:
            cur.execute(
                "INSERT INTO bugs (project_id,title,severity,priority,status,reporter_id) VALUES (?,?,?,?,?,?)",
                (project_id, "AT_BAD_STATUS_" + datetime.datetime.now().strftime("%H%M%S"), "major", "high", "invalid_status", user_id),
            )
        except sqlite3.IntegrityError as e:
            invalid_status_ok = True
            logs.append(f"{now_tag()} | AT-4 invalid status blocked: {e}")
        results.append(("AT-4 bug status validation", invalid_status_ok, "invalid status blocked" if invalid_status_ok else "invalid status accepted"))

        # TEST 5: FK protection for invalid bug_id in test_executions
        fk_ok = False
        tc_title_fk = "AT_TC_FK_" + datetime.datetime.now().strftime("%H%M%S")
        cur.execute(
            "INSERT INTO test_cases (suite_id,title,steps_json,expected_result_json,created_by) VALUES (?,?,?,?,?)",
            (suite_id, tc_title_fk, "[]", "[]", user_id),
        )
        tc_fk_id = cur.lastrowid
        cur.execute(
            "INSERT INTO test_runs (plan_id,name,created_by) VALUES (?,?,?)",
            (plan_id, "AT_RUN_FK_" + datetime.datetime.now().strftime("%H%M%S"), user_id),
        )
        run_fk_id = cur.lastrowid
        try:
            cur.execute(
                "INSERT INTO test_executions (test_run_id,test_case_id,executed_by,status,bug_id) VALUES (?,?,?,?,?)",
                (run_fk_id, tc_fk_id, user_id, "fail", 99999999),
            )
        except sqlite3.IntegrityError as e:
            fk_ok = True
            logs.append(f"{now_tag()} | AT-5 foreign key blocked: {e}")
        results.append(("AT-5 execution bug foreign key", fk_ok, "invalid bug_id blocked" if fk_ok else "invalid bug_id accepted"))

        # TEST 6: translation cache unique composite key
        cache_ok = False
        text_hash = "athash_" + datetime.datetime.now().strftime("%H%M%S")
        cur.execute(
            "INSERT INTO translation_cache (source_lang,target_lang,text_hash,source_text,translated_text,provider) VALUES (?,?,?,?,?,?)",
            ("en", "uk", text_hash, "hello", "привіт", "autotest"),
        )
        try:
            cur.execute(
                "INSERT INTO translation_cache (source_lang,target_lang,text_hash,source_text,translated_text,provider) VALUES (?,?,?,?,?,?)",
                ("en", "uk", text_hash, "hello", "вітаю", "autotest"),
            )
        except sqlite3.IntegrityError as e:
            cache_ok = True
            logs.append(f"{now_tag()} | AT-6 translation cache duplicate blocked: {e}")
        results.append(("AT-6 translation cache uniqueness", cache_ok, "duplicate key blocked" if cache_ok else "duplicate key accepted"))

        # TEST 7: board card position update persists
        card_ok = False
        board = cur.execute("SELECT id FROM boards ORDER BY id LIMIT 1").fetchone()
        column = cur.execute("SELECT id FROM board_columns ORDER BY id LIMIT 1").fetchone()
        if board and column:
            cur.execute(
                "INSERT INTO board_cards (board_id,column_id,title,order_index) VALUES (?,?,?,?)",
                (board[0], column[0], "AT_CARD_" + datetime.datetime.now().strftime("%H%M%S"), 99),
            )
            card_id = cur.lastrowid
            cur.execute("UPDATE board_cards SET order_index=? WHERE id=?", (1, card_id))
            order_value = cur.execute("SELECT order_index FROM board_cards WHERE id=?", (card_id,)).fetchone()
            card_ok = bool(order_value and order_value[0] == 1)
            logs.append(f"{now_tag()} | AT-7 board card order={order_value}")
        results.append(("AT-7 kanban card reorder", card_ok, "order updated" if card_ok else "board/column not found or update failed"))

    finally:
        cur.execute("ROLLBACK TO autotest")
        cur.execute("RELEASE autotest")
        conn.close()
        logs.append(f"{now_tag()} | ROLLBACK complete; no data persisted")

    overall = all(r[1] for r in results)
    lines = [
        "# Autotest Report",
        "",
        f"Date: {datetime.date.today()}",
        "Mode: transactional (`SAVEPOINT` + `ROLLBACK`)",
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
            "- Tests executed against project `database.sqlite`.",
            "- Created entities were rolled back and not persisted.",
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
