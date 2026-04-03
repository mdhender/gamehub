# Burndown — Layer 1, Group F: Report Schema and Service

## Overview

Group F creates the **report schema**, **report models**, **factories**, and the **`SetupReportGenerator` service** — the core data pipeline that materializes Turn 0 setup reports for all empires.

**Design reference:** `docs/SETUP_REPORT.md` — Group F, tasks #23–30.

**Prerequisite groups:** A (enums, schema migrations), B (model updates), C (new models/factories), D (template ingestion), E (business logic extensions) — all complete.

**Out of scope (Group G):** Routes, TurnReportController, TurnReportPolicy, authorization.

**Scope guardrails:**
- Do **not** create controller actions, routes, or policies.
- Do **not** modify existing live models (`Colony`, `Empire`, `Turn`, etc.) except to add inverse relationship helpers.
- Do **not** create frontend pages or components.
- Report snapshot tables store **plain integers** for `source_colony_id`, `planet_id` — these are **not** foreign keys to live tables.
- The only hard FKs in report tables are **parent-child within the report schema** (all `cascadeOnDelete`).

---

## Task F1 — Migration: `turn_reports` table

**Status:** [x] Complete

**Design task:** #23

**Files to create:**
- `database/migrations/..._create_turn_reports_table.php` (use `php artisan make:migration`)

**Schema:**

| Column | Type | Notes |
|---|---|---|
| `id` | integer PK | Auto-increment |
| `game_id` | integer FK → `games` | `constrained()->cascadeOnDelete()` |
| `turn_id` | integer FK → `turns` | `constrained()->cascadeOnDelete()` |
| `empire_id` | integer FK → `empires` | `constrained()->cascadeOnDelete()` |
| `generated_at` | datetime | Not nullable |

**Constraints:**
- Unique: `['turn_id', 'empire_id']`
- No `timestamps()` — use `$table->id()` and explicit columns only

**Implementation notes:**
- Use `$table->foreignId('game_id')->constrained()->cascadeOnDelete()` pattern for all three FKs.
- These are the only report tables with hard FKs to live entities. All child report tables reference report parents only.

**Tests:**
- Add to `tests/Feature/Reports/TurnReportSchemaTest.php` (create this file with `LazilyRefreshDatabase`, `#[Test]` attributes)
- `test_turn_reports_table_can_store_a_report_header` — insert a row, assert it persists.
- `test_turn_reports_table_enforces_unique_turn_and_empire` — insert two rows with same `(turn_id, empire_id)`, assert `QueryException`.

**Acceptance criteria:**
- [x] Migration creates `turn_reports` with exact columns listed above
- [x] Unique constraint on `(turn_id, empire_id)` is enforced
- [x] `generated_at` column exists and is not nullable
- [x] Table has no `created_at` / `updated_at` columns
- [x] Tests pass: `php artisan test --compact --filter=TurnReportSchemaTest`

---

## Task F2 — Migration: `turn_report_colonies` table

**Status:** [x] Complete

**Design task:** #24

**Files to create:**
- `database/migrations/..._create_turn_report_colonies_table.php`

**Schema:**

| Column | Type | Notes |
|---|---|---|
| `id` | integer PK | |
| `turn_report_id` | integer FK → `turn_reports` | `constrained()->cascadeOnDelete()` |
| `source_colony_id` | integer nullable | **Plain integer, NOT a foreign key** |
| `name` | string | Colony name at report time |
| `kind` | string | `ColonyKind` enum value (COPN, CENC, CORB) |
| `tech_level` | integer | |
| `planet_id` | integer nullable | **Plain integer, NOT a foreign key** |
| `orbit` | integer | Denormalized from planet |
| `star_x` | integer | Denormalized from star |
| `star_y` | integer | Denormalized from star |
| `star_z` | integer | Denormalized from star |
| `star_sequence` | integer | Denormalized from star |
| `is_on_surface` | boolean | |
| `rations` | float | |
| `sol` | float | |
| `birth_rate` | float | |
| `death_rate` | float | |

**Constraints:**
- No `timestamps()`
- `source_colony_id` — use `$table->integer('source_colony_id')->nullable()`, do **not** call `constrained()` or `references()`
- `planet_id` — use `$table->integer('planet_id')->nullable()`, do **not** call `constrained()` or `references()`

