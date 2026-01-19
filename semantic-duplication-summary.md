# Semantic Duplication Analysis Summary

## Executive Summary
The semantic analysis covers Itemmanager renewal, settings, and update flows with emphasis on duplicated period, pricing, and tax logic. The extension’s architecture centers on utilities and renewal logic for pricing/period handling, plus admin forms for configuration and renewal UI validation, with sources spanning utility helpers, renewal logic classes, update pages, and settings forms.

## Files Analyzed (IDs → File Names)
- SRC-UTIL → CRM/Itemmanager/Util.php
- SRC-RENEW-FORM → CRM/Itemmanager/Form/RenewItemperiods.php
- SRC-ITEM-SETTING-FORM → CRM/Itemmanager/Form/ItemmanagerSetting.php
- SRC-UPDATE-PAGE → CRM/Itemmanager/Page/UpdateItems.php
- SRC-RENEW-BASE → CRM/Itemmanager/Logic/RenewalPaymentPlanBase.php
- SRC-RENEW-MULTI → CRM/Itemmanager/Logic/RenewalMultipleInstallmentPlan.php
- SRC-RENEW-SINGLE → CRM/Itemmanager/Logic/RenewalSingleInstallmentPlan.php
- SRC-README → README.md

## Cross-File Duplication Analysis
> Cross-file duplication only (unique relations by `related_business_item_ids` + `source_id` + file), excluding same-file overlaps.

### Period Unit Mapping (GRP-001-PeriodUnitMapping)
- **Files & IDs:**
  - SRC-UTIL: BI-001 (choices)
  - SRC-RENEW-BASE: BI-002 (membership extension)
- **Consistency:** Identical intent and logic (same D/W/M/Y mapping and week correction).
- **Risk:** Low (drift would affect period date calculation).
- **Consolidation Strategy:** Centralize the mapping in a shared helper method used by both flows.

### Effective Period Count Overrides (GRP-002-PeriodCountOverrides)
- **Files & IDs:**
  - SRC-UPDATE-PAGE: BI-003 (update preview), BI-004 (update apply)
  - SRC-RENEW-BASE: BI-005 (renewal line prototype)
- **Consistency:** Similar/duplicated logic (exception periods and reverse/invalid forcing to 1).
- **Risk:** Medium (drift can misalign pricing/dates).
- **Consolidation Strategy:** Extract a shared `effectivePeriods` utility and reuse it across update and renewal flows.

### Unit Price per Period (GRP-003-UnitPricePerPeriod)
- **Files & IDs:**
  - SRC-UTIL: BI-006 (choice display price)
  - SRC-UPDATE-PAGE: BI-007 (update preview/apply)
  - SRC-RENEW-BASE: BI-008 (renewal line prototype)
- **Consistency:** Similar but divergent reverse handling (explicit vs implicit).
- **Risk:** Medium (price display vs actual charge drift if reverse logic changes).
- **Consolidation Strategy:** Canonical unit price helper with amount, periods, reverse flag; apply consistently.

### Tax Amount Calculation (GRP-004-TaxAmountCalculation)
- **Files & IDs:**
  - SRC-UPDATE-PAGE: BI-009 (update items)
  - SRC-RENEW-BASE: BI-010 (renewal prototype) + BI-011 (renewal line items using tax utilities)
- **Consistency:** Divergent (raw multiplication vs utility with rounding).
- **Risk:** Medium (rounding inconsistencies can lead to mismatched totals).
- **Consolidation Strategy:** Route all tax computations through the same utility (Contribution Utils + MoneyUtilities).

### Ignore Items for Renewal (GRP-005-IgnoreItems)
- **Files & IDs:**
  - SRC-UTIL: BI-012 (successor set) excludes ignore + novitiate
  - SRC-RENEW-FORM: BI-013 (renewal UI) excludes ignore only
- **Consistency:** Divergent (novitiate handling differs).
- **Risk:** Low (may expose novitiate items in renewal UI).
- **Consolidation Strategy:** Shared eligibility predicate for ignore/novitiate/etc.

## Business Logic Inventory (Unique Rules)
| Business Intent | Files Where Implemented (IDs) | Consistency Status |
| --- | --- | --- |
| Period type mapping for date interval units and week correction | SRC-UTIL (BI-001), SRC-RENEW-BASE (BI-002) | Consistent duplication |
| Effective period count override via exceptions/reverse/invalid | SRC-UPDATE-PAGE (BI-003, BI-004), SRC-RENEW-BASE (BI-005) | Consistent duplication |
| Unit price per period, reverse handling | SRC-UTIL (BI-006), SRC-UPDATE-PAGE (BI-007), SRC-RENEW-BASE (BI-008) | Divergent (reverse handling) |
| Tax amount calculation from unit price & rate | SRC-UPDATE-PAGE (BI-009), SRC-RENEW-BASE (BI-010, BI-011) | Divergent (rounding/util usage) |
| Ignore items in renewal/successor flows | SRC-UTIL (BI-012), SRC-RENEW-FORM (BI-013) | Divergent (novitiate handling) |
| Price set consistency per membership | SRC-RENEW-FORM (BI-014) | Single implementation |
| Period count consistency per membership | SRC-RENEW-FORM (BI-015) | Single implementation |
| Successor item selection constraints | SRC-ITEM-SETTING-FORM (BI-016) | Single implementation |
| Successor period selection | SRC-ITEM-SETTING-FORM (BI-017) | Single implementation |
| Itemmanager period entity fields | SRC-ITEM-SETTING-FORM (BI-018) | Single implementation |
| Itemmanager setting entity fields | SRC-ITEM-SETTING-FORM (BI-019) | Single implementation |
| Renewal plan selection by period count | SRC-RENEW-FORM (BI-020) | Single implementation |

## Refactoring Recommendations (Prioritized)
1. **Consolidate effective period calculation** into a shared utility (`effectivePeriods`) used by update preview/apply and renewal line prototypes. This addresses a core, repeated calculation that feeds both pricing and date logic. Risk: Medium due to potential drift across flows.
2. **Standardize tax calculation** by routing all tax computations through the same CiviCRM utility with currency rounding to avoid inconsistent totals. Risk: Medium, directly affecting financial correctness.
3. **Centralize unit-price-per-period computation** with an explicit reverse flag to align choice display and actual renewal charges. Risk: Medium, impacts customer-visible pricing accuracy.
4. **Unify ignore/novitiate eligibility checks** for successor selection and renewal UI to prevent conflicting item availability. Risk: Low but improves predictability in UI flows.
5. **Centralize period unit mapping** (D/W/M/Y plus week correction) to reduce maintenance overhead. Risk: Low but simplifies long-term updates.
