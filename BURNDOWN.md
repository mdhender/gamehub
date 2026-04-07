# BURNDOWN

Colony template refactor — inventory sections, metadata, and column cleanup.

**Date:** 2026-04-06

**Notes:**
- Tasks are ordered by **dependencies**, not severity.
- Tasks at the same level can be parallelized unless they touch the same file(s).
- Do not check a task off until its acceptance criteria pass.
- Run `vendor/bin/pint --dirty --format agent` after modifying any PHP files.
- Run `bun run build` after modifying frontend files to regenerate Wayfinder actions.

---

## Problem Statement

The colony template JSON has been restructured to be easier for GMs. Three changes:

1. **New metadata fields** — `sol`, `birth-rate-pct`, `death-rate-pct` are now on each template. `ColonyTemplate` lacks columns for these (though `Colony` already has them).
2. **Four inventory sections** — inventory is now grouped into `super-structure`, `structure`, `operational`, and `cargo` instead of the old `operational`/`stored` split. The section determines storage semantics (cargo items use half volume; everything else uses full volume).
3. **Column simplification** — the old `quantity_assembled`/`quantity_disassembled` columns are replaced by a single `quantity` column. The `inventory_section` column now carries the semantic meaning those two columns encoded.

The `CSHP` (ship) kind already exists in `ColonyKind` but has never been used in templates. The new JSON includes a CSHP template with empty population.

## Reference

- Updated JSON: `sample-data/beta/colony-template.json`
- Inventory semantics: `site/docs/content/developers/reference/ship-structures.md` (Enclosure Report table)
- Plan doc: `docs/plan-import-colony-template.md` (PR 1 section only; PR 2 production is out of scope)

---

## Tasks

### COL-01 — Create InventorySection enum and colony_templates metadata migration

**Effort:** S
**Dependencies:** None

**Problem:** `ColonyTemplate` has no columns for `sol`, `birth_rate`, or `death_rate`. There is no enum representing the four inventory sections.

**Files to create:**
- `app/Enums/InventorySection.php` — string-backed enum with cases: `SuperStructure = 'super_structure'`, `Structure = 'structure'`, `Operational = 'operational'`, `Cargo = 'cargo'`
- Migration: add `sol` (real, default 0.0), `birth_rate` (real, default 0.0), `death_rate` (real, default 0.0) to `colony_templates` table

**Conventions:** Check existing enums in `app/Enums/` for style (e.g., `ColonyKind.php`). Use `php artisan make:migration` and `php artisan make:enum` (or create manually matching sibling style).

**Acceptance:**
- [x] `InventorySection` enum exists with exactly 4 cases and correct string values
- [x] Migration adds 3 float columns with defaults to `colony_templates`
- [x] `php artisan test --compact --filter=ColonyTemplateModelTest`
- [x] `vendor/bin/pint --dirty --format agent`

---

### COL-02 — Migration: restructure inventory columns on all 3 tables

**Effort:** S
**Dependencies:** COL-01

**Problem:** `colony_template_items`, `colony_inventory`, and `turn_report_colony_inventory` all use `quantity_assembled`/`quantity_disassembled`. These need to be replaced with `quantity` (integer) + `inventory_section` (string, default `'operational'`).

**Files to create:**
- One migration that alters all 3 tables:
  1. Add `inventory_section` (string, default `'operational'`) to each table
  2. Add `quantity` (integer, default 0) to each table
  3. Backfill: `quantity = quantity_assembled + quantity_disassembled` on all existing rows
  4. Drop `quantity_assembled` and `quantity_disassembled` from each table

**Important:** The test suite uses SQLite with transactions. `Schema::disableForeignKeyConstraints()` is a no-op inside a transaction — if needed, use `DB::statement('PRAGMA defer_foreign_keys = ON')` instead. However, since we're only adding/dropping columns (no FK changes), this shouldn't be an issue.

**Acceptance:**
- [x] All 3 tables have `inventory_section` and `quantity` columns
- [x] All 3 tables no longer have `quantity_assembled` or `quantity_disassembled` columns
- [x] `php artisan migrate:fresh` succeeds
- [x] `vendor/bin/pint --dirty --format agent`

---

### COL-03 — Update models and casts for new columns

**Effort:** S
**Dependencies:** COL-02

**Problem:** Four models reference the old columns and lack the new ones.

**Files to modify:**
- `app/Models/ColonyTemplate.php` — add `sol`, `birth_rate`, `death_rate` to `$fillable`; add casts: `sol` → `float`, `birth_rate` → `float`, `death_rate` → `float`
- `app/Models/ColonyTemplateItem.php` — replace `quantity_assembled`, `quantity_disassembled` with `quantity` in `$fillable`; add `inventory_section` to `$fillable`; add cast: `inventory_section` → `InventorySection`
- `app/Models/ColonyInventory.php` — same changes as `ColonyTemplateItem`
- `app/Models/TurnReportColonyInventory.php` — same changes as `ColonyTemplateItem`

