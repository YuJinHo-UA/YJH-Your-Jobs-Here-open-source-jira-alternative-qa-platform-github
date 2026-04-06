import datetime
import json
import sqlite3
from pathlib import Path


ROOT = Path(__file__).resolve().parents[1]
DB_PATH = ROOT / "database.sqlite"
DOC_PATH = ROOT / "docs" / "PROGRAM_ACTIVITY_LOG.md"


def main() -> int:
    conn = sqlite3.connect(DB_PATH)
    conn.row_factory = sqlite3.Row
    cur = conn.cursor()
    rows = cur.execute(
        "SELECT id, user_id, action, target_type, target_id, details_json, created_at "
        "FROM activity_log ORDER BY id DESC"
    ).fetchall()
    conn.close()

    lines = [
        "# Program Activity Log",
        "",
        f"Generated: {datetime.datetime.now().strftime('%Y-%m-%d %H:%M:%S')}",
        "Source: `activity_log` table",
        "",
        "## Entries",
        "",
    ]

    if not rows:
        lines.append("- No activity entries found.")
    else:
        for row in rows:
            details_raw = row["details_json"] or ""
            details = details_raw
            try:
                parsed = json.loads(details_raw) if details_raw else None
                if parsed is not None:
                    details = json.dumps(parsed, ensure_ascii=False)
            except Exception:
                pass
            lines.append(
                f"- `#{row['id']}` | {row['created_at']} | user={row['user_id']} | "
                f"{row['action']} `{row['target_type']}` id={row['target_id']} | details={details}"
            )

    DOC_PATH.write_text("\n".join(lines) + "\n", encoding="utf-8")
    print(f"OK: wrote {DOC_PATH}")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
