# BURNDOWN

Colony template refactor — production groups and documentation.

**Date:** 2026-04-07

**Notes:**
- Tasks are ordered by **dependencies**, not severity.
- Tasks at the same level can be parallelized unless they touch the same file(s).
- Do not check a task off until its acceptance criteria pass.
- Run `vendor/bin/pint --dirty --format agent` after modifying any PHP files.
- Run `bun run build` after modifying frontend files to regenerate Wayfinder actions.
- COL-01 through COL-12 are complete; continue numbering at COL-13.
- PR 2, PR 3, and PR 4 are intentionally sequential because they all modify `ImportColonyTemplates`, `UploadColonyTemplateRequest`, `EmpireCreator`, `ColonyTemplate`, and `Colony`.
- Use `Game::colonyTemplates()` for bulk import/copy work. Do not rely on any `colonyTemplate()` helper that only returns the first template.
- The test suite uses SQLite inside transactions. If a migration or migration test needs deferred FK handling, use `DB::statement('PRAGMA defer_foreign_keys = ON')` instead of `Schema::disableForeignKeyConstraints()`.

---

## Problem Statement

PR 1 is complete. Remaining work falls into four dependency-ordered PRs:

1. **Factory groups** — add template/live schema, models, factories, importer support, validation, empire creation, and tests for factory production groups.
2. **Farm groups** — add template/live schema, models, factories, importer support, validation, empire creation, and tests for farm harvest groups.
3. **Mine groups** — add template/live schema, models, factories, importer support, validation, empire creation, and tests for mine groups with deposit assignment.
4. **Documentation** — add a Diataxis-style reference doc plus three explanation docs covering factories, farms, and mines.

Key rules from the reconciled plan/sample data:
- Factory `orders` and `work-in-progress` are **group-level**, not per-unit.
- Factory `units` is just the factory inventory in the group.
- Farm units use `stage` (`1`–`4`) per unit entry; there is no farm WIP pipeline. The GM may omit stages from the JSON; the importer fills missing stages with quantity `0`.
- GMs are encouraged to stagger farm stages and pre-fill factory WIP pipelines in the template for sensible game-start conditions, but the system does not enforce this.
- Mine groups are absent from the sample JSON; import must treat missing `production.mines` as normal. EmpireCreator is responsible for creating live mine groups and assigning deposits based on the homeworld planet.
- Template mine `deposit_id` is nullable; live colony mine `deposit_id` is required.
- Any template may have an empty `production: []` section; importer and validator must skip that gracefully.
- Factory WIP stores quarter (`1`, `2`, `3`), unit, tech level, and quantity.
- Live colony factory groups add `input_remainder_mets` / `input_remainder_nmts` with default `0`.

## Reference

- Updated JSON: `sample-data/beta/colony-template.json`
- Plan doc: `docs/plan-import-colony-template.md` (PR 2 through PR 5 sections)
- Colony report example: `sample-data/beta/open-colony-report.txt`

---

## Tasks

### COL-13 — Create factory group schema on templates and live colonies

**Effort:** M
**Dependencies:** COL-12

**Problem:** There is no schema for factory groups, factory inventory, or factory WIP on either colony templates or live colonies. Reimport also requires cascade deletes so old factory rows do not survive when templates are replaced.

**Files to create:**
- Migration: create 6 tables (`colony_template_factory_groups`, `colony_template_factory_units`, `colony_template_factory_wip`, `colony_factory_groups`, `colony_factory_units`, `colony_factory_wip`)

**Schema requirements:**
- `colony_template_factory_groups`
  - `id`
  - `colony_template_id` FK cascade
  - `group_number` integer
  - `orders_unit` string
  - `orders_tech_level` integer, default `0`
  - `pending_orders_unit` string, nullable
  - `pending_orders_tech_level` integer, nullable
  - unique: `(colony_template_id, group_number)`
- `colony_template_factory_units`
  - `id`
  - `colony_template_factory_group_id` FK cascade
  - `unit` string
  - `tech_level` integer
  - `quantity` integer
- `colony_template_factory_wip`
  - `id`
  - `colony_template_factory_group_id` FK cascade
  - `quarter` integer
  - `unit` string
  - `tech_level` integer, default `0`
  - `quantity` integer
  - unique: `(colony_template_factory_group_id, quarter)`
- `colony_factory_groups`
  - `id`
  - `colony_id` FK cascade
  - same order columns as template groups
  - `input_remainder_mets` real, default `0`
  - `input_remainder_nmts` real, default `0`
  - unique: `(colony_id, group_number)`
- `colony_factory_units`
  - `id`
  - `colony_factory_group_id` FK cascade
  - `unit`, `tech_level`, `quantity`
- `colony_factory_wip`
  - `id`
  - `colony_factory_group_id` FK cascade
  - `quarter`, `unit`, `tech_level`, `quantity`
  - unique: `(colony_factory_group_id, quarter)`

**Acceptance:**
- [x] All 6 tables exist with the required columns, defaults, and unique constraints
- [x] Deleting a `colony_templates` row cascades to template factory groups, units, and WIP rows
- [x] Deleting a `colonies` row cascades to live factory groups, units, and WIP rows
- [x] `php artisan migrate:fresh` succeeds
- [x] `vendor/bin/pint --dirty --format agent`

---

### COL-14 — Add factory group models and parent relationships

**Effort:** M
**Dependencies:** COL-13

**Problem:** The new factory tables need Eloquent models, casts, and relationships. `ColonyTemplate` and `Colony` also need `factoryGroups()` relations.