**Tests:**
- Add to `tests/Feature/Reports/TurnReportSchemaTest.php`
- `test_turn_report_colonies_accept_plain_source_ids_without_live_foreign_keys` — create a `turn_report_colonies` row with arbitrary `source_colony_id` and `planet_id` values that don't exist in `colonies`/`planets` tables. Assert no constraint violation.
- `test_deleting_turn_report_cascades_to_turn_report_colonies` — create parent + child, delete parent, assert child is gone.

**Acceptance criteria:**
- [x] Table matches design schema exactly
- [x] `turn_report_id` FK cascades on delete
- [x] `source_colony_id` is nullable and is **not** a foreign key
- [x] `planet_id` is nullable and is **not** a foreign key
- [x] No timestamps columns
- [x] Tests pass: `php artisan test --compact --filter=TurnReportSchemaTest`

---

## Task F3 — Migrations: `turn_report_colony_inventory` and `turn_report_colony_population` tables

**Status:** [x] Complete

**Design tasks:** #25, #26

**Files to create:**
- `database/migrations/..._create_turn_report_colony_inventory_table.php`
- `database/migrations/..._create_turn_report_colony_population_table.php`

**Schema: `turn_report_colony_inventory`**

| Column | Type | Notes |
|---|---|---|
| `id` | integer PK | |
| `turn_report_colony_id` | integer FK → `turn_report_colonies` | `constrained()->cascadeOnDelete()` |
| `unit_code` | string | `UnitCode` enum value |
| `tech_level` | integer | |
| `quantity_assembled` | integer | |
| `quantity_disassembled` | integer | |

**Schema: `turn_report_colony_population`**

| Column | Type | Notes |
|---|---|---|
| `id` | integer PK | |
| `turn_report_colony_id` | integer FK → `turn_report_colonies` | `constrained()->cascadeOnDelete()` |
| `population_code` | string | `PopulationClass` enum value |
| `quantity` | integer | |
| `pay_rate` | float | |
| `rebel_quantity` | integer | |

**Constraints:**
- No `timestamps()` on either table
- No uniqueness constraints beyond the PK — the design does not specify any

**Tests:**
- Add to `tests/Feature/Reports/TurnReportSchemaTest.php`
- `test_turn_report_colony_inventory_can_be_created` — insert a row, assert it persists.
- `test_turn_report_colony_population_can_be_created` — insert a row, assert it persists.
- `test_deleting_turn_report_colony_cascades_inventory_and_population` — create a `turn_report_colonies` row with child inventory and population rows, delete the colony row, assert all children are gone.

**Acceptance criteria:**
- [x] Both tables exist with exact columns listed above
- [x] Both child FKs cascade on delete from `turn_report_colonies`
- [x] No timestamps on either table
- [x] Tests pass: `php artisan test --compact --filter=TurnReportSchemaTest`

---

## Task F4 — Migrations: `turn_report_surveys` and `turn_report_survey_deposits` tables

**Status:** [x] Complete

**Design task:** #27

**Files to create:**
- `database/migrations/..._create_turn_report_surveys_table.php`
- `database/migrations/..._create_turn_report_survey_deposits_table.php`

**Schema: `turn_report_surveys`**

| Column | Type | Notes |
|---|---|---|
| `id` | integer PK | |
| `turn_report_id` | integer FK → `turn_reports` | `constrained()->cascadeOnDelete()` |
| `planet_id` | integer nullable | **Plain integer, NOT a foreign key** |
| `orbit` | integer | |
| `star_x` | integer | |
| `star_y` | integer | |
| `star_z` | integer | |
| `star_sequence` | integer | |
| `planet_type` | string | `PlanetType` enum value |
| `habitability` | integer | |

**Schema: `turn_report_survey_deposits`**

| Column | Type | Notes |
|---|---|---|
| `id` | integer PK | |
| `turn_report_survey_id` | integer FK → `turn_report_surveys` | `constrained()->cascadeOnDelete()` |
| `deposit_no` | integer | 1-based sequence within planet |
| `resource` | string | `DepositResource` enum value |
| `yield_pct` | integer | |
| `quantity_remaining` | integer | |

**Constraints:**
- No `timestamps()` on either table
- `planet_id` — `$table->integer('planet_id')->nullable()`, **not** a foreign key

**Tests:**
- Add to `tests/Feature/Reports/TurnReportSchemaTest.php`
- `test_turn_report_surveys_accept_plain_planet_id_without_live_foreign_key` — insert a survey row with a `planet_id` that doesn't exist in `planets`. Assert no constraint violation.
- `test_deleting_turn_report_survey_cascades_deposits` — create parent survey + deposit children, delete survey, assert deposits are gone.

