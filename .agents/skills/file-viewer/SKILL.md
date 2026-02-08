---
name: file-viewer
description: Create compact file and folder overviews from approximate user path hints. Use when Codex should show only short repository-relative path information (no long previews), including optional folder trees for quick orientation before continuing with other skills.
---

# File Viewer

## Goal

Show a short overview only. Keep output minimal and repository-relative.

## Workflow

1. Find candidate file paths:
   - `python3 .agents/skills/file-viewer/scripts/file_viewer.py find "<hint>" --root . --limit 8`
2. Create one-line file overview (no content preview):
   - `python3 .agents/skills/file-viewer/scripts/file_viewer.py overview "<relative/path>" --root .`
   - Fuzzy resolution: `python3 .agents/skills/file-viewer/scripts/file_viewer.py overview "<hint>" --guess --root .`
3. Show folder tree when requested:
   - `python3 .agents/skills/file-viewer/scripts/file_viewer.py tree "<relative/folder>" --root . --depth 2`
   - Fuzzy resolution: `python3 .agents/skills/file-viewer/scripts/file_viewer.py tree "<hint>" --guess --root .`
   - Add files only when needed: `--files`
   - Include hidden entries only when needed: `--all`

## Output Rules

- Output only compact path-based summaries.
- Never print full file contents.
- Prefer relative paths everywhere.
- Tree output omits hidden entries by default.
- Use `ambiguous` plus short candidate list when guessing is not confident.
- Use `references/output-template.md` for the default compact format.

## Script

Use `scripts/file_viewer.py` for deterministic matching and rendering.

- `find`: List fuzzy-matched files.
- `overview`: Print one-line file overview only.
- `preview`: Alias for `overview`.
- `tree`: Print compact folder tree (folders only by default).
- `--json`: Return machine-friendly output for chained workflows.