**Files to create:**
- `app/Models/ColonyTemplateFactoryGroup.php`
- `app/Models/ColonyTemplateFactoryUnit.php`
- `app/Models/ColonyTemplateFactoryWip.php`
- `app/Models/ColonyFactoryGroup.php`
- `app/Models/ColonyFactoryUnit.php`
- `app/Models/ColonyFactoryWip.php`

**Files to modify:**
- `app/Models/ColonyTemplate.php` — add `factoryGroups()` hasMany
- `app/Models/Colony.php` — add `factoryGroups()` hasMany

**Conventions:** Match current Laravel 13 model style in this repo:
- `#[Fillable([...])]` attribute
- `use HasFactory;`
- `casts()` method instead of `$casts` property
- `public $timestamps = false;` on all models except `ColonyTemplate` itself (including template child models like `ColonyTemplateItem`, `ColonyTemplatePopulation`, etc.)

**Model requirements:**
- Group models belong to `ColonyTemplate` / `Colony`
- Group models have many `units()` and `wip()`
- Unit and WIP models belong to their group
- `unit`, `orders_unit`, and `pending_orders_unit` cast to `UnitCode` where present
- `input_remainder_mets` and `input_remainder_nmts` cast to `float` on live factory groups

**Acceptance:**
- [x] All 6 models exist and follow the repo's model conventions
- [x] `ColonyTemplate` exposes `factoryGroups()`
- [x] `Colony` exposes `factoryGroups()`
- [x] Factory group/unit/WIP relations resolve correctly
- [x] `vendor/bin/pint --dirty --format agent`

---

### COL-15 — Add model factories for factory group models

**Effort:** S
**Dependencies:** COL-14

**Problem:** The new factory models need standard Laravel factories for model tests and future feature tests.

**Files to create:**
- `database/factories/ColonyTemplateFactoryGroupFactory.php`
- `database/factories/ColonyTemplateFactoryUnitFactory.php`
- `database/factories/ColonyTemplateFactoryWipFactory.php`
- `database/factories/ColonyFactoryGroupFactory.php`
- `database/factories/ColonyFactoryUnitFactory.php`
- `database/factories/ColonyFactoryWipFactory.php`

**Factory requirements:**
- Template/live group factories default `pending_orders_*` to `null`
- Live factory group factory defaults `input_remainder_mets` and `input_remainder_nmts` to `0`
- Factory unit factories default to `FCT` unit with a positive `tech_level`
- Factory WIP factories default to a valid quarter (`1`, `2`, or `3`) and a manufacturable unit

**Acceptance:**
- [x] Each new factory can `make()` a valid attribute set
- [x] Each new factory can `create()` valid related records
- [x] Live factory group factories default both remainder columns to `0`
- [x] `vendor/bin/pint --dirty --format agent`

---

### COL-16 — Update ImportColonyTemplates for factory groups

**Effort:** M
**Dependencies:** COL-14

**Problem:** `ImportColonyTemplates` does not import `production.factories`. It also needs to respect the corrected JSON semantics: `orders` and `work-in-progress` are group-level, while `units` is just the factory inventory in the group.

**Files to modify:**
- `app/Actions/GameGeneration/ImportColonyTemplates.php`

**Implementation checklist:**
1. Add `createFactoryGroups()` to read `production.factories`
2. Treat an empty `production: []` as "no production groups" and skip gracefully
3. Create one `ColonyTemplateFactoryGroup` per JSON group using:
   - `group_number` from `group`
   - parsed `orders_unit` and `orders_tech_level` from `orders` string
   - `pending_orders_* = null`
4. Create `ColonyTemplateFactoryUnit` rows from `units` array
   - parse `"FCT-1"` into `unit=FCT`, `tech_level=1`
5. Create `ColonyTemplateFactoryWip` rows from `work-in-progress.q1/q2/q3`
   - map `q1` → `quarter=1`, `q2` → `quarter=2`, `q3` → `quarter=3`
   - parse unit/tech level from the WIP unit string
6. Reimport is idempotent because `$game->colonyTemplates()->delete()` cascades to factory groups
7. Consider extracting a shared `parseUnitString()` helper if the same `CODE[-TL]` parsing logic is used in inventory and factory imports

**Acceptance:**
- [x] Importing `sample-data/beta/colony-template.json` creates 7 factory groups for COPN
- [x] Importing creates 1 factory group for CORB
- [x] Importing creates 0 factory groups for CSHP
- [x] Imported factory groups have `pending_orders_unit` and `pending_orders_tech_level` as `null`
- [x] Factory units parse `FCT-1` correctly into `unit=FCT`, `tech_level=1`
- [x] Factory WIP rows store the correct quarter, unit, tech level, and quantity
- [x] Reimporting the same file does not leave duplicate or orphaned rows
- [x] `vendor/bin/pint --dirty --format agent`

---

### COL-17 — Update UploadColonyTemplateRequest validation for factory groups

**Effort:** L
**Dependencies:** COL-12

**Problem:** The request does not validate `production.factories`. It also needs a production-specific validation approach that distinguishes manufacturable targets from non-manufacturable ones.

**Files to modify:**
- `app/Http/Requests/UploadColonyTemplateRequest.php`

**Implementation checklist:**
1. Allow `production` to be either an empty array or an associative array of production sections
2. Validate optional `production.factories` as an array of groups
3. For each factory group, require:
   - `group` integer
   - `orders` string (valid unit code with optional tech level suffix)
   - `units` array of `{unit, quantity}`
   - `work-in-progress` object with `q1`, `q2`, `q3` each having `unit` and `quantity`
