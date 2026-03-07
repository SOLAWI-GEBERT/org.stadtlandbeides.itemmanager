#!/usr/bin/env python3
"""Locate files and folders and print compact repository-relative overviews."""

from __future__ import annotations

import argparse
import difflib
import json
import os
import re
import sys
from dataclasses import asdict, dataclass
from pathlib import Path
from typing import Iterable

SKIP_DIRS = {".git"}


@dataclass
class Candidate:
    path: str
    score: int


def normalize(value: str) -> str:
    return re.sub(r"[^a-z0-9]+", " ", value.lower()).strip()


def compact(value: str) -> str:
    return re.sub(r"[^a-z0-9]+", "", value.lower())


def iter_repo_files(root: Path) -> Iterable[str]:
    for current_root, dirnames, filenames in os.walk(root):
        dirnames[:] = sorted(
            [name for name in dirnames if name not in SKIP_DIRS],
            key=str.lower,
        )
        for filename in sorted(filenames, key=str.lower):
            yield (Path(current_root) / filename).relative_to(root).as_posix()


def iter_repo_dirs(root: Path) -> Iterable[str]:
    for current_root, dirnames, _ in os.walk(root):
        dirnames[:] = sorted(
            [name for name in dirnames if name not in SKIP_DIRS],
            key=str.lower,
        )
        for dirname in dirnames:
            yield (Path(current_root) / dirname).relative_to(root).as_posix()


def score_candidate(query: str, rel_path: str) -> Candidate:
    query_raw = query.strip().lower()
    query_norm = normalize(query)
    query_tokens = [token for token in query_norm.split() if token]
    query_compact = compact(query)

    rel_lower = rel_path.lower()
    base_lower = Path(rel_path).name.lower()

    score = 0

    if query_raw and rel_lower == query_raw:
        score += 420
    if query_raw and base_lower == query_raw:
        score += 360
    if query_raw and query_raw in base_lower and base_lower != query_raw:
        score += 210
    if query_raw and query_raw in rel_lower and rel_lower != query_raw:
        score += 140

    token_hits = 0
    for token in query_tokens:
        if token in base_lower:
            score += 65
            token_hits += 1
        elif token in rel_lower:
            score += 35
            token_hits += 1

    if query_tokens and token_hits == len(query_tokens):
        score += 85

    if query_compact:
        base_ratio = difflib.SequenceMatcher(None, query_compact, compact(base_lower)).ratio()
        path_ratio = difflib.SequenceMatcher(None, query_compact, compact(rel_lower)).ratio()
        score += int(base_ratio * 130)
        score += int(path_ratio * 70)

    score -= rel_path.count("/") * 2
    score -= max(0, len(rel_path) - 90) // 8

    return Candidate(path=rel_path, score=max(score, 0))


def rank_candidates(
    query: str,
    root: Path,
    limit: int,
    min_score: int,
    kind: str,
) -> list[Candidate]:
    if kind == "file":
        source = iter_repo_files(root)
    elif kind == "dir":
        source = iter_repo_dirs(root)
    else:
        raise ValueError(f"Unsupported rank kind: {kind}")

    ranked = [score_candidate(query, rel_path) for rel_path in source]
    ranked = [candidate for candidate in ranked if candidate.score >= min_score]
    ranked.sort(key=lambda item: (-item.score, item.path))
    return ranked[:limit]


def is_text_file(path: Path) -> bool:
    sample = path.read_bytes()[:4096]
    if b"\x00" in sample:
        return False
    if not sample:
        return True

    printable = 0
    for byte in sample:
        if byte in (9, 10, 13) or byte >= 32:
            printable += 1
    return printable / len(sample) >= 0.85


def count_lines(path: Path) -> int:
    with path.open("r", encoding="utf-8", errors="replace") as handle:
        return sum(1 for _ in handle)


def resolve_root(root_value: str) -> Path:
    root = Path(root_value).expanduser().resolve()
    if not root.exists() or not root.is_dir():
        raise FileNotFoundError(f"Root directory does not exist: {root}")
    return root


def ensure_inside_root(path: Path, root: Path) -> Path:
    resolved = path.expanduser().resolve()
    try:
        resolved.relative_to(root)
    except ValueError as exc:
        raise ValueError(f"Path is outside root: {resolved}") from exc
    return resolved


def repo_relative(path: Path, root: Path) -> str:
    if path == root:
        return "."
    return path.relative_to(root).as_posix()


def choose_best_candidate(candidates: list[Candidate], min_margin: int, min_score: int) -> Candidate | None:
    if not candidates:
        return None

    top = candidates[0]
    second = candidates[1] if len(candidates) > 1 else None

    if top.score < min_score:
        return None
    if second and (top.score - second.score) < min_margin:
        return None

    return top


def print_ambiguous(candidates: list[Candidate], max_items: int = 5) -> None:
    print("ambiguous")
    for candidate in candidates[:max_items]:
        print(candidate.path)


