---
name: civicrm-api-migration
description: Safely migrate CiviCRM core data access toward API usage. Use when tasks involve (1) converting raw SQL access to the best available API endpoint, (2) upgrading API3 calls to API4 while preserving runnable code, or (3) classifying civicrm-core entities as API4, API3-only, or raw-SQL-only to plan migrations.
---

# CiviCRM API Migration

## Goal

Provide deterministic tooling for three migration tasks:
- classify entity access coverage (`API4`, `API3`, `RAW_SQL_ONLY`)
- suggest API alternatives for raw SQL statements
- convert API3 calls into API4 migration candidates

## Workflow

1. Resolve the target `civicrm-core` path.
2. Ensure report artifacts are placed in `doc/`.
3. Generate an entity access report when migration coverage is requested.
4. Run SQL-to-API hinting when SQL snippets should be migrated.
5. Run API3-to-API4 conversion when PHP code uses `civicrm_api3(...)`.
6. Return output plus explicit safety limits for manual follow-up.

## Commands

Generate entity access report (TSV, default target `doc/civicrm-core-entity-access.tsv`):
```bash
python3 .agents/skills/civicrm-api-migration/scripts/build_entity_access_report.py \
  --core /path/to/civicrm-core \
  --format tsv
```

Generate JSON report (default target `doc/civicrm-core-entity-access.json`):
```bash
python3 .agents/skills/civicrm-api-migration/scripts/build_entity_access_report.py \
  --core /path/to/civicrm-core \
  --format json
```

Print report to stdout instead of `doc/`:
```bash
python3 .agents/skills/civicrm-api-migration/scripts/build_entity_access_report.py \
  --core /path/to/civicrm-core \
  --format tsv \
  --stdout
```

Suggest API access from SQL:
```bash
python3 .agents/skills/civicrm-api-migration/scripts/sql_to_api_hint.py \
  --core /path/to/civicrm-core \
  --sql "SELECT * FROM civicrm_contact WHERE id = 1" \
  --format text
```

Upgrade API3 calls to API4 candidates (safe mode with API4 catalog):
```bash
python3 .agents/skills/civicrm-api-migration/scripts/upgrade_api3_to_api4.py \
  --core /path/to/civicrm-core \
  --input /path/to/file.php \
  --in-place
```

## Rules

- Derive canonical entities from `schema/**/*.entityType.php`.
- Apply classification precedence per schema entity: `API4` > `API3` > `RAW_SQL_ONLY`.
- Normalize API3 naming mismatches: `Acl` -> `ACL`, `AclRole` -> `ACLEntityRole`, `Im` -> `IM`, `Pcp` -> `PCP`.
- Treat SQL-to-API output as migration guidance, not a guaranteed 1:1 semantic rewrite.
- If an entity has no API endpoint, keep raw SQL unchanged.
- Convert only explicitly supported API3 actions.
- Convert API3 calls only for entities confirmed in API4 catalog (`--core`).
- Leave unsupported or unrecognized API3 calls unchanged.
- Ensure generated migration output stays syntactically runnable PHP code.
- Use `references/method.md` when explaining methodology and edge cases.