**Conventions:** Check `Colony.php` for cast style — it already casts `sol`, `birth_rate`, `death_rate` to float. Match that pattern.

**Acceptance:**
- [x] All 4 models compile without errors (`php artisan tinker --execute 'new \App\Models\ColonyTemplate;'`)
- [x] `ColonyTemplateItem`, `ColonyInventory`, `TurnReportColonyInventory` all cast `inventory_section` to `InventorySection`
- [x] No references to `quantity_assembled` or `quantity_disassembled` remain in any of the 4 model files
- [x] `vendor/bin/pint --dirty --format agent`

---

### COL-04 — Update factories for new columns

**Effort:** S
**Dependencies:** COL-03

**Problem:** Four factories generate `quantity_assembled`/`quantity_disassembled`. The `GameFactory` uses old `operational`/`stored` inventory keys.

**Files to modify:**
- `database/factories/ColonyTemplateFactory.php` — add `sol`, `birth_rate`, `death_rate` with sensible defaults (e.g., `1.0`, `0.0625`, `0.0625`)
- `database/factories/ColonyTemplateItemFactory.php` — replace `quantity_assembled`/`quantity_disassembled` with `quantity`; add `inventory_section` defaulting to `InventorySection::Operational`
- `database/factories/ColonyInventoryFactory.php` — same: replace qty columns with `quantity`; add `inventory_section` defaulting to `InventorySection::Operational`
- `database/factories/TurnReportColonyInventoryFactory.php` — same pattern
- `database/factories/GameFactory.php` — update the `createWithFullGeneration` or similar method that builds colony template items: replace `quantity_assembled`/`quantity_disassembled` with `quantity`; replace `operational`/`stored` inventory key references with section-based logic using `inventory_section`

**Acceptance:**
- [x] No references to `quantity_assembled`, `quantity_disassembled`, or old `stored` key remain in any factory
- [x] Each factory produces valid model instances (verify with `php artisan tinker --execute 'App\Models\ColonyTemplateItem::factory()->make()->toArray();'`)
- [x] `vendor/bin/pint --dirty --format agent`

---

### COL-05 — Update ImportColonyTemplates action

**Effort:** M
**Dependencies:** COL-03

**Problem:** `ImportColonyTemplates` reads the old `operational`/`stored` inventory keys and writes `quantity_assembled`/`quantity_disassembled`. It doesn't read `sol`, `birth-rate-pct`, or `death-rate-pct`. It doesn't handle empty population (CSHP).

**Files to modify:**
- `app/Actions/GameGeneration/ImportColonyTemplates.php`:
  1. Read `sol`, `birth-rate-pct`, `death-rate-pct` from each template object and store as `sol`, `birth_rate`, `death_rate` on the `ColonyTemplate`
  2. Make population import conditional — if `population` is an empty array, skip population creation (CSHP has no population)
  3. Replace the inventory import logic: iterate over the 4 section keys (`super-structure`, `structure`, `operational`, `cargo`), map each JSON key to the `InventorySection` enum (`super-structure` → `SuperStructure`, etc.), and create `ColonyTemplateItem` records with `inventory_section` and `quantity` (not the old `quantity_assembled`/`quantity_disassembled`)
  4. Remove any references to old `stored` key or `quantity_assembled`/`quantity_disassembled`

**Key mapping (JSON key → enum case):**
- `super-structure` → `InventorySection::SuperStructure`
- `structure` → `InventorySection::Structure`
- `operational` → `InventorySection::Operational`
- `cargo` → `InventorySection::Cargo`

**Acceptance:**
- [x] Importing `sample-data/beta/colony-template.json` creates 3 `ColonyTemplate` records (COPN, CORB, CSHP)
- [x] COPN template has `sol=1.0`, `birth_rate=0.0625`, `death_rate=0.0625`
- [x] CSHP template has 0 population records
- [x] COPN template items span all 4 inventory sections
- [x] All template items use `quantity` (no `quantity_assembled`/`quantity_disassembled`)
- [x] No references to `stored`, `quantity_assembled`, or `quantity_disassembled` remain in the file
- [x] `vendor/bin/pint --dirty --format agent`

---

### COL-06 — Update UploadColonyTemplateRequest validation

**Effort:** M
**Dependencies:** COL-01

**Problem:** The request validates `operational` and `stored` inventory keys. It needs to validate the 4 new section keys and accept empty population for CSHP.