4. Validate each factory inventory unit:
   - `unit` matches `FCT-\d+` format
   - `quantity` is integer `>= 0`
5. Validate `orders` and WIP units as manufacturable:
   - reject non-manufacturable targets: `FUEL`, `FOOD`, `GOLD`, `METS`, `NMTS`, population codes
   - allow manufacturable consumables without suffix: `CNGD`, `MTSP`, `RSCH`, `SLS`, `STU`
   - allow manufacturable tech-level units with suffix: `AUT-1`, `MIN-1`, etc.
6. Ensure WIP unit matches the group `orders` target (same base code)
7. Do **not** require `pending_orders` in uploaded JSON
8. Allow `production.farms` / `production.mines` keys to exist even though deep validation for them lands in PR 3 / PR 4
9. Update or replace `isConsumable()` if needed so inventory validation and production validation are both correct

**Acceptance:**
- [x] A valid factory group passes validation
- [x] Missing `orders` fails validation
- [x] Missing `work-in-progress` or any missing quarter (`q1`/`q2`/`q3`) fails validation
- [x] A WIP unit that does not match `orders` fails validation
- [x] A factory inventory unit that is not `FCT-\d+` fails validation
- [x] Orders targeting `FUEL`, `FOOD`, `GOLD`, `METS`, or `NMTS` fail validation
- [x] Templates with an empty `production: []` pass validation
- [x] The sample data file still passes validation even though it includes `farms`
- [x] `vendor/bin/pint --dirty --format agent`

---

### COL-18 — Update EmpireCreator to copy factory groups to live colonies

**Effort:** M
**Dependencies:** COL-14

**Problem:** `EmpireCreator` does not eager-load or copy factory groups from templates to live colonies.

**Files to modify:**
- `app/Services/EmpireCreator.php`

**Implementation checklist:**
1. Eager-load `factoryGroups.units` and `factoryGroups.wip` from `Game::colonyTemplates()`
2. After creating inventory and population, create one `ColonyFactoryGroup` per template factory group
3. Copy: `group_number`, `orders_unit`, `orders_tech_level`, `pending_orders_unit`, `pending_orders_tech_level`
4. Initialize: `input_remainder_mets = 0`, `input_remainder_nmts = 0`
5. Batch-insert `ColonyFactoryUnit` rows from template factory units
6. Batch-insert `ColonyFactoryWip` rows from template factory WIP
7. Preserve behavior for templates with empty production: no production groups are created

**Acceptance:**
- [x] Live colonies receive factory groups copied from their template
- [x] Live factory units and WIP rows match the template values
- [x] `input_remainder_mets` and `input_remainder_nmts` are initialized to `0`
- [x] Templates with empty production produce colonies with 0 live factory groups
- [x] `vendor/bin/pint --dirty --format agent`

---

### COL-19 — Add migration and model tests for factory groups

**Effort:** M
**Dependencies:** COL-15

**Problem:** The new factory schema and models need dedicated tests, and parent model tests should assert the new relations exist.

**Files to create:**
- Migration test file(s) for the 6 factory group tables (follow existing `tests/Feature/Database/Migrations/` pattern)
- Model test file(s) for the 6 factory models (follow existing `tests/Feature/Models/*ModelTest.php` pattern)

**Files to modify:**
- `tests/Feature/Models/ColonyTemplateModelTest.php` — assert `factoryGroups()` relation
- `tests/Feature/Models/ColonyModelTest.php` — assert `factoryGroups()` relation

**Acceptance:**
- [x] Migration tests verify columns, defaults, unique constraints, and cascade deletes for all 6 tables
- [x] Model tests verify casts, relationships, and factory validity for all 6 new models
- [x] Parent model tests cover `factoryGroups()` on both `ColonyTemplate` and `Colony`
- [x] `php artisan test --compact tests/Feature/Models/ tests/Feature/Database/Migrations/`
- [x] `vendor/bin/pint --dirty --format agent`

---

### COL-20 — Add importer and validation tests for factory groups

**Effort:** M
**Dependencies:** COL-16, COL-17

**Problem:** Existing importer/validation tests do not cover factory groups.

**Files to modify:**
- `tests/Feature/ImportColonyTemplatesTest.php`
- `tests/Feature/UploadColonyTemplateValidationTest.php`

**Acceptance:**
- [x] Import tests cover COPN=7, CORB=1, CSHP=0 factory groups from the sample JSON
- [x] Import tests assert correct orders, unit parsing, WIP parsing, and reimport replacement
- [x] Validation tests cover: valid factory groups, missing orders, missing WIP quarters, WIP mismatch, invalid `FCT` unit format, invalid non-manufacturable orders, empty `production: []`, and sample file pass-through
- [x] `php artisan test --compact tests/Feature/ImportColonyTemplatesTest.php tests/Feature/UploadColonyTemplateValidationTest.php`
- [x] `vendor/bin/pint --dirty --format agent`

---

### COL-21 — Add EmpireCreator tests for factory groups

**Effort:** S
**Dependencies:** COL-18

**Problem:** `EmpireCreatorTest` does not prove factory groups are copied to live colonies.

**Files to modify:**
- `tests/Feature/EmpireCreatorTest.php`

