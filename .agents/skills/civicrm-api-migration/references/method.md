# Method Notes

## Classification Basis

- Source of truth for core entities: `schema/**/*.entityType.php`
- API4 coverage signal: `Civi/Api4/*.php` (excluding infrastructure files)
- API3 coverage signal: `api/v3/*.php` (excluding helper files)

## Precedence

For each schema entity:
1. classify as `API4` when API4 class exists
2. else classify as `API3` when API3 endpoint exists
3. else classify as `RAW_SQL_ONLY`

This keeps classes mutually exclusive for reporting.

## SQL to API Limits

- SQL can encode joins, computed fields, and DB-specific behavior that do not map 1:1 to API calls.
- Suggestions map table/entity/action direction and require manual refinement for `WHERE`, joins, and permissions.
- If access class is `RAW_SQL_ONLY`, keep raw SQL unchanged.

## API3 to API4 Safety Contract

- Convert only known and explicitly supported API3 actions.
- Convert only entities confirmed in API4 catalog from `--core`.
- Keep unsupported actions and non-API4 entities unchanged.
- Preserve syntactically runnable PHP output at all times.
- Use converted output as a starter patch and review query semantics, permissions, and return handling.