**Files to modify:**
- `app/Http/Requests/UploadColonyTemplateRequest.php`:
  1. Accept `CSHP` in kind validation (verify it's already accepted — `ColonyKind` has `Ship = 'CSHP'`)
  2. Validate `sol`, `birth-rate-pct`, `death-rate-pct` as required numerics
  3. Replace `inventory.operational` and `inventory.stored` validation with: `inventory.super-structure`, `inventory.structure`, `inventory.operational`, `inventory.cargo` — each should be an optional array of items; reject unknown keys under `inventory`
  4. Require at least one item across all 4 sections combined
  5. Allow `population` to be an empty array (CSHP has `"population": []`)
  6. Remove all references to `stored`

**Acceptance:**
- [x] CSHP template with empty population and inventory-only passes validation
- [x] Template missing `sol` or `birth-rate-pct` or `death-rate-pct` fails validation
- [x] Template with unknown inventory key (e.g., `inventory.weapons`) fails validation
- [x] Template with no items in any inventory section fails validation
- [x] Existing COPN/CORB templates still pass validation
- [x] `vendor/bin/pint --dirty --format agent`

---

### COL-07 — Update EmpireCreator service

**Effort:** S
**Dependencies:** COL-03

**Problem:** `EmpireCreator` copies template items to `ColonyInventory` using `quantity_assembled`/`quantity_disassembled`. It doesn't copy `sol`, `birth_rate`, `death_rate` from template to `Colony`. It doesn't copy `inventory_section`.

**Files to modify:**
- `app/Services/EmpireCreator.php`:
  1. In `createColonies()` (or wherever colonies are created from templates): copy `sol`, `birth_rate`, `death_rate` from `ColonyTemplate` to the `Colony` record
  2. In the inventory copy logic: replace `quantity_assembled`/`quantity_disassembled` mapping with `quantity` and `inventory_section`
  3. Handle templates with empty population (CSHP) — skip `ColonyPopulation::insert` if the template has no population records

**Acceptance:**
- [x] Created colonies have `sol`, `birth_rate`, `death_rate` matching their template
- [x] Created `ColonyInventory` records have `inventory_section` and `quantity` (not old columns)
- [x] CSHP-based colonies have 0 `ColonyPopulation` records
- [x] No references to `quantity_assembled` or `quantity_disassembled` remain in the file
- [x] `vendor/bin/pint --dirty --format agent`

---

### COL-08 — Update SetupReportGenerator and TurnReportJsonExporter

**Effort:** S
**Dependencies:** COL-03

**Problem:** `SetupReportGenerator` copies `quantity_assembled`/`quantity_disassembled` from `ColonyInventory` to `TurnReportColonyInventory`. `TurnReportJsonExporter` exports those old columns.

**Files to modify:**
- `app/Services/SetupReportGenerator.php` — update the inventory copy to use `quantity` and `inventory_section` instead of `quantity_assembled`/`quantity_disassembled`
- `app/Support/TurnReports/TurnReportJsonExporter.php` — export `quantity` and `inventory_section` instead of the old columns

**Acceptance:**
- [x] `TurnReportColonyInventory` records created by `SetupReportGenerator` have `quantity` and `inventory_section`
- [x] JSON export includes `quantity` and `inventory_section`, not `quantity_assembled`/`quantity_disassembled`
- [x] No references to `quantity_assembled` or `quantity_disassembled` remain in either file
- [x] `vendor/bin/pint --dirty --format agent`

---

### COL-09 — Update model and migration tests

**Effort:** M
**Dependencies:** COL-04

**Problem:** Tests for `ColonyTemplateItem`, `ColonyInventory`, `TurnReportColonyInventory`, and `ColonyTemplate` reference the old columns.

**Files to modify:**
- `tests/Feature/Models/ColonyTemplateItemModelTest.php` — replace `quantity_assembled`/`quantity_disassembled` assertions with `quantity` + `inventory_section`
- `tests/Feature/Models/ColonyInventoryModelTest.php` — same
- `tests/Feature/Models/ColonyTemplateModelTest.php` — add assertions for `sol`, `birth_rate`, `death_rate`; update any template item assertions
- `tests/Feature/Database/Migrations/RebuildColonyInventoryAndTemplatesMigrationTest.php` — this tests the old rebuild migration; update assertions to match new column structure (the old migration still runs, but the new migration follows it and changes the columns)

**Acceptance:**
- [x] All model tests pass: `php artisan test --compact tests/Feature/Models/ColonyTemplateModelTest.php tests/Feature/Models/ColonyTemplateItemModelTest.php tests/Feature/Models/ColonyInventoryModelTest.php`
- [x] Migration test passes: `php artisan test --compact tests/Feature/Database/Migrations/`
- [x] `vendor/bin/pint --dirty --format agent`

---

### COL-10 — Update importer and validation tests

**Effort:** M
**Dependencies:** COL-05, COL-06

**Problem:** Tests for `ImportColonyTemplates` and `UploadColonyTemplateRequest` use old inventory structure and don't test new metadata or CSHP.

**Files to modify:**
- Tests for `ImportColonyTemplates` (find with `grep -r ImportColonyTemplates tests/`):
  - Update test JSON fixtures to use 4-section inventory format
  - Add test: importing creates templates with `sol`, `birth_rate`, `death_rate`
  - Add test: CSHP template with empty population imports successfully
  - Add test: items have correct `inventory_section` values
  - Remove assertions on `quantity_assembled`/`quantity_disassembled`
- `tests/Feature/UploadColonyTemplateValidationTest.php`:
  - Update test fixtures from `operational`/`stored` to 4-section format
  - Add tests for `sol`, `birth-rate-pct`, `death-rate-pct` validation
  - Add test: CSHP with empty population passes
  - Add test: unknown inventory section key fails
  - Remove `stored` references

**Acceptance:**
- [x] `php artisan test --compact --filter=ImportColonyTemplate`
- [x] `php artisan test --compact --filter=UploadColonyTemplate`
- [x] `vendor/bin/pint --dirty --format agent`

---

### COL-11 — Update EmpireCreator, report, and generation tests

**Effort:** M
**Dependencies:** COL-07, COL-08

**Problem:** Tests for `EmpireCreator`, `SetupReportGenerator`, turn report models, and game generation reference old columns.

**Files to modify:**
- `tests/Feature/EmpireCreatorTest.php` — replace `quantity_assembled`/`quantity_disassembled` with `quantity` + `inventory_section`; add assertions for `sol`, `birth_rate`, `death_rate` on created colonies; add test for CSHP with empty population
- `tests/Feature/Services/SetupReportGeneratorTest.php` — replace old column references with `quantity` + `inventory_section`
- `tests/Feature/Reports/TurnReportSchemaTest.php` — update schema assertions for new columns
- `tests/Feature/Reports/TurnReportModelTest.php` — update column references
- `tests/Feature/GameGenerationReportPropsTest.php` — update column references
- `tests/Feature/GameGenerationControllerEmpireTest.php` — update column references
- `tests/Feature/GameShowSetupReportTest.php` — update column references
- `tests/Feature/GameGenerationControllerTest.php` — update any colony template fixtures

**Acceptance:**
- [x] `php artisan test --compact tests/Feature/EmpireCreatorTest.php`
- [x] `php artisan test --compact tests/Feature/Services/SetupReportGeneratorTest.php`
- [x] `php artisan test --compact tests/Feature/Reports/`
- [x] `php artisan test --compact tests/Feature/GameGenerationReportPropsTest.php tests/Feature/GameGenerationControllerEmpireTest.php tests/Feature/GameShowSetupReportTest.php tests/Feature/GameGenerationControllerTest.php`
- [x] `vendor/bin/pint --dirty --format agent`

---

### COL-12 — Update documentation and sample data

**Effort:** S
**Dependencies:** COL-08

**Problem:** Docs and sample data reference `quantity_assembled`/`quantity_disassembled` and old inventory structure.

**Files to modify:**
- `docs/SETUP_REPORT.md` — replace `quantity_assembled`/`quantity_disassembled` references with `quantity` + `inventory_section`
- `docs/GENERATORS.md` — update example JSON to show new column names
- `site/docs/content/developers/reference/terminology.md` — update or remove `quantity_assembled`/`quantity_disassembled` definitions; add `inventory_section` and `quantity`
- `site/docs/content/developers/explanation/assembly-required-units.md` — update references to old column semantics
- `site/blog/content/docs-unit-codes-and-inventory-model.md` — update column references
- `sample-data/beta/5/report-4-turn-0-empire-5.json` — update inventory entries to use `quantity` + `inventory_section`
- `sample-data/beta/5/report-4-turn-0-empire-5.csv` — update header and data rows

**Acceptance:**
- [ ] `grep -r 'quantity_assembled\|quantity_disassembled' docs/ site/ sample-data/` returns no matches
- [ ] Documentation accurately describes the new 4-section inventory model

---

## Execution Order

Tasks should be completed in this order. Tasks at the same indentation level can be parallelized.

```
COL-01  (InventorySection enum + colony_templates metadata migration)

COL-02  (restructure inventory columns on 3 tables)           ← after COL-01

COL-03  (update models and casts)                              ← after COL-02

  COL-04  (update factories)                                   ← parallel
  COL-05  (update ImportColonyTemplates)                       ← parallel
  COL-06  (update UploadColonyTemplateRequest)                 ← parallel (only needs COL-01)
  COL-07  (update EmpireCreator)                               ← parallel
  COL-08  (update SetupReportGenerator + exporter)             ← parallel

COL-09  (model + migration tests)                              ← after COL-04

COL-10  (importer + validation tests)                          ← after COL-05, COL-06

COL-11  (EmpireCreator, report, generation tests)              ← after COL-07, COL-08

COL-12  (documentation + sample data)                          ← after COL-08
```