**Acceptance criteria:**
- [x] Both tables exist with exact columns listed above
- [x] `planet_id` on surveys is a plain nullable integer, not a FK
- [x] Survey deposit FK cascades on delete from `turn_report_surveys`
- [x] No timestamps on either table
- [x] Tests pass: `php artisan test --compact --filter=TurnReportSchemaTest`

---

## Task F5 — Model: `TurnReport`

**Status:** [x] Complete

**Design task:** #28 (part 1 of 3)

**Files to create:**
- `app/Models/TurnReport.php` (use `php artisan make:class`)

**Files to modify:**
- `app/Models/Turn.php` — add `reports(): HasMany` relationship

**Model contract:**

```php
#[Fillable(['game_id', 'turn_id', 'empire_id', 'generated_at'])]
class TurnReport extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'generated_at' => 'datetime',
        ];
    }

    public function game(): BelongsTo     // → Game
    public function turn(): BelongsTo     // → Turn
    public function empire(): BelongsTo   // → Empire
    public function colonies(): HasMany   // → TurnReportColony
    public function surveys(): HasMany    // → TurnReportSurvey
}
```

**Inverse relationship to add on `Turn`:**

```php
/** @return HasMany<TurnReport, $this> */
public function reports(): HasMany
{
    return $this->hasMany(TurnReport::class);
}
```

**Tests:**
- Add to `tests/Feature/Reports/TurnReportModelTest.php` (create this file)
- `test_turn_report_casts_generated_at_to_carbon` — create via factory (Task F8), assert `generated_at` is a Carbon instance.
- `test_turn_report_belongs_to_game_turn_and_empire` — create, assert relationships resolve.
- `test_turn_report_has_many_colonies_and_surveys` — create parent with children, assert relation counts.

**Implementation notes:**
- Follow existing model conventions: `#[Fillable]` attribute, `casts()` method, PHPDoc on relationships.
- This task creates the model file only. The factory is created in Task F8.
- Model tests for this task can create records directly via DB insert or wait for the factory in Task F8. Either approach is acceptable — if waiting, mark the tests as part of Task F8's scope.

**Acceptance criteria:**
- [x] `TurnReport` model exists with exact fillable, casts, and relationships
- [x] `Turn::reports()` HasMany relationship exists
- [x] `$timestamps = false`
- [x] Tests pass: `php artisan test --compact --filter=TurnReportModelTest`

---

## Task F6 — Models: `TurnReportColony`, `TurnReportColonyInventory`, `TurnReportColonyPopulation`

**Status:** [x] Complete

**Design task:** #28 (part 2 of 3)

**Files to create:**
- `app/Models/TurnReportColony.php`
- `app/Models/TurnReportColonyInventory.php`
- `app/Models/TurnReportColonyPopulation.php`

**Model contract: `TurnReportColony`**

```php
#[Fillable(['turn_report_id', 'source_colony_id', 'name', 'kind', 'tech_level',
    'planet_id', 'orbit', 'star_x', 'star_y', 'star_z', 'star_sequence',
    'is_on_surface', 'rations', 'sol', 'birth_rate', 'death_rate'])]
class TurnReportColony extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'kind' => ColonyKind::class,
            'is_on_surface' => 'boolean',
            'rations' => 'float',
            'sol' => 'float',
            'birth_rate' => 'float',
            'death_rate' => 'float',
        ];
    }

    public function turnReport(): BelongsTo          // → TurnReport
    public function inventory(): HasMany             // → TurnReportColonyInventory
    public function population(): HasMany            // → TurnReportColonyPopulation
}
```

**Model contract: `TurnReportColonyInventory`**

```php
#[Fillable(['turn_report_colony_id', 'unit_code', 'tech_level', 'quantity_assembled', 'quantity_disassembled'])]
class TurnReportColonyInventory extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'unit_code' => UnitCode::class,
        ];
    }

    public function turnReportColony(): BelongsTo    // → TurnReportColony
}
```

**Model contract: `TurnReportColonyPopulation`**

```php
#[Fillable(['turn_report_colony_id', 'population_code', 'quantity', 'pay_rate', 'rebel_quantity'])]
class TurnReportColonyPopulation extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'population_code' => PopulationClass::class,
            'pay_rate' => 'float',
        ];
    }

    public function turnReportColony(): BelongsTo    // → TurnReportColony
}
```

