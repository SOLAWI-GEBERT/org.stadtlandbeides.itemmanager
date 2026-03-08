#!/usr/bin/env python3
"""Suggest CiviCRM API access from raw SQL snippets.

For tables/entities without API support, the tool explicitly recommends keeping raw SQL.
"""

from __future__ import annotations

import argparse
import json
import re
import sys
from pathlib import Path
from typing import Dict, List

from build_entity_access_report import classify, load_api3_entities, load_api4_entities, load_schema_entities

TABLE_RE = re.compile(r"\bcivicrm_[a-z0-9_]+\b", re.IGNORECASE)
VERB_RE = re.compile(r"^\s*([a-z]+)", re.IGNORECASE)


def parse_args() -> argparse.Namespace:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--core", required=True, help="Path to civicrm-core checkout")
    parser.add_argument("--sql", help="SQL string")
    parser.add_argument("--sql-file", help="Path to file containing SQL")
    parser.add_argument("--format", choices=["text", "json"], default="text")
    return parser.parse_args()


def read_sql(args: argparse.Namespace) -> str:
    if args.sql:
        return args.sql
    if args.sql_file:
        return Path(args.sql_file).read_text(encoding="utf-8", errors="ignore")
    text = sys.stdin.read()
    if not text:
        raise SystemExit("Provide --sql, --sql-file, or stdin input")
    return text


def detect_action(sql: str) -> str:
    match = VERB_RE.search(sql)
    verb = match.group(1).upper() if match else "SELECT"
    return {
        "SELECT": "get",
        "INSERT": "create",
        "UPDATE": "update",
        "DELETE": "delete",
        "REPLACE": "create",
    }.get(verb, "get")


def classify_entities(core: Path) -> Dict[str, str]:
    schema = load_schema_entities(core)
    rows = classify(schema, load_api4_entities(core), load_api3_entities(core), include_extras=False)
    return {row.entity: row.access_class for row in rows}


def build_table_to_entities(core: Path) -> Dict[str, List[str]]:
    schema = load_schema_entities(core)
    mapping: Dict[str, List[str]] = {}
    for entity, table in schema.items():
        if not table:
            continue
        mapping.setdefault(table.lower(), []).append(entity)
    return mapping


def api4_example(entity: str, action: str) -> str:
    action = action.lower()
    if action not in {"get", "create", "update", "delete"}:
        action = "get"
    return f"\\Civi\\Api4\\{entity}::{action}(FALSE)->execute();"


def api3_example(entity: str, action: str) -> str:
    return f"civicrm_api3('{entity}', '{action}', $params);"


def suggest(sql: str, table_entities: Dict[str, List[str]], entity_access: Dict[str, str]) -> List[Dict[str, object]]:
    action = detect_action(sql)
    tables = sorted({t.lower() for t in TABLE_RE.findall(sql)})
    out: List[Dict[str, object]] = []

    if not tables:
        out.append(
            {
                "table": "",
                "entity": "",
                "access_class": "UNKNOWN",
                "action": action,
                "decision": "keep_raw_sql",
                "keep_raw_sql": True,
                "suggestion": "No civicrm_* table detected in SQL.",
            }
        )
        return out

    for table in tables:
        entities = table_entities.get(table, [])
        if not entities:
            out.append(
                {
                    "table": table,
                    "entity": "",
                    "access_class": "UNKNOWN_TABLE",
                    "action": action,
                    "decision": "keep_raw_sql",
                    "keep_raw_sql": True,
                    "suggestion": "Table not found in schema entity map. Keep raw SQL.",
                }
            )
            continue

        for entity in sorted(entities):
            access = entity_access.get(entity, "RAW_SQL_ONLY")
            if access == "API4":
                decision = "use_api4"
                keep_raw_sql = False
                suggestion = api4_example(entity, action)
            elif access == "API3":
                decision = "use_api3"
                keep_raw_sql = False
                suggestion = api3_example(entity, action)
            else:
                decision = "keep_raw_sql"
                keep_raw_sql = True
                suggestion = "No core API endpoint detected; keep raw SQL."

            out.append(
                {
                    "table": table,
                    "entity": entity,
                    "access_class": access,
                    "action": action,
                    "decision": decision,
                    "keep_raw_sql": keep_raw_sql,
                    "suggestion": suggestion,
                }
            )
    return out


def render_text(rows: List[Dict[str, object]]) -> str:
    lines = []
    for row in rows:
        lines.append(
            " | ".join(
                [
                    f"table={row['table'] or '-'}",
                    f"entity={row['entity'] or '-'}",
                    f"class={row['access_class']}",
                    f"action={row['action']}",
                    f"decision={row['decision']}",
                    f"keep_raw_sql={'yes' if row['keep_raw_sql'] else 'no'}",
                    f"hint={row['suggestion']}",
                ]
            )
        )
    return "\n".join(lines) + "\n"


def main() -> None:
    args = parse_args()
    core = Path(args.core)
    sql = read_sql(args)

    entity_access = classify_entities(core)
    table_entities = build_table_to_entities(core)
    rows = suggest(sql, table_entities, entity_access)

    if args.format == "json":
        print(json.dumps({"rows": rows}, indent=2, ensure_ascii=True))
    else:
        print(render_text(rows), end="")


if __name__ == "__main__":
    main()
