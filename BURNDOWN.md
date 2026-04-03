# Burndown — Layer 1, Group D: Template Ingestion Updates

## Overview

Group D updates the colony template upload pipeline to accept the new JSON format: an **array of colony templates**, each with `CODE-TL` unit strings, an `operational`/`stored` inventory split, and a `population` section.

**Prerequisite groups:** A (enums, schema migrations), B (model updates), C (new models/factories) — all complete.

**Out of scope (Group E):** `EmpireCreator` multi-colony creation, `GameGenerationController::activate()` Turn 0 creation.

---

## Task D1 — Add `Game::colonyTemplates()` HasMany relationship

**Status:** DONE

**Why:** The upload controller needs to delete-and-recreate multiple colony templates per game. The existing `colonyTemplate()` is `HasOne` and must stay for existing read paths (`EmpireCreator`, `GameGenerationController::colonyTemplateSummary()`). A new `colonyTemplates()` `HasMany` provides the write path.

**Files to modify:**
- `app/Models/Game.php`

**Changes:**
1. Add import for `ColonyTemplate` if not already present.
2. Add a new `colonyTemplates()` method returning `HasMany` to `ColonyTemplate`.
3. Do **not** remove or rename the existing `colonyTemplate()` method.

**Tests:**
- Run existing tests to confirm nothing breaks: `php artisan test --compact --filter=GameGenerationControllerTest`
- Run: `php artisan test --compact --filter=EmpireCreatorTest`

**Acceptance criteria:**
- `Game` has both `colonyTemplate(): HasOne` and `colonyTemplates(): HasMany`.
- All existing tests pass without modification.

---

## Task D2 — Rewrite `UploadColonyTemplateRequest` validation for the new schema

**Status:** DONE

**Why:** The current validation only checks that the file is valid JSON with a non-empty `inventory` array. The new format requires validating: array-of-templates, `kind` as `ColonyKind`, `tech-level` as integer, `population` section, `inventory.operational`/`inventory.stored` split, and `CODE-TL` unit format rules.

**Files to modify:**
- `app/Http/Requests/UploadColonyTemplateRequest.php`

**Changes:**
Rewrite the `after()` validation closure to validate the new schema. Keep the `rules()` method (file upload validation) unchanged.

Validation rules for the decoded JSON:
1. Top-level must be a **non-empty array** of template objects.
2. **Duplicate `kind` values** across templates must be rejected (one colony per kind per planet).
3. Per template:
   - `kind` — required, must be a valid `ColonyKind` backed value (`COPN`, `CENC`, `CORB`).
   - `tech-level` — required, must be a positive integer.
   - `population` — required, must be a non-empty array.
   - `inventory` — required, must be an array/object.
4. Per population entry:
   - `population_code` — required, must be a valid `PopulationClass` backed value.
   - `quantity` — required, integer >= 0.
   - `pay_rate` — required, numeric >= 0.
5. Inventory structure:
   - `inventory.operational` and `inventory.stored` are each optional arrays.
   - At least one item must exist across both sections combined.
6. Per inventory item:
   - `unit` — required string.
   - `quantity` — required, integer >= 0.
7. **Unit format rules** (critical):
   - If the unit string contains `-`, split on first `-`:
     - Left side must be a valid `UnitCode` backed value.
     - Left side must **not** be a consumable (`CNGD`, `FOOD`, `FUEL`, `GOLD`, `METS`, `MTSP`, `NMTS`, `RSCH`).
     - Right side must be a positive integer (tech level).
   - If the unit string has no `-`:
     - Must be a valid `UnitCode` backed value.
     - Must be a consumable (one of the 8 consumable codes listed above).

**Identifying consumables:** Use a static method or constant. The simplest approach: define a private method `isConsumable(string $code): bool` that checks against the list of consumable `UnitCode` backed values. Alternatively, check whether `UnitCode` already has a helper — it does not, so add the check inline or as a private method in the request class.

**Tests:**
Create `tests/Feature/UploadColonyTemplateValidationTest.php` (or add to existing `GameGenerationControllerTest`). Test cases:

- Valid new-format upload with 1 template passes validation.
- Valid new-format upload with 2 templates passes validation.
- Non-array top-level (single object) fails.
- Empty array fails.
- Missing `kind` fails.
- Invalid `kind` value fails.
- Missing `tech-level` fails.
- Missing `population` fails.
- Empty `population` array fails.
- Missing inventory fails.
- Empty inventory (both operational and stored empty/missing) fails.
- `FCT` without tech level suffix fails (non-consumable must use `CODE-TL`).
- `FUEL-1` fails (consumable must not have tech level).
- `INVALID-1` fails (unknown unit code).
- Duplicate `kind` values in the array fails.
- Valid `FCT-1` and `FUEL` pass.