**Tests:**
- Add to `tests/Feature/Reports/TurnReportModelTest.php`
- `test_turn_report_colony_casts_kind_to_colony_kind_enum` — create, assert `kind` is `ColonyKind` instance.
- `test_turn_report_colony_inventory_casts_unit_code_to_unit_code_enum` — create, assert.
- `test_turn_report_colony_population_casts_population_code_to_population_class_enum` — create, assert.
- `test_turn_report_colony_has_many_inventory_and_population` — create parent with children, assert counts.

**Acceptance criteria:**
- [x] All 3 models exist with exact fillable, casts, and relationships
- [x] `$timestamps = false` on all 3
- [x] Casts match the live model enum usage (`ColonyKind`, `UnitCode`, `PopulationClass`)
- [x] Tests pass: `php artisan test --compact --filter=TurnReportModelTest`

---

## Task F7 — Models: `TurnReportSurvey`, `TurnReportSurveyDeposit`

**Status:** [x] Complete

**Design task:** #28 (part 3 of 3)

**Files to create:**
- `app/Models/TurnReportSurvey.php`
- `app/Models/TurnReportSurveyDeposit.php`

**Model contract: `TurnReportSurvey`**

```php
#[Fillable(['turn_report_id', 'planet_id', 'orbit', 'star_x', 'star_y', 'star_z',
    'star_sequence', 'planet_type', 'habitability'])]
class TurnReportSurvey extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'planet_type' => PlanetType::class,
        ];
    }

    public function turnReport(): BelongsTo          // → TurnReport
    public function deposits(): HasMany              // → TurnReportSurveyDeposit
}
```

**Model contract: `TurnReportSurveyDeposit`**

```php
#[Fillable(['turn_report_survey_id', 'deposit_no', 'resource', 'yield_pct', 'quantity_remaining'])]
class TurnReportSurveyDeposit extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'resource' => DepositResource::class,
        ];
    }

    public function turnReportSurvey(): BelongsTo    // → TurnReportSurvey
}
```

**Tests:**
- Add to `tests/Feature/Reports/TurnReportModelTest.php`
- `test_turn_report_survey_casts_planet_type_to_planet_type_enum`
- `test_turn_report_survey_deposit_casts_resource_to_deposit_resource_enum`
- `test_turn_report_survey_has_many_deposits`

**Acceptance criteria:**
- [x] Both models exist with exact fillable, casts, and relationships
- [x] `$timestamps = false` on both
- [x] Casts use `PlanetType` and `DepositResource` enums
- [x] Tests pass: `php artisan test --compact --filter=TurnReportModelTest`

---

## Task F8 — Factories: `TurnReport` and colony report factories

**Status:** [x] Complete

**Design task:** #29 (part 1 of 2)

**Files to create:**
- `database/factories/TurnReportFactory.php`
- `database/factories/TurnReportColonyFactory.php`
- `database/factories/TurnReportColonyInventoryFactory.php`
- `database/factories/TurnReportColonyPopulationFactory.php`

**Factory definitions (follow existing factory style):**

**`TurnReportFactory`**
```php
return [
    'game_id' => Game::factory(),
    'turn_id' => Turn::factory(),
    'empire_id' => Empire::factory(),
    'generated_at' => now(),
];
```

**`TurnReportColonyFactory`**
```php
return [
    'turn_report_id' => TurnReport::factory(),
    'source_colony_id' => fake()->optional()->randomNumber(),
    'name' => fake()->words(2, true),
    'kind' => fake()->randomElement(ColonyKind::cases()),
    'tech_level' => fake()->numberBetween(1, 5),
    'planet_id' => fake()->optional()->randomNumber(),
    'orbit' => fake()->numberBetween(1, 10),
    'star_x' => fake()->numberBetween(1, 30),
    'star_y' => fake()->numberBetween(1, 30),
    'star_z' => fake()->numberBetween(1, 30),
    'star_sequence' => fake()->numberBetween(1, 4),
    'is_on_surface' => fake()->boolean(),
    'rations' => 1.0,
    'sol' => 0.0,
    'birth_rate' => 0.0,
    'death_rate' => 0.0,
];
```

**`TurnReportColonyInventoryFactory`**
```php
return [
    'turn_report_colony_id' => TurnReportColony::factory(),
    'unit_code' => fake()->randomElement(UnitCode::cases()),
    'tech_level' => fake()->numberBetween(1, 5),
    'quantity_assembled' => fake()->numberBetween(0, 1000),
    'quantity_disassembled' => fake()->numberBetween(0, 100),
];
```

