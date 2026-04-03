# Burndown — Layer 1, Group E: Business Logic Extensions

## Overview

Group E extends the setup-time business logic with two changes:

- **Task 21:** Extend `EmpireCreator` so new empires create colonies from **all** colony templates and copy each template's `population` rows into `colony_population`.
- **Task 22:** Extend `GameGenerationController::activate()` so activating a game also creates **Turn 0** with status `pending` in the **same transaction**.

**Prerequisite groups:** A (enums, schema migrations), B (model updates), C (new models/factories), D (template ingestion) — all complete.

**Out of scope (Group F):** Report schema, report models, SetupReportGenerator service.

**Scope guardrails:**
- Do **not** change template upload logic.
- Do **not** change `Game::colonyTemplate()` or `GameGenerationController::colonyTemplateSummary()`.
- Do **not** introduce new services, jobs, listeners, or events for Turn 0 creation.

---

## Task E1 — Extend `EmpireCreator` for multi-template colony creation and starting population

**Status:** [x] Complete

**Why:** `EmpireCreator` currently reads only the first colony template via `$game->colonyTemplate()` (HasOne) and creates a single colony with inventory. It never populates `colony_population`. Group D made colony templates multi-row (`colonyTemplates()` HasMany), so empire creation must now create one colony per template and copy both inventory and population from each.

**Files to modify:**
- `app/Services/EmpireCreator.php`

**Changes:**

1. Add import for `ColonyPopulation`:
   ```php
   use App\Models\ColonyPopulation;
   ```

2. Replace the single-template lookup in the private colony creation method:
   - **Current:** `$game->colonyTemplate()->with('items')->first()`
   - **New:** `$game->colonyTemplates()->with(['items', 'population'])->orderBy('id')->get()`

3. If the collection is empty, throw `RuntimeException` — keep the message containing `colony template` so the existing test expectation stays valid.

4. Rename the private method from `createColony()` to `createColonies()` (returns `void` instead of `Colony`). Update the call site in `create()`.

5. Loop over each `ColonyTemplate` and for each:
   - Create a `Colony` via `Colony::create()` with `empire_id`, `planet_id` (homeworld), `kind`, `tech_level`.
   - Copy inventory using `ColonyInventory::insert()` — use `$item->unit->value` (not the enum object) because `insert()` bypasses casts.
   - Copy population using `ColonyPopulation::insert()` — use `$pop->population_code->value` for the same reason. Always set `rebel_quantity` to `0`.

6. Population insert mapping per template population row:
   ```php
   [
       'colony_id' => $colony->id,
       'population_code' => $pop->population_code->value,
       'quantity' => $pop->quantity,
       'pay_rate' => $pop->pay_rate,
       'rebel_quantity' => 0,
   ]
   ```

7. Inventory insert mapping (existing pattern, but fix enum usage):
   ```php
   [
       'colony_id' => $colony->id,
       'unit' => $item->unit->value,
       'tech_level' => $item->tech_level,
       'quantity_assembled' => $item->quantity_assembled,
       'quantity_disassembled' => $item->quantity_disassembled,
   ]
   ```

8. Do **not** change:
   - The outer `DB::transaction()` in `create()`
   - Player / capacity validation logic
   - `reassign()` method (it already updates all homeworld colonies)

**Tests:**
- Run existing tests first: `php artisan test --compact --filter=EmpireCreatorTest`
- All existing tests should pass (the fixture creates one template, so behavior is unchanged for single-template cases)

**Acceptance criteria:**
- `EmpireCreator` uses `colonyTemplates()` (HasMany) instead of `colonyTemplate()` (HasOne).
- One colony is created per colony template, all on the homeworld.
- Inventory rows are copied using `->value` for enum-backed columns.
- Population rows are created in `colony_population` with `rebel_quantity = 0`.
- Existing single-template behavior still works.

---

## Task E2 — Add `EmpireCreator` test coverage for multi-template and population

**Status:** [x] Complete

**Why:** The `EmpireCreator` changes from Task E1 need test coverage for the new multi-colony and population behavior, and the existing fixture needs a population row for realistic testing.

**Files to modify:**
- `tests/Feature/EmpireCreatorTest.php`

**Changes:**

1. Add missing import:
   ```php
   use App\Enums\PopulationClass;
   ```