**Acceptance criteria:**
- Request validates the new array-of-templates schema.
- Unit format rules enforce `CODE-TL` for non-consumables and plain codes for consumables.
- Duplicate `kind` values are rejected.
- All validation errors appear on the `template` key (consistent with existing pattern).

---

## Task D3 — Refactor `TemplateController::uploadColony()` for multi-template ingestion

**Status:** DONE

**Why:** The controller currently reads a single-object JSON with flat `inventory` and no `population`. It must be rewritten to: iterate an array of templates, parse `CODE-TL` unit strings, distinguish `operational`/`stored` inventory, and store population rows.

**Files to modify:**
- `app/Http/Controllers/GameGeneration/TemplateController.php`

**Changes to `uploadColony()` method:**
1. Keep the authorization and active-game guard unchanged.
2. Parse the uploaded file as `$templatesData = json_decode(...)` — expect a top-level array.
3. Wrap the entire delete/recreate in a `DB::transaction()`.
4. Delete all existing templates: `$game->colonyTemplates()->delete()` (cascading deletes handle items and population).
5. For each template entry in the array:
   a. Create a `ColonyTemplate` via `$game->colonyTemplates()->create([...])`:
      - `kind` from the entry's `kind` value.
      - `tech_level` from the entry's `tech-level` value.
   b. Store population rows via `$template->population()->create([...])` for each entry in the template's `population` array:
      - `population_code`, `quantity`, `pay_rate`.
   c. Merge operational and stored inventory:
      ```php
      $allItems = array_merge(
          $templateData['inventory']['operational'] ?? [],
          $templateData['inventory']['stored'] ?? [],
      );
      ```
   d. For each item, parse the `unit` string:
      - If contains `-`: split on first `-` → `unit = left part`, `tech_level = (int) right part`.
      - If no `-`: `unit = full string`, `tech_level = 0`.
   e. Store inventory items via `$template->items()->create([...])`:
      - `unit`, `tech_level`, `quantity_assembled = quantity`, `quantity_disassembled = 0`.

**Required imports:** Add `use Illuminate\Support\Facades\DB;` if not present.

**Tests:**
Add/update tests in `tests/Feature/GameGenerationControllerTest.php`:

- Upload with 1 template stores 1 `colony_templates` row, correct items, correct population.
- Upload with 2 templates stores 2 `colony_templates` rows, each with their own items and population.
- Re-upload replaces all previous templates (old template rows are deleted, new ones created).
- Unit parsing: `FCT-1` → `unit=FCT, tech_level=1`; `FUEL` → `unit=FUEL, tech_level=0`; `STU` (no TL) → `unit=STU, tech_level=0`.
- Population rows are stored with correct `population_code`, `quantity`, `pay_rate`.
- Active game rejection still works with new payload format.

**Acceptance criteria:**
- Uploading a valid multi-template JSON creates the correct number of templates, items, and population rows.
- `CODE-TL` parsing correctly splits unit code and tech level.
- Plain consumable codes store `tech_level = 0`.
- Re-upload is atomic (transaction) and fully replaces all previous templates.
- Existing authorization and active-game guard behavior is preserved.

---

## Task D4 — Update existing colony upload tests for new JSON format

**Status:** TODO

**Why:** The existing `makeColonyTemplateJson()` helper and the 4 colony upload tests in `GameGenerationControllerTest` use the old single-object format. They must be updated to use the new array-of-templates format with `CODE-TL` units and population.

**Files to modify:**
- `tests/Feature/GameGenerationControllerTest.php`

**Changes:**
1. Rewrite `makeColonyTemplateJson()` to return the new format:
   ```php
   private function makeColonyTemplateJson(int $templateCount = 1, int $itemCount = 1): string
   ```
   - Returns a JSON-encoded array of `$templateCount` templates.
   - Each template includes: `kind`, `tech-level`, `population` (at least 1 entry), `inventory` with `operational` (containing `$itemCount` items using `CODE-TL` format) and optionally `stored`.
   - Use valid `ColonyKind` values, cycling through `COPN`, `CORB`, `CENC` for multiple templates.

2. Update `upload_colony_template_creates_template_and_items`:
   - Use `makeColonyTemplateJson(templateCount: 1, itemCount: 3)`.
   - Assert template count, item count, and population count.