**`TurnReportColonyPopulationFactory`**
```php
return [
    'turn_report_colony_id' => TurnReportColony::factory(),
    'population_code' => fake()->randomElement(PopulationClass::cases()),
    'quantity' => fake()->numberBetween(1, 1000000),
    'pay_rate' => fake()->randomFloat(3, 0, 10),
    'rebel_quantity' => 0,
];
```

**Implementation notes:**
- Use enum cases directly (not `->value`) — this matches existing factory style (see `ColonyFactory`, `ColonyInventoryFactory`).
- Each factory needs `/** @extends Factory<ModelClass> */` PHPDoc and `use HasFactory` trait on the model.
- Ensure each model has `use HasFactory;` if not already added in Tasks F5–F7.

**Tests:**
- Add to `tests/Feature/Reports/TurnReportFactoryTest.php` (create this file)
- `test_turn_report_factory_creates_persisted_model` — `TurnReport::factory()->create()`, assert model exists.
- `test_turn_report_colony_factory_creates_persisted_model` — same pattern.
- `test_turn_report_colony_inventory_factory_creates_with_enum_cast` — create, assert `unit_code` is `UnitCode` instance.
- `test_turn_report_colony_population_factory_creates_with_enum_cast` — create, assert `population_code` is `PopulationClass` instance.

**Acceptance criteria:**
- [x] All 4 factories exist and follow existing factory conventions
- [x] `TurnReport::factory()->create()` produces a persisted record
- [x] Enum-casted attributes hydrate correctly from factory values
- [x] Tests pass: `php artisan test --compact --filter=TurnReportFactoryTest`

---

## Task F9 — Factories: survey report factories

**Status:** [x] Complete

**Design task:** #29 (part 2 of 2)

**Files to create:**
- `database/factories/TurnReportSurveyFactory.php`
- `database/factories/TurnReportSurveyDepositFactory.php`

**Factory definitions:**

**`TurnReportSurveyFactory`**
```php
return [
    'turn_report_id' => TurnReport::factory(),
    'planet_id' => fake()->optional()->randomNumber(),
    'orbit' => fake()->numberBetween(1, 10),
    'star_x' => fake()->numberBetween(1, 30),
    'star_y' => fake()->numberBetween(1, 30),
    'star_z' => fake()->numberBetween(1, 30),
    'star_sequence' => fake()->numberBetween(1, 4),
    'planet_type' => fake()->randomElement(PlanetType::cases()),
    'habitability' => fake()->numberBetween(0, 25),
];
```

**`TurnReportSurveyDepositFactory`**
```php
return [
    'turn_report_survey_id' => TurnReportSurvey::factory(),
    'deposit_no' => fake()->numberBetween(1, 10),
    'resource' => fake()->randomElement(DepositResource::cases()),
    'yield_pct' => fake()->numberBetween(1, 100),
    'quantity_remaining' => fake()->numberBetween(100, 10000),
];
```

**Tests:**
- Add to `tests/Feature/Reports/TurnReportFactoryTest.php`
- `test_turn_report_survey_factory_creates_with_planet_type_enum` — create, assert `planet_type` is `PlanetType` instance.
- `test_turn_report_survey_deposit_factory_creates_with_deposit_resource_enum` — create, assert `resource` is `DepositResource` instance.

**Acceptance criteria:**
- [x] Both factories exist and follow existing conventions
- [x] Enum casts hydrate correctly
- [x] Tests pass: `php artisan test --compact --filter=TurnReportFactoryTest`

---

## Task F10 — Service: `SetupReportGenerator` — atomic status transition and skeleton

**Status:** [x] Complete

**Design task:** #30 (part 1 of 3)

**Files to create:**
- `app/Services/SetupReportGenerator.php`
- `tests/Feature/Services/SetupReportGeneratorTest.php`

**Public API:**

```php
namespace App\Services;

class SetupReportGenerator
{
    /**
     * Generate setup reports for all empires with colonies.
     *
     * @throws \RuntimeException if the turn is not available for report generation.
     */
    public function generate(Turn $turn): int
}
```

**Implementation — this task only:**

1. Wrap the entire method body in `DB::transaction(...)`.