**Acceptance:**
- [x] Tests create template factory groups and assert live factory groups, units, and WIP are copied
- [x] Tests assert both `input_remainder_mets` and `input_remainder_nmts` initialize to `0`
- [x] Tests assert colonies from templates with empty production have no factory groups
- [x] `php artisan test --compact tests/Feature/EmpireCreatorTest.php`
- [x] `vendor/bin/pint --dirty --format agent`

---

### COL-22 — Create farm group schema on templates and live colonies

**Effort:** M
**Dependencies:** COL-21

**Problem:** There is no schema for farm groups or staged farm units on templates or live colonies.

**Files to create:**
- Migration: create 4 tables (`colony_template_farm_groups`, `colony_template_farm_units`, `colony_farm_groups`, `colony_farm_units`)

**Schema requirements:**
- `colony_template_farm_groups`
  - `id`
  - `colony_template_id` FK cascade
  - `group_number` integer
  - unique: `(colony_template_id, group_number)`
- `colony_template_farm_units`
  - `id`
  - `colony_template_farm_group_id` FK cascade
  - `unit` string
  - `tech_level` integer
  - `quantity` integer
  - `stage` integer (1–4)
- `colony_farm_groups`
  - `id`
  - `colony_id` FK cascade
  - `group_number` integer
  - unique: `(colony_id, group_number)`
- `colony_farm_units`
  - `id`
  - `colony_farm_group_id` FK cascade
  - `unit`, `tech_level`, `quantity`, `stage` (1–4)

**Acceptance:**
- [x] All 4 farm tables exist with the required columns, defaults, and unique constraints
- [x] Deleting a template or colony cascades to its farm groups and farm units
- [x] `php artisan migrate:fresh` succeeds
- [x] `vendor/bin/pint --dirty --format agent`

---

### COL-23 — Add farm group models and parent relationships

**Effort:** M
**Dependencies:** COL-22

**Problem:** The new farm tables need models, casts, and `farmGroups()` relations on the parent models.

**Files to create:**
- `app/Models/ColonyTemplateFarmGroup.php`
- `app/Models/ColonyTemplateFarmUnit.php`
- `app/Models/ColonyFarmGroup.php`
- `app/Models/ColonyFarmUnit.php`

**Files to modify:**
- `app/Models/ColonyTemplate.php` — add `farmGroups()` hasMany
- `app/Models/Colony.php` — add `farmGroups()` hasMany

**Model requirements:**
- Farm group models belong to `ColonyTemplate` / `Colony`
- Farm group models have many `units()`
- Farm unit models belong to their group
- Farm unit models cast `unit` to `UnitCode`

**Acceptance:**
- [x] All 4 farm models exist and follow the repo's model conventions
- [x] Farm unit models cast `unit` to `UnitCode`
- [x] `ColonyTemplate` exposes `farmGroups()`
- [x] `Colony` exposes `farmGroups()`
- [x] `vendor/bin/pint --dirty --format agent`

---

### COL-24 — Add model factories for farm group models

**Effort:** S
**Dependencies:** COL-23

**Problem:** The new farm models need factories for model tests and service tests.

**Files to create:**
- `database/factories/ColonyTemplateFarmGroupFactory.php`
- `database/factories/ColonyTemplateFarmUnitFactory.php`
- `database/factories/ColonyFarmGroupFactory.php`
- `database/factories/ColonyFarmUnitFactory.php`

**Acceptance:**
- [x] Each new farm factory can `make()` a valid attribute set
- [x] Each new farm factory can `create()` valid related records
- [x] Farm unit factories produce `FRM` units with `stage` in `1..4`
- [x] `vendor/bin/pint --dirty --format agent`

---

### COL-25 — Update ImportColonyTemplates for farm groups

**Effort:** M
**Dependencies:** COL-23

**Problem:** `ImportColonyTemplates` does not import `production.farms`. Farms have different semantics from factories: each unit entry stores its own `stage`, and there is no WIP pipeline.

**Files to modify:**
- `app/Actions/GameGeneration/ImportColonyTemplates.php`

**Implementation checklist:**
1. Add `createFarmGroups()` to read `production.farms`
2. Treat an empty `production: []` as "no farm groups" and skip gracefully
3. If `production` is an associative array but `farms` key is absent (CORB), skip without error
4. Create one `ColonyTemplateFarmGroup` per JSON group with `group_number`
5. Create one `ColonyTemplateFarmUnit` per JSON unit entry
6. Parse `"FRM-1"` into `unit=FRM`, `tech_level=1`
7. Store `stage` from JSON; `quantity` defaults to `0` if omitted
8. After processing the JSON entries, fill in any missing stages (`1`–`4`) with `quantity = 0`. Use the first unit entry's `unit` and `tech_level` for backfilled rows (the sample data uses one unit type per group, e.g., all `FRM-1`)
9. Preserve existing factory import behavior from PR 2

**Acceptance:**
- [x] Importing `sample-data/beta/colony-template.json` creates 1 farm group for COPN
- [x] That COPN farm group contains 4 unit entries of FRM-1 at stages `1`, `2`, `3`, and `4`
- [x] Importing creates 0 farm groups for CORB
- [x] Importing creates 0 farm groups for CSHP
- [x] Reimporting the same file does not leave duplicate or orphaned farm rows
- [x] `vendor/bin/pint --dirty --format agent`

---

### COL-26 — Update UploadColonyTemplateRequest validation for farm groups

**Effort:** M
**Dependencies:** COL-21