2. Update `activeGameWithHomeSystem()` fixture — after creating the colony template and its item, add a population row:
   ```php
   $colonyTemplate->population()->create([
       'population_code' => PopulationClass::Unemployable,
       'quantity' => 3500000,
       'pay_rate' => 0.0,
   ]);
   ```

3. Add new test `create_applies_colony_template_population`:
   - Create game + player, call `create()`
   - Get the colony's population rows
   - Assert 1 population row exists
   - Assert `population_code === PopulationClass::Unemployable`
   - Assert `quantity === 3500000`
   - Assert `pay_rate === 0.0`
   - Assert `rebel_quantity === 0`

4. Add new test `create_creates_one_colony_per_template_on_homeworld`:
   - After `activeGameWithHomeSystem()`, create a second colony template on the same game:
     ```php
     $template2 = $game->colonyTemplates()->create([
         'kind' => ColonyKind::Orbital,
         'tech_level' => 2,
     ]);
     $template2->items()->create([
         'unit' => UnitCode::Farms,
         'tech_level' => 1,
         'quantity_assembled' => 3,
         'quantity_disassembled' => 0,
     ]);
     $template2->population()->create([
         'population_code' => PopulationClass::Unskilled,
         'quantity' => 1000000,
         'pay_rate' => 0.125,
     ]);
     ```
   - Create player, call `create()`
   - Assert empire has 2 colonies
   - Assert both colonies have `planet_id === homeSystem->homeworld_planet_id`
   - Assert one colony has `kind === ColonyKind::OpenSurface`, the other `kind === ColonyKind::Orbital`
   - Assert each colony has the correct inventory count and population count

5. Update `create_throws_when_no_colony_template` to delete all templates:
   ```php
   $game->colonyTemplates()->delete();
   ```
   instead of `$game->colonyTemplate()->delete()`.

**Tests:**
```bash
php artisan test --compact --filter=EmpireCreatorTest
php artisan test --compact --filter=create_applies_colony_template_population
php artisan test --compact --filter=create_creates_one_colony_per_template_on_homeworld
```

**Acceptance criteria:**
- New tests verify population rows are created with correct values.
- New tests verify multi-template creates multiple colonies with correct kinds.
- Existing tests continue to pass.
- The missing-template test uses `colonyTemplates()->delete()`.

---

## Task E3 — Extend `GameGenerationController::activate()` to create Turn 0

**Status:** [ ] Not started

**Why:** `activate()` currently marks the game as `Active` but creates no Turn. The setup report flow requires `Game::canGenerateReports()` to return `true`, which needs an active game **and** a current turn with non-locked, non-generating status.

**Files to modify:**
- `app/Http/Controllers/GameGenerationController.php`

**Changes:**

1. Add missing import:
   ```php
   use App\Enums\TurnStatus;
   ```

2. Inside the existing `DB::transaction()` in `activate()`, after saving the game status, create Turn 0:
   ```php
   $game->status = GameStatus::Active;
   $game->save();

   $game->turns()->create([
       'number' => 0,
       'status' => TurnStatus::Pending,
   ]);
   ```

3. Do **not** change:
   - Authorization (`Gate::authorize('update', $game)`)
   - `canActivate()` validation
   - Redirect or flash message
   - Do **not** add a second transaction or move Turn creation outside the existing transaction

**Tests:**
- Run: `php artisan test --compact --filter=GameGenerationControllerActivateTest`
- All existing tests should pass unchanged.

**Acceptance criteria:**
- Successful activation creates Turn 0 in the same transaction.
- Turn 0 has `number = 0`, `status = pending`, `reports_locked_at = null`.
- Existing authorization and rejection tests pass unchanged.

---

## Task E4 — Add activate test coverage for Turn 0 creation

**Status:** [ ] Not started

**Why:** The activate changes from Task E3 need test coverage for Turn 0 creation, atomicity, and rejection-path safety.

**Files to modify:**
- `tests/Feature/GameGenerationControllerActivateTest.php`

**Changes:**

1. Add missing imports:
   ```php
   use App\Enums\TurnStatus;
   use App\Models\Turn;
   ```