2. Perform atomic status transition using a single guarded update:
   ```php
   $updated = Turn::where('id', $turn->id)
       ->whereNull('reports_locked_at')
       ->whereIn('status', [TurnStatus::Pending, TurnStatus::Completed])
       ->update(['status' => TurnStatus::Generating]);

   if ($updated === 0) {
       throw new \RuntimeException('Turn is not available for report generation.');
   }
   ```

3. Reload the turn after the update: `$turn = Turn::findOrFail($turn->id);`

4. Eager-load empires with colonies and all needed relations:
   ```php
   $empires = Empire::where('game_id', $turn->game_id)
       ->whereHas('colonies')
       ->with([
           'colonies' => fn ($q) => $q->orderBy('id'),
           'colonies.planet.star',
           'colonies.inventory',
           'colonies.population',
           'homeSystem.homeworldPlanet.star',
           'homeSystem.homeworldPlanet.deposits' => fn ($q) => $q->orderBy('id'),
       ])
       ->orderBy('id')
       ->get();
   ```

5. Create placeholder for empire processing loop (Tasks F11–F12 will fill this in):
   ```php
   $generatedAt = now();

   foreach ($empires as $empire) {
       // Tasks F11 and F12 will implement snapshotEmpire()
   }
   ```

6. After the loop, set turn status to `completed`:
   ```php
   $turn->update(['status' => TurnStatus::Completed]);
   ```

7. Return `$empires->count()`.

**Tests:**
- Create `tests/Feature/Services/SetupReportGeneratorTest.php` with `LazilyRefreshDatabase`, `#[Test]` attributes.
- Use a helper method `activeGameWithEmpire()` that creates the full game setup (similar to `EmpireCreatorTest::activeGameWithHomeSystem()` but also creates an empire with a colony). This helper should:
  - Create a game and run star/planet/deposit generation
  - Create home system template + colony template with inventory and population
  - Create a home system
  - Activate the game (sets status to Active, creates Turn 0)
  - Create a player, assign an empire via `EmpireCreator`
  - Return the game

- `test_generate_allows_pending_turn` — fresh game with pending Turn 0, call `generate()`, assert no exception, turn status is `completed`.
- `test_generate_allows_completed_turn_rerun` — set Turn 0 status to `completed`, call `generate()` again, assert success.
- `test_generate_rejects_generating_turn` — set Turn 0 status to `generating`, call `generate()`, assert `RuntimeException`.
- `test_generate_rejects_closed_turn` — set Turn 0 status to `closed`, assert `RuntimeException`.
- `test_generate_rejects_locked_turn` — set `reports_locked_at` to `now()`, assert `RuntimeException`.

**Acceptance criteria:**
- [ ] `SetupReportGenerator` class exists with `generate(Turn $turn): int`
- [ ] Atomic status transition uses a single guarded update (not `findOrFail` then check)
- [ ] Full operation runs inside one `DB::transaction()`
- [ ] Pending and completed turns are accepted
- [ ] Generating, closed, and locked turns are rejected with `RuntimeException`
- [ ] Tests pass: `php artisan test --compact --filter=SetupReportGeneratorTest`

---

## Task F11 — Service: colony/inventory/population snapshots

**Status:** [x] Complete

**Design task:** #30 (part 2 of 3)

**Files to modify:**
- `app/Services/SetupReportGenerator.php`

**Implementation — add to the empire processing loop:**

For each empire:

1. **Delete existing report** for `(turn_id, empire_id)` — cascade deletes handle all children:
   ```php
   TurnReport::where('turn_id', $turn->id)
       ->where('empire_id', $empire->id)
       ->delete();
   ```

2. **Create report header:**
   ```php
   $report = TurnReport::create([
       'game_id' => $turn->game_id,
       'turn_id' => $turn->id,
       'empire_id' => $empire->id,
       'generated_at' => $generatedAt,
   ]);
   ```

3. **Snapshot each colony** into `turn_report_colonies`:
   ```php
   $star = $colony->planet->star;
   $reportColony = $report->colonies()->create([
       'source_colony_id' => $colony->id,
       'name' => $colony->name,
       'kind' => $colony->kind,
       'tech_level' => $colony->tech_level,
       'planet_id' => $colony->planet_id,
       'orbit' => $colony->planet->orbit,
       'star_x' => $star->x,
       'star_y' => $star->y,
       'star_z' => $star->z,
       'star_sequence' => $star->sequence,
       'is_on_surface' => $colony->is_on_surface,
       'rations' => $colony->rations,
       'sol' => $colony->sol,
       'birth_rate' => $colony->birth_rate,
       'death_rate' => $colony->death_rate,
   ]);
   ```