**Problem:** The request does not validate `production.farms`. Farm entries have different rules from factory entries: no orders, no WIP, and stage is per-unit.

**Files to modify:**
- `app/Http/Requests/UploadColonyTemplateRequest.php`

**Implementation checklist:**
1. Validate optional `production.farms` as an array of groups
2. For each farm group, require:
   - `group` integer
   - `units` array
3. For each farm unit, require:
   - `unit` matches `FRM-\d+` format
   - `quantity` optional integer `>= 0` (defaults to `0` if omitted)
   - `stage` required integer in `1..4`
4. Do not require or accept factory-specific keys (`orders`, `work-in-progress`) for farms
5. Preserve all PR 2 factory validation rules
6. Allow `production.mines` key to exist without deep validation (PR 4)

**Acceptance:**
- [x] A valid farm group passes validation
- [x] A non-`FRM-\d+` farm unit fails validation
- [x] A farm unit with stage outside `1..4` fails validation
- [x] Templates with an empty `production: []` pass validation
- [x] The sample data file passes validation with both factories and farms present
- [x] `vendor/bin/pint --dirty --format agent`

---

### COL-27 — Update EmpireCreator to copy farm groups to live colonies

**Effort:** S
**Dependencies:** COL-23

**Problem:** `EmpireCreator` does not eager-load or copy farm groups from templates to live colonies.

**Files to modify:**
- `app/Services/EmpireCreator.php`

**Implementation checklist:**
1. Eager-load `farmGroups.units` alongside existing template relations
2. After inventory/population/factory copy, create live farm groups
3. Copy farm units to `colony_farm_units`, preserving `unit`, `tech_level`, `quantity`, `stage`
4. Preserve PR 2 factory copy behavior

**Acceptance:**
- [x] Live colonies receive farm groups copied from their template
- [x] Live farm units preserve the template `stage` values
- [x] Templates with empty production produce colonies with 0 live farm groups
- [x] `vendor/bin/pint --dirty --format agent`

---

### COL-28 — Add migration and model tests for farm groups

**Effort:** M
**Dependencies:** COL-24

**Problem:** The new farm schema and models need dedicated tests, and parent model tests should assert the new relations exist.

**Files to create:**
- Migration test file(s) for the 4 farm tables
- Model test file(s) for the 4 farm models

**Files to modify:**
- `tests/Feature/Models/ColonyTemplateModelTest.php` — assert `farmGroups()` relation
- `tests/Feature/Models/ColonyModelTest.php` — assert `farmGroups()` relation

**Acceptance:**
- [x] Migration tests verify columns, defaults, unique constraints, and cascade deletes for all 4 farm tables
- [x] Model tests verify casts, relationships, and factory validity for all 4 new farm models
- [x] Parent model tests cover `farmGroups()` on both `ColonyTemplate` and `Colony`
- [x] `php artisan test --compact tests/Feature/Models/ tests/Feature/Database/Migrations/`
- [x] `vendor/bin/pint --dirty --format agent`

---

### COL-29 — Add importer and validation tests for farm groups

**Effort:** M
**Dependencies:** COL-25, COL-26

**Problem:** Existing importer/validation tests do not cover farm groups.

**Files to modify:**
- `tests/Feature/ImportColonyTemplatesTest.php`
- `tests/Feature/UploadColonyTemplateValidationTest.php`

**Acceptance:**
- [x] Import tests assert the sample file creates COPN=1, CORB=0, CSHP=0 farm groups
- [x] Import tests assert the 4 COPN farm entries preserve stages `1..4`
- [x] Import tests assert reimport replaces farm rows cleanly
- [x] Validation tests cover: valid farm groups, invalid unit format, invalid stage values, empty production, and sample file pass-through
- [x] `php artisan test --compact tests/Feature/ImportColonyTemplatesTest.php tests/Feature/UploadColonyTemplateValidationTest.php`
- [x] `vendor/bin/pint --dirty --format agent`

---

### COL-30 — Add EmpireCreator tests for farm groups

**Effort:** S
**Dependencies:** COL-27

**Problem:** `EmpireCreatorTest` does not prove farm groups are copied to live colonies with stages preserved.

**Files to modify:**
- `tests/Feature/EmpireCreatorTest.php`

**Acceptance:**
- [x] Tests create template farm groups and assert live farm groups/units are copied
- [x] Tests assert live farm unit stages match the template stages
- [x] Tests assert colonies from templates with empty production have no farm groups
- [x] `php artisan test --compact tests/Feature/EmpireCreatorTest.php`
- [x] `vendor/bin/pint --dirty --format agent`

---

### COL-31 — Create mine group schema on templates and live colonies

**Effort:** M
**Dependencies:** COL-30

**Problem:** There is no schema for mine groups or mine units. Template mine groups need nullable `deposit_id`, while live mine groups require `deposit_id`.

**Files to create:**
- Migration: create 4 tables (`colony_template_mine_groups`, `colony_template_mine_units`, `colony_mine_groups`, `colony_mine_units`)