2. Add new test `activate_creates_turn_zero_with_pending_status`:
   - Use existing `gameWithHomeSystem()` fixture, authenticate as GM
   - POST to activate
   - Assert redirect (success)
   - Assert `$game->fresh()->status === GameStatus::Active`
   - Assert exactly 1 turn exists for the game:
     ```php
     $this->assertDatabaseCount('turns', 1);
     ```
   - Assert the turn has correct values:
     ```php
     $this->assertDatabaseHas('turns', [
         'game_id' => $game->id,
         'number' => 0,
         'status' => 'pending',
     ]);
     ```
   - Assert `$game->fresh()->currentTurn->number === 0`
   - Assert `$game->fresh()->canGenerateReports() === true`

3. Add new test `activate_rolls_back_when_turn_zero_creation_fails`:
   - Create a valid activatable game with `gameWithHomeSystem()`
   - Pre-create a Turn 0 row to trigger a unique constraint violation:
     ```php
     Turn::create([
         'game_id' => $game->id,
         'number' => 0,
         'status' => TurnStatus::Pending,
     ]);
     ```
   - Call `$this->withoutExceptionHandling()`
   - Expect `Illuminate\Database\QueryException` (import if needed)
   - POST to activate
   - After catching, assert the game status is still `GameStatus::HomeSystemGenerated` (not `Active`)

4. Strengthen one rejection test — in `activate_is_rejected_when_status_is_not_home_system_generated`, add assertion that no turns were created:
   ```php
   $this->assertDatabaseCount('turns', 0);
   ```

**Tests:**
```bash
php artisan test --compact --filter=GameGenerationControllerActivateTest
php artisan test --compact --filter=activate_creates_turn_zero_with_pending_status
php artisan test --compact --filter=activate_rolls_back_when_turn_zero_creation_fails
```

**Acceptance criteria:**
- Turn 0 is verified as created with correct values after activation.
- `currentTurn()` resolves to Turn 0.
- `canGenerateReports()` returns `true` after activation.
- Transaction rollback is verified — failed Turn 0 creation does not leave the game as Active.
- Failed activation does not create stray turns.

---

## Task E5 — Formatting and full test suite verification

**Status:** [ ] Not started

**Why:** Final cleanup to ensure all Group E changes are formatted and the entire test suite passes.

**Steps:**

1. Run Pint on modified files:
   ```bash
   vendor/bin/pint --dirty
   ```

2. Run focused Group E tests:
   ```bash
   php artisan test --compact --filter=EmpireCreatorTest
   php artisan test --compact --filter=GameGenerationControllerActivateTest
   ```

3. Run the full test suite:
   ```bash
   php artisan test --compact
   ```

**Acceptance criteria:**
- No Pint violations.
- All `EmpireCreatorTest` tests pass.
- All `GameGenerationControllerActivateTest` tests pass.
- Full test suite passes.

---

## Group E Acceptance Criteria

Group E is complete when **all** of the following are true:

- [ ] **1. Multi-colony empire creation:** `EmpireCreator` uses `Game::colonyTemplates()` (HasMany) and creates one colony per template, all on the empire's homeworld.

- [ ] **2. Starting inventory:** Inventory rows are copied from each template using `insert()` with `->value` for enum-backed columns (bypassing cast safety).

- [ ] **3. Starting population:** `colony_population` rows are created for each template's population entries with `rebel_quantity = 0`. Retrieved rows cast back correctly to `PopulationClass`.

- [ ] **4. Turn 0 creation:** `GameGenerationController::activate()` creates Turn 0 with `TurnStatus::Pending` and `reports_locked_at = null` in the same transaction that sets the game to `Active`.

- [ ] **5. Turn behavior:** Turn 0 becomes the game's `currentTurn`. `Game::canGenerateReports()` returns `true` immediately after activation.

- [ ] **6. Atomicity:** If Turn 0 creation fails, the game status change is rolled back. Failed activation does not create stray turns.

- [ ] **7. Regression safety:** Single-template empire creation still works. Existing home-system assignment, capacity checks, and reassignment behavior unchanged. Existing activation authorization and rejection tests pass.

- [ ] **8. Scope control:** No changes to template upload logic. No changes to `Game::colonyTemplate()` or `colonyTemplateSummary()`. No new services, events, or jobs introduced.

- [ ] **9. Verification:** Pint clean. Focused Group E tests pass. Full test suite passes.