4. **Snapshot inventory** for each colony:
   ```php
   foreach ($colony->inventory as $item) {
       $reportColony->inventory()->create([
           'unit_code' => $item->unit,
           'tech_level' => $item->tech_level,
           'quantity_assembled' => $item->quantity_assembled,
           'quantity_disassembled' => $item->quantity_disassembled,
       ]);
   }
   ```

5. **Snapshot population** for each colony:
   ```php
   foreach ($colony->population as $pop) {
       $reportColony->population()->create([
           'population_code' => $pop->population_code,
           'quantity' => $pop->quantity,
           'pay_rate' => $pop->pay_rate,
           'rebel_quantity' => $pop->rebel_quantity,
       ]);
   }
   ```

**Implementation notes:**
- Use `create()` not `insert()` — report volumes are small and this preserves enum cast safety.
- The `$colony->planet->star` is already eager-loaded. No N+1 risk.
- If a colony's `planet` or `star` relation is null, the eager-load guarantees this won't happen for valid data. Let it fail naturally (null dereference) if the data is corrupt — the transaction will roll back.

**Tests:**
- Add to `tests/Feature/Services/SetupReportGeneratorTest.php`
- `test_generate_creates_one_report_per_empire_with_colonies` — create 2 empires (one with colony, one without), call `generate()`, assert 1 `turn_reports` row, return value is `1`.
- `test_generate_snapshots_colony_with_denormalized_star_coordinates` — call `generate()`, load the `TurnReportColony`, assert `star_x`, `star_y`, `star_z`, `star_sequence`, `orbit` match the live colony's planet/star.
- `test_generate_snapshots_colony_inventory` — assert `turn_report_colony_inventory` rows match live inventory count, `unit_code`, quantities.
- `test_generate_snapshots_colony_population` — assert `turn_report_colony_population` rows match live population.
- `test_generate_rerun_replaces_existing_report_data` — call `generate()` twice, assert only 1 report exists (delete-and-recreate), and data is fresh.
- `test_generate_skips_empires_without_colonies` — create an empire with no colonies, assert no report is created for it.

**Acceptance criteria:**
- [ ] Existing report is deleted before recreation (idempotent)
- [ ] One report header per eligible empire is created
- [ ] Colony snapshot includes all denormalized fields (orbit, star coords)
- [ ] Inventory rows are copied with correct `unit_code` and quantities
- [ ] Population rows are copied with correct `population_code`, `quantity`, `pay_rate`, `rebel_quantity`
- [ ] Empires without colonies are skipped
- [ ] Rerun produces identical results (idempotent)
- [ ] Tests pass: `php artisan test --compact --filter=SetupReportGeneratorTest`

---

## Task F12 — Service: homeworld survey + deposits + final status

**Status:** [x] Complete

**Design task:** #30 (part 3 of 3)

**Files to modify:**
- `app/Services/SetupReportGenerator.php`

**Implementation — add to the empire processing loop, after colony snapshots:**

1. **Get homeworld** from the empire's home system:
   ```php
   $homeworld = $empire->homeSystem->homeworldPlanet;
   $homeworldStar = $homeworld->star;
   ```

2. **Create survey** entry for the homeworld:
   ```php
   $survey = $report->surveys()->create([
       'planet_id' => $homeworld->id,
       'orbit' => $homeworld->orbit,
       'star_x' => $homeworldStar->x,
       'star_y' => $homeworldStar->y,
       'star_z' => $homeworldStar->z,
       'star_sequence' => $homeworldStar->sequence,
       'planet_type' => $homeworld->type,
       'habitability' => $homeworld->habitability,
   ]);
   ```

3. **Snapshot all homeworld deposits** with 1-based `deposit_no`:
   ```php
   $homeworld->deposits->values()->each(function ($deposit, $index) use ($survey) {
       $survey->deposits()->create([
           'deposit_no' => $index + 1,
           'resource' => $deposit->resource,
           'yield_pct' => $deposit->yield_pct,
           'quantity_remaining' => $deposit->quantity_remaining,
       ]);
   });
   ```

4. **Verify final status:** After the empire loop completes, `$turn->update(['status' => TurnStatus::Completed])` should already be in place from Task F10. Verify it's still at the right position (after the loop, before the return).