3. Update `upload_colony_template_replaces_existing_template`:
   - Start from `withDefaultTemplates()` (which creates 2 templates from the sample file).
   - Upload new payload.
   - Assert old templates are gone, new templates exist with correct counts.

4. Update `upload_colony_template_is_rejected_when_game_is_active`:
   - Just change the payload to new format.

5. Update `upload_colony_template_is_rejected_when_no_inventory_items`:
   - Use new format with empty `operational` and `stored` arrays.

**Tests:**
- All 4 existing tests pass with the new format.
- Run: `php artisan test --compact --filter=GameGenerationControllerTest`

**Acceptance criteria:**
- `makeColonyTemplateJson()` produces new-format JSON exclusively.
- All existing colony upload tests pass and test the real contract.
- No test still depends on the old single-object format.

---

## Task D5 — Add real sample file upload regression test

**Status:** TODO

**Why:** A regression test using the actual `sample-data/beta/colony-template.json` file locks the contract between the checked-in sample data and the upload pipeline, preventing future drift.

**Files to modify:**
- `tests/Feature/GameGenerationControllerTest.php`

**Changes:**
Add a new test method `upload_colony_template_with_real_sample_file`:
1. Create a game, authenticate as GM.
2. Read the real file from `base_path('sample-data/beta/colony-template.json')`.
3. Create an `UploadedFile` from it.
4. POST to the colony template upload endpoint.
5. Assert the upload succeeds (redirect, no errors).
6. Assert database state:
   - 2 `colony_templates` rows created for this game.
   - First template (`COPN`): 17 total inventory items (6 operational + 11 stored), 4 population rows.
   - Second template (`CORB`): 1 inventory item (1 operational + 0 stored), 4 population rows.
7. Spot-check parsed values:
   - `ASW-1` stored as `unit = ASW`, `tech_level = 1`.
   - `FUEL` stored as `unit = FUEL`, `tech_level = 0`.
   - `STU` (operational, no tech level) stored as `unit = STU`, `tech_level = 0`.
   - First template's `UEM` population: `quantity = 3500000`, `pay_rate = 0.0`.

**Tests:**
- Run: `php artisan test --compact --filter=upload_colony_template_with_real_sample_file`

**Acceptance criteria:**
- The checked-in sample file uploads successfully end-to-end.
- Database state matches the sample file contents exactly.
- Parsed unit codes and tech levels are correct.

---

## Task D6 — Run Pint and full test suite

**Status:** TODO

**Why:** Final cleanup and verification that all changes are consistent and nothing is broken.

**Steps:**
1. Run `vendor/bin/pint --dirty` to fix any formatting issues.
2. Run `php artisan test --compact` to verify the full test suite passes.

**Acceptance criteria:**
- No Pint violations.
- Full test suite passes.

---

## Group D Acceptance Criteria

Group D is complete when **all** of the following are true:

1. **`sample-data/beta/colony-template.json`** is confirmed to already match the new contract (array format, `CODE-TL` units, population). A regression test locks this contract.

2. **`UploadColonyTemplateRequest`** validates:
   - Top-level array of templates (non-empty).
   - Each template has `kind` (valid `ColonyKind`), `tech-level` (integer), `population` (non-empty array), and `inventory`.
   - Population entries have valid `population_code`, `quantity`, `pay_rate`.
   - Inventory items use correct unit format: `CODE-TL` for non-consumables, plain code for consumables.
   - Duplicate `kind` values across templates are rejected.

3. **`TemplateController::uploadColony()`**:
   - Accepts the new array-of-templates schema.
   - Deletes all existing colony templates for the game (atomic transaction).
   - Creates all uploaded templates with items and population rows.
   - Parses `CODE-TL` format correctly (`FCT-1` → unit=FCT, tech_level=1; `FUEL` → unit=FUEL, tech_level=0).

4. **`Game` model** exposes both `colonyTemplate(): HasOne` (existing readers) and `colonyTemplates(): HasMany` (upload write path).

5. **Out-of-scope guardrails intact:**
   - `EmpireCreator` still creates from the first template only (unchanged).
   - `GameGenerationController::colonyTemplateSummary()` still uses `colonyTemplate()` (unchanged).
   - No multi-colony empire creation logic introduced.

6. **Tests:**
   - All existing colony upload tests updated for new format and passing.
   - New validation tests cover positive and negative cases.
   - Real sample file regression test passes.
   - Full test suite passes.
   - Pint clean.
