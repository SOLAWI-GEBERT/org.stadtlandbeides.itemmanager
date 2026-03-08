#!/usr/bin/env python3
"""Rewrite supported civicrm_api3(...) calls into API4 migration candidates.

Safety rules:
- Convert only explicitly supported API3 actions.
- Convert only entities that are confirmed as API4 entities in the provided civicrm-core checkout.
- Leave unsupported actions and non-API4 entities unchanged.
- Always emit syntactically runnable PHP output.
"""

from __future__ import annotations

import argparse
import json
import re
import sys
from dataclasses import dataclass
from pathlib import Path
from typing import Optional, Set, Tuple

from build_entity_access_report import load_api4_entities

CALL_RE = re.compile(
    r"civicrm_api3\(\s*(['\"])(?P<entity>[A-Za-z0-9_]+)\1\s*,\s*(['\"])(?P<action>[A-Za-z0-9_]+)\3\s*,\s*(?P<params>.*?)\)\s*;",
    re.DOTALL,
)

ENTITY_MAP = {
    "Acl": "ACL",
    "AclRole": "ACLEntityRole",
    "Im": "IM",
    "Pcp": "PCP",
}
SUPPORTED_ACTIONS = {"get", "getsingle", "getcount", "create", "update", "delete"}


@dataclass
class Stats:
    matched: int = 0
    converted: int = 0
    skipped_unsupported_action: int = 0
    skipped_no_api4_entity: int = 0
    skipped_missing_core: int = 0


def parse_args() -> argparse.Namespace:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--core", help="Path to civicrm-core checkout (required for safe conversion)")
    parser.add_argument("--input", help="Path to PHP file. Omit to read stdin")
    parser.add_argument("--output", help="Output file path. Defaults to stdout")
    parser.add_argument("--in-place", action="store_true", help="Overwrite --input file")
    parser.add_argument("--quiet", action="store_true", help="Do not print conversion summary")
    return parser.parse_args()


def load_source(args: argparse.Namespace) -> str:
    if args.input:
        return Path(args.input).read_text(encoding="utf-8", errors="ignore")
    return sys.stdin.read()


def normalize_entity(name: str) -> str:
    return ENTITY_MAP.get(name, name)


def load_api4_catalog(core: Optional[str]) -> Optional[Set[str]]:
    if not core:
        return None
    return load_api4_entities(Path(core))


def build_api4_expression(entity: str, action: str, params: str) -> str:
    action = action.lower()
    params_clean = params.strip()

    if action == "get":
        return (
            f"\\Civi\\Api4\\{entity}::get(FALSE)\n"
            "  // TODO: map API3 params to API4 query methods.\n"
            f"  /* original params: {params_clean} */\n"
            "  ->execute();"
        )

    if action == "getsingle":
        return (
            f"\\Civi\\Api4\\{entity}::get(FALSE)\n"
            "  // TODO: map API3 params to API4 query methods.\n"
            f"  /* original params: {params_clean} */\n"
            "  ->execute()->single();"
        )

    if action == "getcount":
        return (
            f"\\Civi\\Api4\\{entity}::get(FALSE)\n"
            "  // TODO: map API3 filters to API4 where clauses.\n"
            f"  /* original params: {params_clean} */\n"
            "  ->execute()->countMatched();"
        )

    if action == "create":
        return f"\\Civi\\Api4\\{entity}::create(FALSE)->setValues({params_clean})->execute();"

    if action == "update":
        return (
            f"\\Civi\\Api4\\{entity}::update(FALSE)\n"
            f"  ->setValues({params_clean})\n"
            "  // TODO: ensure update filters are explicit in API4.\n"
            "  ->execute();"
        )

    if action == "delete":
        return (
            f"\\Civi\\Api4\\{entity}::delete(FALSE)\n"
            "  // TODO: map API3 delete filters to API4 where clauses.\n"
            f"  /* original params: {params_clean} */\n"
            "  ->execute();"
        )

    return ""


def convert(text: str, api4_catalog: Optional[Set[str]]) -> Tuple[str, Stats]:
    stats = Stats()

    def repl(match: re.Match[str]) -> str:
        stats.matched += 1
        original = match.group(0)
        action = match.group("action").lower()

        if action not in SUPPORTED_ACTIONS:
            stats.skipped_unsupported_action += 1
            return original

        if api4_catalog is None:
            stats.skipped_missing_core += 1
            return original

        entity = normalize_entity(match.group("entity"))
        if entity not in api4_catalog:
            stats.skipped_no_api4_entity += 1
            return original

        replacement = build_api4_expression(entity, action, match.group("params"))
        if not replacement:
            stats.skipped_unsupported_action += 1
            return original

        stats.converted += 1
        return replacement

    return CALL_RE.sub(repl, text), stats


def write_output(args: argparse.Namespace, text: str) -> None:
    if args.in_place:
        if not args.input:
            raise SystemExit("--in-place requires --input")
        Path(args.input).write_text(text, encoding="utf-8")
        return

    if args.output:
        Path(args.output).write_text(text, encoding="utf-8")
        return

    print(text, end="")


def print_stats(stats: Stats) -> None:
    payload = {
        "matched": stats.matched,
        "converted": stats.converted,
        "skipped_unsupported_action": stats.skipped_unsupported_action,
        "skipped_no_api4_entity": stats.skipped_no_api4_entity,
        "skipped_missing_core": stats.skipped_missing_core,
    }
    print(json.dumps(payload, ensure_ascii=True), file=sys.stderr)


def main() -> None:
    args = parse_args()
    source = load_source(args)
    api4_catalog = load_api4_catalog(args.core)
    converted, stats = convert(source, api4_catalog)
    write_output(args, converted)
    if not args.quiet:
        print_stats(stats)


if __name__ == "__main__":
    main()