**Implementation notes:**
- Deposits are ordered by `id` (from the eager-load in Task F10), so `deposit_no` assignment is deterministic.
- The homeworld is the empire's `homeSystem->homeworldPlanet`, **not** the colony's planet (though they are the same for setup reports). Use the canonical source.
- Only empires with colonies are processed — this was already enforced by the `whereHas('colonies')` scope in Task F10.

**Tests:**
- Add to `tests/Feature/Services/SetupReportGeneratorTest.php`
- `test_generate_creates_homeworld_survey_with_correct_planet_data` — call `generate()`, load the `TurnReportSurvey`, assert `planet_id`, `orbit`, `star_x/y/z`, `star_sequence`, `planet_type`, `habitability` match the homeworld.
- `test_generate_snapshots_all_homeworld_deposits_with_one_based_deposit_numbers` — create 3 deposits on homeworld, call `generate()`, assert 3 `turn_report_survey_deposits` rows with `deposit_no` 1, 2, 3.
- `test_generate_marks_turn_completed_on_success` — call `generate()`, assert `$turn->fresh()->status === TurnStatus::Completed`.
- `test_generate_returns_count_of_processed_empires` — create 2 empires with colonies and 1 without, call `generate()`, assert return value is `2`.

**Acceptance criteria:**
- [ ] One homeworld survey per processed empire is created
- [ ] Survey contains correct homeworld planet data and denormalized star coords
- [ ] All homeworld deposits are copied with deterministic 1-based `deposit_no`
- [ ] `deposit_no` ordering follows deposit `id` order
- [ ] Turn status is `completed` after successful generation
- [ ] Return value equals the number of empires with colonies
- [ ] Tests pass: `php artisan test --compact --filter=SetupReportGeneratorTest`

---

## Task F13 — Formatting and full test suite verification

**Status:** [x] Complete

**Steps:**

1. Run Pint on all modified/created PHP files:
   ```bash
   vendor/bin/pint --dirty --format agent
   ```

2. Run all Group F tests:
   ```bash
   php artisan test --compact --filter=TurnReportSchemaTest
   php artisan test --compact --filter=TurnReportModelTest
   php artisan test --compact --filter=TurnReportFactoryTest
   php artisan test --compact --filter=SetupReportGeneratorTest
   ```

3. Run the full test suite:
   ```bash
   php artisan test --compact
   ```

**Acceptance criteria:**
- [ ] No Pint violations
- [ ] All Group F tests pass
- [ ] Full test suite passes (no regressions from Groups A–E)

---

## Group F Acceptance Criteria

Group F is complete when **all** of the following are true:

- [ ] **1. Report schema:** Five report tables exist (`turn_reports`, `turn_report_colonies`, `turn_report_colony_inventory`, `turn_report_colony_population`, `turn_report_surveys`, `turn_report_survey_deposits`) with correct columns, constraints, and cascade behavior.

- [ ] **2. Snapshot FK strategy:** `source_colony_id` and `planet_id` on snapshot tables are plain nullable integers, not foreign keys. Historical reports survive if live entities are deleted.

- [ ] **3. Report models:** Six Eloquent models exist with correct `#[Fillable]`, `casts()`, `$timestamps = false`, and relationship methods. Enum casts match the live model usage (`ColonyKind`, `UnitCode`, `PopulationClass`, `PlanetType`, `DepositResource`).

- [ ] **4. Factories:** Six factory classes exist following existing conventions. All produce valid persisted records with correctly hydrating enum casts.

- [ ] **5. Atomic status transition:** `SetupReportGenerator::generate()` uses a single guarded `UPDATE` to transition turn status from `pending`/`completed` to `generating`. It rejects `generating`, `closed`, and locked turns with `RuntimeException`.

- [ ] **6. Report generation:** The service creates one report per empire with colonies. Each report contains colony snapshots with denormalized star/planet data, inventory snapshots, population snapshots, a homeworld survey, and homeworld deposit snapshots with 1-based `deposit_no`.

- [ ] **7. Idempotency:** Re-running the generator deletes existing reports and recreates them. The delete-and-recreate pattern via cascade ensures clean results.

- [ ] **8. Completion:** Turn status is set to `completed` after successful generation. The return value is the number of empires processed.

- [ ] **9. No-colony handling:** Empires without colonies are skipped and not counted.

- [ ] **10. Scope control:** No controllers, routes, policies, or frontend changes. No modifications to existing live models except the `Turn::reports()` relationship.

- [ ] **11. Verification:** Pint clean. All Group F tests pass. Full test suite passes with no regressions.