**Schema requirements:**
- `colony_template_mine_groups`
  - `id`
  - `colony_template_id` FK cascade
  - `group_number` integer
  - `deposit_id` integer, nullable (no FK constraint — templates may reference deposits that don't exist yet)
  - unique: `(colony_template_id, group_number)`
- `colony_template_mine_units`
  - `id`
  - `colony_template_mine_group_id` FK cascade
  - `unit` string
  - `tech_level` integer
  - `quantity` integer
- `colony_mine_groups`
  - `id`
  - `colony_id` FK cascade
  - `group_number` integer
  - `deposit_id` foreignId, required (not nullable), constrained to `deposits` table
  - unique: `(colony_id, group_number)`
- `colony_mine_units`
  - `id`
  - `colony_mine_group_id` FK cascade
  - `unit`, `tech_level`, `quantity`

**Acceptance:**
- [x] All 4 mine tables exist with the required columns and nullability rules
- [x] Template mine groups allow `deposit_id = null`
- [x] Live mine groups require a non-null `deposit_id`
- [x] Deleting a template or colony cascades to its mine groups and mine units
- [x] `php artisan migrate:fresh` succeeds
- [x] `vendor/bin/pint --dirty --format agent`

---

### COL-32 — Add mine group models and parent relationships

**Effort:** M
**Dependencies:** COL-31

**Problem:** The new mine tables need models, relations, and `mineGroups()` on the parent models.

**Files to create:**
- `app/Models/ColonyTemplateMineGroup.php`
- `app/Models/ColonyTemplateMineUnit.php`
- `app/Models/ColonyMineGroup.php`
- `app/Models/ColonyMineUnit.php`

**Files to modify:**
- `app/Models/ColonyTemplate.php` — add `mineGroups()` hasMany
- `app/Models/Colony.php` — add `mineGroups()` hasMany

**Model requirements:**
- `ColonyTemplateMineGroup` belongs to `ColonyTemplate`, has many `units()`
- `ColonyTemplateMineUnit` belongs to group, casts `unit` to `UnitCode`
- `ColonyMineGroup` belongs to `Colony`, belongs to `Deposit`, has many `units()`
- `ColonyMineUnit` belongs to group, casts `unit` to `UnitCode`

**Acceptance:**
- [x] All 4 mine models exist and follow the repo's model conventions
- [x] `ColonyTemplate` exposes `mineGroups()`
- [x] `Colony` exposes `mineGroups()`
- [x] `ColonyMineGroup` resolves its `deposit()` belongsTo relationship
- [x] `vendor/bin/pint --dirty --format agent`

---

### COL-33 — Add model factories for mine group models

**Effort:** S
**Dependencies:** COL-32

**Problem:** The new mine models need factories for model tests and service tests.

**Files to create:**
- `database/factories/ColonyTemplateMineGroupFactory.php`
- `database/factories/ColonyTemplateMineUnitFactory.php`
- `database/factories/ColonyMineGroupFactory.php`
- `database/factories/ColonyMineUnitFactory.php`

**Acceptance:**
- [x] Each new mine factory can `make()` a valid attribute set
- [x] Each new mine factory can `create()` valid related records
- [x] Template mine group factory defaults `deposit_id` to `null`
- [x] Live mine group factory requires a valid `deposit_id` (use `Deposit::factory()`)
- [x] Mine unit factories produce `MIN` units with a positive `tech_level`
- [x] `vendor/bin/pint --dirty --format agent`

---

### COL-34 — Update ImportColonyTemplates for mine groups

**Effort:** M
**Dependencies:** COL-32

**Problem:** `ImportColonyTemplates` does not import `production.mines`. The sample JSON does not include mines, so absence must be treated as normal.

**Files to modify:**
- `app/Actions/GameGeneration/ImportColonyTemplates.php`

**Implementation checklist:**
1. Add `createMineGroups()` to read `production.mines`
2. If `production.mines` is absent or `production` is an empty array, skip without error
3. Create one `ColonyTemplateMineGroup` per JSON group with:
   - `group_number`
   - `deposit_id = null`
4. Create `ColonyTemplateMineUnit` rows from `units`
5. Parse `"MIN-1"` into `unit=MIN`, `tech_level=1`
6. Preserve existing factory/farm import behavior

**Acceptance:**
- [x] A custom test fixture with `production.mines` imports the correct template mine groups and mine units
- [x] Importing `sample-data/beta/colony-template.json` creates 0 mine groups without error
- [x] Imported template mine groups default `deposit_id` to `null`
- [x] Reimporting mine-enabled fixtures replaces mine rows cleanly
- [x] `vendor/bin/pint --dirty --format agent`

---

### COL-35 — Update UploadColonyTemplateRequest validation for mine groups

**Effort:** M
**Dependencies:** COL-30

**Problem:** The request does not validate `production.mines`.

**Files to modify:**
- `app/Http/Requests/UploadColonyTemplateRequest.php`

**Implementation checklist:**
1. Validate optional `production.mines` as an array of groups
2. For each mine group, require:
   - `group` integer
   - `units` array
3. For each mine unit, require:
   - `unit` matches `MIN-\d+` format
   - `quantity` integer `>= 0`
4. Do not require or accept factory/farm-specific keys (`orders`, `work-in-progress`, `stage`) for mines
5. Preserve all PR 2 and PR 3 validation rules
6. Allow sample file to pass when `production.mines` is absent

**Acceptance:**
- [x] A valid mine group passes validation
- [x] A non-`MIN-\d+` mine unit fails validation
- [x] Templates with an empty `production: []` pass validation
- [x] The sample data file passes validation when `production.mines` is absent
- [x] `vendor/bin/pint --dirty --format agent`

---

### COL-36 — Update EmpireCreator to generate mine groups and assign deposits

**Effort:** M
**Dependencies:** COL-32

**Problem:** `EmpireCreator` does not create mine groups for live colonies. Unlike factories and farms (which are copied from templates), mine groups are generated by EmpireCreator based on the homeworld planet's available deposits. Template mine groups provide the unit configuration, but `deposit_id` must be assigned at creation time.

**Files to modify:**
- `app/Services/EmpireCreator.php`

**Implementation checklist:**
1. Eager-load `mineGroups.units` alongside existing template relations
2. When creating live mine groups, fetch deposits for the homeworld planet in a deterministic order (e.g., ordered by `id`)
3. Create one live mine group per template mine group, assigning a deposit to each
4. Copy mine units from the template to `colony_mine_units`
5. Throw a clear `RuntimeException` if there are fewer available deposits than template mine groups
6. Preserve factory/farm copy behavior from PR 2 / PR 3
7. Templates without mine groups produce colonies with no mine groups

**Acceptance:**
- [x] Live colonies receive mine groups with unit configuration from the template and deposits from the homeworld planet
- [x] Each live mine group gets a non-null `deposit_id`
- [x] Mine units are copied correctly to live colonies
- [x] If template mine groups exceed available deposits, empire creation fails with a clear exception
- [x] Templates without mine groups produce colonies with 0 mine groups
- [x] `vendor/bin/pint --dirty --format agent`

---

### COL-37 — Add migration and model tests for mine groups

**Effort:** M
**Dependencies:** COL-33

**Problem:** The new mine schema and models need dedicated tests, and parent model tests should assert the new relations exist.

**Files to create:**
- Migration test file(s) for the 4 mine tables
- Model test file(s) for the 4 mine models

**Files to modify:**
- `tests/Feature/Models/ColonyTemplateModelTest.php` — assert `mineGroups()` relation
- `tests/Feature/Models/ColonyModelTest.php` — assert `mineGroups()` relation

**Acceptance:**
- [x] Migration tests verify columns, nullability, unique constraints, and cascade deletes for all 4 mine tables
- [x] Model tests verify casts, relationships, deposit linkage, and factory validity for all 4 new mine models
- [x] Parent model tests cover `mineGroups()` on both `ColonyTemplate` and `Colony`
- [x] `php artisan test --compact tests/Feature/Models/ tests/Feature/Database/Migrations/`
- [x] `vendor/bin/pint --dirty --format agent`

---

### COL-38 — Add importer and validation tests for mine groups

**Effort:** M
**Dependencies:** COL-34, COL-35

**Problem:** Existing importer/validation tests do not cover mine groups, and the sample JSON intentionally has none.

**Files to modify:**
- `tests/Feature/ImportColonyTemplatesTest.php`
- `tests/Feature/UploadColonyTemplateValidationTest.php`

**Acceptance:**
- [x] Import tests add a custom mine-enabled fixture and assert template mine groups/units import correctly
- [x] Import tests assert the sample file imports 0 mine groups without error
- [x] Import tests assert reimport replaces mine rows cleanly
- [x] Validation tests cover: valid mine groups, invalid mine unit format, empty production, and sample-file compatibility when mines are absent
- [x] `php artisan test --compact tests/Feature/ImportColonyTemplatesTest.php tests/Feature/UploadColonyTemplateValidationTest.php`
- [x] `vendor/bin/pint --dirty --format agent`

---

### COL-39 — Add EmpireCreator tests for mine groups

**Effort:** S
**Dependencies:** COL-36

**Problem:** `EmpireCreatorTest` does not prove mine groups are generated from template configuration and assigned homeworld deposits.

**Files to modify:**
- `tests/Feature/EmpireCreatorTest.php`

**Acceptance:**
- [x] Tests create template mine groups and assert live mine groups are created with units and deposits
- [x] Tests assert live mine groups receive non-null `deposit_id` values from the homeworld planet
- [x] Tests assert insufficient deposits fail with a clear exception
- [x] Tests assert templates without mine groups produce colonies with no mine groups
- [x] `php artisan test --compact tests/Feature/EmpireCreatorTest.php`
- [x] `vendor/bin/pint --dirty --format agent`

---

### COL-40 — Write the colony template reference document

**Effort:** S
**Dependencies:** COL-39

**Problem:** There is no single authoritative, field-by-field reference for the colony template JSON.

**Files to create:**
- `docs/reference/colony-template.md`

**Content requirements (use `diataxis` skill for reference-style writing):**
- Top-level fields: `kind`, `tech-level`, `sol`, `birth-rate-pct`, `death-rate-pct`
- `population[]`: `population_code`, `quantity`, `pay_rate`
- `inventory`: the four sections (`super-structure`, `structure`, `operational`, `cargo`) and `{unit, quantity}` entries
- `production.factories`: group shape with `group`, `orders`, `units` array, `work-in-progress` object (`q1`/`q2`/`q3`)
- `production.farms`: group shape with `group`, `units` array (each with `unit`, `quantity`, `stage`)
- `production.mines`: optional group shape with `group`, `units` array (each with `unit`, `quantity`)
- Valid `kind` values: `COPN`, `CORB`, `CSHP` — and what each allows/omits
- Unit code format examples: `FCT-1`, `FRM-1`, `MIN-1`, `AUT-1`, `CNGD`

**Acceptance:**
- [x] `docs/reference/colony-template.md` exists and is non-empty
- [x] The reference doc matches the reconciled sample JSON and plan decisions
- [x] Factory `orders` / `work-in-progress` documented as group-level fields
- [x] Farm `stage` documented as a per-unit field
- [x] `production.mines` documented as optional/may be absent

---

### COL-41 — Write the farming and factory explanation documents

**Effort:** S
**Dependencies:** COL-40

**Problem:** Referees need explanation docs for the two production systems present in the sample JSON: farm staging and factory WIP.

**Files to create (use `diataxis` skill for explanation-style writing):**
- `docs/referees/explanation/colony-template-farming.md`
- `docs/referees/explanation/colony-template-factories.md`

**Content requirements:**
- Farming doc:
  - The five-stage harvest cycle: `0% → 25% → 50% → 75% → 100%` → harvest → back to `0%`
  - Reset-on-shortage behavior (unlike mines)
  - Why starting units must be staggered across stages 1–4
  - Example: `130,000 FRM-1` → 4 entries of `32,500` at stages 1, 2, 3, 4
- Factory doc:
  - The 3-quarter WIP pipeline: new production → `q1` (25%) → `q2` (50%) → `q3` (75%) → delivered to Cargo
  - Why `orders` and `work-in-progress` are group-level (all factories in a group produce the same thing)
  - How WIP quantities relate to factory count and output rate
  - `pending_orders` and retooling (not used at game start but available later)

**Acceptance:**
- [x] Both explanation docs exist and are non-empty
- [x] Farming doc explains staggering and includes the 32,500-per-stage example
- [x] Factory doc explains the 3-quarter WIP pipeline and group-level `orders`

---

### COL-42 — Write the mining explanation document

**Effort:** S
**Dependencies:** COL-40

**Problem:** Mining has different semantics from both factories and farms, and the sample JSON omits mines entirely. Referees need an explanation.

**Files to create (use `diataxis` skill for explanation-style writing):**
- `docs/referees/explanation/colony-template-mining.md`

**Content requirements:**
- Mines produce one quarter of annual output each turn (no pipeline, no stages)
- 1:1 relationship between mine group and deposit
- Why mine groups are not in the template (deposits are system-specific, assigned by EmpireCreator)
- Input shortages reduce output proportionally — no reset penalty (unlike farms)

**Acceptance:**
- [ ] `docs/referees/explanation/colony-template-mining.md` exists and is non-empty
- [ ] Doc explains why mines are absent from the current sample JSON
- [ ] Doc explains live `deposit_id` assignment during empire creation

---

### COL-43 — Add a documentation smoke test

**Effort:** S
**Dependencies:** COL-41, COL-42

**Problem:** The docs PR needs a cheap automated guard so future changes don't accidentally delete or empty the new documentation files.

**Files to create:**
- `tests/Feature/Docs/ColonyTemplateDocumentationSmokeTest.php` (or nearest existing docs test pattern)

**Test requirements:**
- Assert all 4 new documentation files exist and are non-empty:
  - `docs/reference/colony-template.md`
  - `docs/referees/explanation/colony-template-farming.md`
  - `docs/referees/explanation/colony-template-factories.md`
  - `docs/referees/explanation/colony-template-mining.md`

**Acceptance:**
- [ ] Smoke test passes: `php artisan test --compact --filter=ColonyTemplateDocumentation`
- [ ] `vendor/bin/pint --dirty --format agent`

---

## Execution Order

Tasks should be completed in this order. Tasks at the same indentation level can be parallelized.

```
PR 2 — Factory Groups
  COL-13  (create factory group schema)

  COL-14  (add factory models + parent relations)                 ← after COL-13

    COL-15  (add factory model factories)                         ← parallel
    COL-16  (update ImportColonyTemplates for factories)          ← parallel
    COL-17  (update UploadColonyTemplateRequest for factories)    ← parallel (only needs COL-12)
    COL-18  (update EmpireCreator for factories)                  ← parallel

  COL-19  (factory migration + model tests)                       ← after COL-15
  COL-20  (factory importer + validation tests)                   ← after COL-16, COL-17
  COL-21  (factory EmpireCreator tests)                           ← after COL-18

PR 3 — Farm Groups
  COL-22  (create farm group schema)                              ← after COL-21

  COL-23  (add farm models + parent relations)                    ← after COL-22

    COL-24  (add farm model factories)                            ← parallel
    COL-25  (update ImportColonyTemplates for farms)              ← parallel
    COL-26  (update UploadColonyTemplateRequest for farms)        ← parallel (only needs COL-21)
    COL-27  (update EmpireCreator for farms)                      ← parallel

  COL-28  (farm migration + model tests)                          ← after COL-24
  COL-29  (farm importer + validation tests)                      ← after COL-25, COL-26
  COL-30  (farm EmpireCreator tests)                              ← after COL-27

PR 4 — Mine Groups
  COL-31  (create mine group schema)                              ← after COL-30

  COL-32  (add mine models + parent relations)                    ← after COL-31

    COL-33  (add mine model factories)                            ← parallel
    COL-34  (update ImportColonyTemplates for mines)              ← parallel
    COL-35  (update UploadColonyTemplateRequest for mines)        ← parallel (only needs COL-30)
    COL-36  (update EmpireCreator to generate mines + deposit assignment) ← parallel

  COL-37  (mine migration + model tests)                          ← after COL-33
  COL-38  (mine importer + validation tests)                      ← after COL-34, COL-35
  COL-39  (mine EmpireCreator tests)                              ← after COL-36

PR 5 — Documentation
  COL-40  (write colony template reference doc)                   ← after COL-39

    COL-41  (write farming + factory explanation docs)            ← parallel
    COL-42  (write mining explanation doc)                        ← parallel

  COL-43  (documentation smoke test)                              ← after COL-41, COL-42
```