def safe_listdir(path: Path, include_files: bool, include_hidden: bool) -> list[Path]:
    try:
        entries = [entry for entry in path.iterdir() if entry.name not in SKIP_DIRS]
    except PermissionError:
        return []
    if not include_hidden:
        entries = [entry for entry in entries if not entry.name.startswith(".")]
    if not include_files:
        entries = [entry for entry in entries if entry.is_dir()]
    entries.sort(key=lambda entry: (not entry.is_dir(), entry.name.lower()))
    return entries


def build_tree(
    root: Path,
    directory: Path,
    depth: int,
    max_entries: int,
    include_files: bool,
    include_hidden: bool,
) -> tuple[list[str], int, bool]:
    base = repo_relative(directory, root)
    root_label = "./" if base == "." else f"{base}/"
    lines = [root_label]

    if depth <= 0:
        return lines, 0, False

    count = 0
    truncated = False

    def walk(current: Path, prefix: str, current_depth: int) -> None:
        nonlocal count, truncated
        entries = safe_listdir(current, include_files=include_files, include_hidden=include_hidden)
        for idx, entry in enumerate(entries):
            if count >= max_entries:
                truncated = True
                return

            is_last = idx == len(entries) - 1
            connector = "└── " if is_last else "├── "
            label = repo_relative(entry, root)
            if entry.is_dir():
                label += "/"
            lines.append(f"{prefix}{connector}{label}")
            count += 1

            if entry.is_dir() and current_depth + 1 < depth:
                next_prefix = prefix + ("    " if is_last else "│   ")
                walk(entry, next_prefix, current_depth + 1)
                if truncated:
                    return

    walk(directory, "", 0)
    return lines, count, truncated


def resolve_file_target(args: argparse.Namespace, root: Path) -> tuple[Path | None, list[Candidate] | None]:
    if args.guess:
        candidates = rank_candidates(args.target, root, args.limit, args.min_score, kind="file")
        best = choose_best_candidate(candidates, min_margin=args.min_margin, min_score=args.min_score)
        if best is None:
            return None, candidates
        return root / best.path, candidates

    raw_target = Path(args.target)
    target_path = raw_target if raw_target.is_absolute() else root / raw_target
    target_path = ensure_inside_root(target_path, root)
    return target_path, None


def resolve_dir_target(args: argparse.Namespace, root: Path) -> tuple[Path | None, list[Candidate] | None]:
    if args.guess:
        candidates = rank_candidates(args.target, root, args.limit, args.min_score, kind="dir")
        best = choose_best_candidate(candidates, min_margin=args.min_margin, min_score=args.min_score)
        if best is None:
            return None, candidates
        return root / best.path, candidates

    raw_target = Path(args.target)
    target_path = raw_target if raw_target.is_absolute() else root / raw_target
    target_path = ensure_inside_root(target_path, root)
    return target_path, None


def command_find(args: argparse.Namespace) -> int:
    root = resolve_root(args.root)
    candidates = rank_candidates(args.query, root, args.limit, args.min_score, kind="file")

    if args.json:
        print(json.dumps({"query": args.query, "candidates": [asdict(item) for item in candidates]}, indent=2))
    else:
        for item in candidates:
            print(item.path)

    return 0 if candidates else 1


def command_overview(args: argparse.Namespace) -> int:
    root = resolve_root(args.root)

    try:
        target_path, candidates = resolve_file_target(args, root)
    except ValueError as exc:
        print(str(exc), file=sys.stderr)
        return 1

    if target_path is None:
        if args.json:
            print(
                json.dumps(
                    {
                        "target": args.target,
                        "status": "ambiguous_or_not_found",
                        "candidates": [asdict(item) for item in (candidates or [])],
                    },
                    indent=2,
                )
            )
        else:
            print_ambiguous(candidates or [])
        return 2

    if not target_path.exists() or not target_path.is_file():
        print(f"missing {repo_relative(target_path, root)}", file=sys.stderr)
        return 1

    rel_path = repo_relative(target_path, root)
    is_text = is_text_file(target_path)
    size_bytes = target_path.stat().st_size

    result = {
        "path": rel_path,
        "name": target_path.name,
        "ext": target_path.suffix or "",
        "type": "text" if is_text else "binary",
        "size": size_bytes,
    }
    if is_text:
        result["lines"] = count_lines(target_path)

    if args.json:
        print(json.dumps(result, indent=2))
    else:
        if "lines" in result:
            print(
                f"{result['path']} | {result['type']} | {result['size']}b | {result['lines']} lines"
            )
        else:
            print(f"{result['path']} | {result['type']} | {result['size']}b")

    return 0


def command_preview(args: argparse.Namespace) -> int:
    # Backward-compatible alias.
    return command_overview(args)


