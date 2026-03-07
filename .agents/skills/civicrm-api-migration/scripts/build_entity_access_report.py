#!/usr/bin/env python3
"""Build a CiviCRM core entity access report.

Classify each schema entity as API4, API3, or RAW_SQL_ONLY.
"""

from __future__ import annotations

import argparse
import json
import re
import sys
from dataclasses import dataclass
from pathlib import Path
from typing import Dict, Iterable, List, Optional, Set

API4_EXCLUDE = {"Entity", "System", "ExampleData"}
API3_EXCLUDE = {"civicrm", "Generic", "utils", "Exception"}
API3_NAME_MAP = {
    "Acl": "ACL",
    "AclRole": "ACLEntityRole",
    "Im": "IM",
    "Pcp": "PCP",
}
TABLE_RE = re.compile(r"'table'\s*=>\s*'([^']+)'")


@dataclass(frozen=True)
class Row:
    entity: str
    table: str
    access_class: str
    has_api4: bool
    has_api3: bool
    source: str


def parse_args() -> argparse.Namespace:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--core", required=True, help="Path to civicrm-core checkout")
    parser.add_argument("--format", choices=["tsv", "json"], default="tsv")
    parser.add_argument("--output", help="Output file path. Default: doc/civicrm-core-entity-access.<format>")
    parser.add_argument("--stdout", action="store_true", help="Write report to stdout instead of a file")
    parser.add_argument("--no-extras", action="store_true", help="Exclude non-schema API entities")
    parser.add_argument("--summary", action="store_true", help="Print counts to stderr")
    return parser.parse_args()


def require_dir(path: Path) -> None:
    if not path.exists() or not path.is_dir():
        raise SystemExit(f"Missing directory: {path}")


def load_schema_entities(core: Path) -> Dict[str, str]:
    schema_root = core / "schema"
    require_dir(schema_root)
    out: Dict[str, str] = {}
    for file_path in sorted(schema_root.glob("**/*.entityType.php")):
        entity = file_path.name[: -len(".entityType.php")]
        text = file_path.read_text(encoding="utf-8", errors="ignore")
        table_match = TABLE_RE.search(text)
        table = table_match.group(1) if table_match else ""
        out[entity] = table
    return out


def load_api4_entities(core: Path) -> Set[str]:
    root = core / "Civi" / "Api4"
    require_dir(root)
    return {p.stem for p in root.glob("*.php") if p.stem not in API4_EXCLUDE}


def load_api3_entities(core: Path) -> Set[str]:
    root = core / "api" / "v3"
    require_dir(root)
    normalized: Set[str] = set()
    for file_path in root.glob("*.php"):
        name = file_path.stem
        if name in API3_EXCLUDE:
            continue
        normalized.add(API3_NAME_MAP.get(name, name))
    return normalized


def classify(schema: Dict[str, str], api4: Set[str], api3: Set[str], include_extras: bool) -> List[Row]:
    rows: List[Row] = []
    for entity in sorted(schema):
        has_api4 = entity in api4
        has_api3 = entity in api3
        if has_api4:
            access_class = "API4"
        elif has_api3:
            access_class = "API3"
        else:
            access_class = "RAW_SQL_ONLY"
        rows.append(Row(entity, schema[entity], access_class, has_api4, has_api3, "schema"))

    if include_extras:
        schema_set = set(schema)
        for entity in sorted(api4 - schema_set):
            rows.append(Row(entity, "", "API4_EXTRA_NON_SCHEMA", True, False, "api4_only"))
        for entity in sorted(api3 - schema_set):
            rows.append(Row(entity, "", "API3_EXTRA_NON_SCHEMA", False, True, "api3_only"))

    return rows


def to_json(rows: Iterable[Row]) -> str:
    rows_list = list(rows)
    payload = {
        "counts": {
            "total": len(rows_list),
            "schema_total": sum(1 for r in rows_list if r.source == "schema"),
            "api4": sum(1 for r in rows_list if r.access_class == "API4"),
            "api3": sum(1 for r in rows_list if r.access_class == "API3"),
            "raw_sql_only": sum(1 for r in rows_list if r.access_class == "RAW_SQL_ONLY"),
            "api4_extra_non_schema": sum(1 for r in rows_list if r.access_class == "API4_EXTRA_NON_SCHEMA"),
            "api3_extra_non_schema": sum(1 for r in rows_list if r.access_class == "API3_EXTRA_NON_SCHEMA"),
        },
        "rows": [
            {
                "entity": r.entity,
                "table": r.table,
                "access_class": r.access_class,
                "has_api4": r.has_api4,
                "has_api3": r.has_api3,
                "source": r.source,
            }
            for r in rows_list
        ],
    }
    return json.dumps(payload, indent=2, ensure_ascii=True)


def to_tsv(rows: Iterable[Row]) -> str:
    lines = ["entity\ttable\taccess_class\thas_api4\thas_api3\tsource"]
    for row in rows:
        lines.append(
            "\t".join(
                [
                    row.entity,
                    row.table,
                    row.access_class,
                    "yes" if row.has_api4 else "no",
                    "yes" if row.has_api3 else "no",
                    row.source,
                ]
            )
        )
    return "\n".join(lines) + "\n"


def resolve_output_path(fmt: str, output: Optional[str], stdout: bool) -> Optional[Path]:
    if stdout:
        return None
    if output:
        return Path(output)
    return Path.cwd() / "doc" / f"civicrm-core-entity-access.{fmt}"


def write_output(text: str, output_path: Optional[Path]) -> None:
    if output_path is None:
        print(text, end="")
        return
    output_path.parent.mkdir(parents=True, exist_ok=True)
    output_path.write_text(text, encoding="utf-8")
    print(f"Wrote report: {output_path}", file=sys.stderr)


def print_summary(rows: List[Row]) -> None:
    summary = {
        "schema_total": sum(1 for r in rows if r.source == "schema"),
        "api4": sum(1 for r in rows if r.access_class == "API4"),
        "api3": sum(1 for r in rows if r.access_class == "API3"),
        "raw_sql_only": sum(1 for r in rows if r.access_class == "RAW_SQL_ONLY"),
        "api4_extra_non_schema": sum(1 for r in rows if r.access_class == "API4_EXTRA_NON_SCHEMA"),
        "api3_extra_non_schema": sum(1 for r in rows if r.access_class == "API3_EXTRA_NON_SCHEMA"),
    }
    print(json.dumps(summary, indent=2, ensure_ascii=True), file=sys.stderr)


def main() -> None:
    args = parse_args()
    core = Path(args.core)
    schema = load_schema_entities(core)
    api4 = load_api4_entities(core)
    api3 = load_api3_entities(core)

    rows = classify(schema, api4, api3, include_extras=not args.no_extras)

    rendered = to_tsv(rows) if args.format == "tsv" else to_json(rows)
    output_path = resolve_output_path(args.format, args.output, args.stdout)
    write_output(rendered, output_path)

    if args.summary:
        print_summary(rows)


if __name__ == "__main__":
    main()