def command_tree(args: argparse.Namespace) -> int:
    root = resolve_root(args.root)

    try:
        target_path, candidates = resolve_dir_target(args, root)
    except ValueError as exc:
        print(str(exc), file=sys.stderr)
        return 1

    if target_path is None:
        if args.json:
            print(
                json.dumps(
                    {
                        "target": args.target,
                        "status": "ambiguous_or_not_found",
                        "candidates": [asdict(item) for item in (candidates or [])],
                    },
                    indent=2,
                )
            )
        else:
            print_ambiguous(candidates or [])
        return 2

    if not target_path.exists() or not target_path.is_dir():
        print(f"missing {repo_relative(target_path, root)}", file=sys.stderr)
        return 1

    lines, shown_entries, truncated = build_tree(
        root,
        target_path,
        args.depth,
        args.max_entries,
        include_files=args.files,
        include_hidden=args.all,
    )

    if args.json:
        print(
            json.dumps(
                {
                    "root": repo_relative(target_path, root),
                    "depth": args.depth,
                    "include_files": args.files,
                    "include_hidden": args.all,
                    "entries": shown_entries,
                    "truncated": truncated,
                    "tree": lines,
                },
                indent=2,
            )
        )
    else:
        for line in lines:
            print(line)
        if truncated:
            print(f"... truncated ({shown_entries}/{args.max_entries})")

    return 0


def add_common_target_args(parser: argparse.ArgumentParser, target_help: str) -> None:
    parser.add_argument("target", help=target_help)
    parser.add_argument("--root", default=".", help="Repository root (default: current directory).")
    parser.add_argument("--guess", action="store_true", help="Resolve target as fuzzy hint first.")
    parser.add_argument("--limit", type=int, default=8, help="Candidate limit while resolving --guess.")
    parser.add_argument("--min-score", type=int, default=60, help="Minimum score for --guess matching.")
    parser.add_argument(
        "--min-margin",
        type=int,
        default=18,
        help="Required score gap between top two candidates for --guess.",
    )
    parser.add_argument("--json", action="store_true", help="Output JSON instead of plain text.")


def build_parser() -> argparse.ArgumentParser:
    parser = argparse.ArgumentParser(
        description="Compact file/folder overviews with repository-relative paths.",
    )
    subparsers = parser.add_subparsers(dest="command", required=True)

    find_parser = subparsers.add_parser("find", help="List fuzzy-matched file paths.")
    find_parser.add_argument("query", help="Approximate file name or path hint.")
    find_parser.add_argument("--root", default=".", help="Repository root (default: current directory).")
    find_parser.add_argument("--limit", type=int, default=8, help="Maximum number of candidates.")
    find_parser.add_argument("--min-score", type=int, default=40, help="Minimum score threshold.")
    find_parser.add_argument("--json", action="store_true", help="Output JSON instead of plain text.")
    find_parser.set_defaults(func=command_find)

    overview_parser = subparsers.add_parser("overview", help="Show one-line file overview only.")
    add_common_target_args(overview_parser, "Exact relative file path or fuzzy file hint.")
    overview_parser.set_defaults(func=command_overview)

    preview_parser = subparsers.add_parser("preview", help="Alias for overview.")
    add_common_target_args(preview_parser, "Exact relative file path or fuzzy file hint.")
    preview_parser.set_defaults(func=command_preview)

    tree_parser = subparsers.add_parser("tree", help="Show compact folder tree.")
    tree_parser.add_argument(
        "target",
        nargs="?",
        default=".",
        help="Exact relative folder path or fuzzy folder hint (default: .).",
    )
    tree_parser.add_argument("--root", default=".", help="Repository root (default: current directory).")
    tree_parser.add_argument("--depth", type=int, default=2, help="Tree depth under selected folder.")
    tree_parser.add_argument("--max-entries", type=int, default=60, help="Maximum printed entries.")
    tree_parser.add_argument("--files", action="store_true", help="Include files in tree output (default: folders only).")
    tree_parser.add_argument("--all", action="store_true", help="Include hidden entries (default: hidden entries omitted).")
    tree_parser.add_argument("--guess", action="store_true", help="Resolve target as fuzzy folder hint first.")
    tree_parser.add_argument("--limit", type=int, default=8, help="Candidate limit while resolving --guess.")
    tree_parser.add_argument("--min-score", type=int, default=60, help="Minimum score for --guess matching.")
    tree_parser.add_argument(
        "--min-margin",
        type=int,
        default=18,
        help="Required score gap between top two candidates for --guess.",
    )
    tree_parser.add_argument("--json", action="store_true", help="Output JSON instead of plain text.")
    tree_parser.set_defaults(func=command_tree)

    return parser


def main() -> int:
    parser = build_parser()
    args = parser.parse_args()

    try:
        return args.func(args)
    except FileNotFoundError as exc:
        print(str(exc), file=sys.stderr)
        return 1


if __name__ == "__main__":
    sys.exit(main())
