# Layer 1 / Group C — New Models and Factories

## Scope

This burndown covers **Layer 1, Group C** from `docs/SETUP_REPORT.md`: creating the new Eloquent models and factories
for `ColonyPopulation`, `ColonyTemplatePopulation`, and `Turn`, plus wiring their relationships into existing models.

1. Create `ColonyPopulation` model and add `Colony::population()` relationship
2. Create `ColonyTemplatePopulation` model and add `ColonyTemplate::population()` relationship
3. Create `Turn` model with `TurnStatus` cast and `Game` relationship
4. Add `Game::turns()`, `Game::currentTurn()` relationships and `Game::canGenerateReports()` helper
5. Create `TurnFactory`
6. Create `ColonyPopulationFactory` and `ColonyTemplatePopulationFactory`

---

## Global Guardrails

- **Order is fixed:** complete tasks C14a → C14b → C15 → C16 → C17a → C17b in sequence.
- **Use PHPUnit only** (not Pest).
- Match existing repo conventions:
  - `#[Fillable([...])]` attribute for mass assignment
  - `/** @use HasFactory<XxxFactory> */` PHPDoc on the trait
  - Explicit relationship PHPDoc return types: `/** @return BelongsTo<X, $this> */`
  - Casts via `protected function casts(): array` (see `Game` model for the pattern)
  - Use `fake()->...` in factories, not `$this->faker`
- **Assume Groups A and B are complete.** All migrations have been applied. All existing models and factories use
  string-backed enum casts.
- **Run after every PHP task:** `vendor/bin/pint --dirty --format agent`

---

## Critical Risks and Guardrails

### 1. Wrong table names (most likely bug)

Laravel auto-pluralizes model names to derive table names:
- `ColonyPopulation` → `colony_populations` (wrong — actual table is `colony_population`)
- `ColonyTemplatePopulation` → `colony_template_populations` (wrong — actual table is `colony_template_population`)

**Guardrail:** Both population models **must** set `protected $table` explicitly.

### 2. Wrong timestamp behavior

The `colony_population` and `colony_template_population` tables have **no** `created_at` / `updated_at` columns.

**Guardrail:** Both population models **must** set `public $timestamps = false;`. The `Turn` model **must not** disable
timestamps — the `turns` table has both timestamp columns.

### 3. Using the wrong "active" signal in `canGenerateReports()`

`Game::isActive()` checks the `status` enum (`GameStatus::Active`), **not** the `is_active` boolean column.

**Guardrail:** In tests for `canGenerateReports()`, always create games with:
```php
['status' => GameStatus::Active]
```

### 4. Unique constraint collisions in tests

Both population tables have composite unique keys (`colony_id + population_code` and
`colony_template_id + population_code`).

**Guardrail:** When creating multiple population rows for the same parent in a single test, assign **distinct**
`population_code` values.

---

## Current File State (Post–Groups A & B)

### Existing models (already updated)

- **`Colony`** — `#[Fillable]` includes new columns; casts `kind` → `ColonyKind`, `is_on_surface` → `boolean`,
  floats for `rations`, `sol`, `birth_rate`, `death_rate`; has `empire()`, `planet()`, `inventory()` relationships;
  `$timestamps = false`
- **`ColonyTemplate`** — casts `kind` → `ColonyKind`; has `game()`, `items()` relationships
- **`ColonyInventory`** — casts `unit` → `UnitCode`; `$table = 'colony_inventory'`; `$timestamps = false`
- **`ColonyTemplateItem`** — casts `unit` → `UnitCode`; `$timestamps = false`
- **`Game`** — casts `status` → `GameStatus`; has `isActive()` and other status helpers; has `homeSystemTemplate()`,
  `colonyTemplate()`, `stars()`, `planets()`, `deposits()`, `homeSystems()`, `empires()`, `generationSteps()`,
  `playerRecords()`, `users()`, `gms()`, `players()` relationships and capability helpers

### Existing factories (already updated)

- `ColonyFactory` — uses `ColonyKind::OpenSurface`, includes all new column defaults
- `ColonyTemplateFactory` — uses `ColonyKind::OpenSurface`
- `ColonyInventoryFactory` — uses `UnitCode` enum cases
- `ColonyTemplateItemFactory` — uses `UnitCode` enum cases

### Existing migrations (from Group A)

- `colony_population` — `id`, `colony_id` FK, `population_code` string, `quantity` int, `pay_rate` float,
  `rebel_quantity` int default 0; unique `(colony_id, population_code)`; no timestamps
- `colony_template_population` — `id`, `colony_template_id` FK, `population_code` string, `quantity` int,
  `pay_rate` float; unique `(colony_template_id, population_code)`; no timestamps
- `turns` — `id`, `game_id` FK, `number` int, `status` string default `'pending'`, `reports_locked_at` datetime
  nullable, `created_at`, `updated_at`; unique `(game_id, number)`

### Enums (from Group A)

- `TurnStatus`: `Pending='pending'`, `Generating='generating'`, `Completed='completed'`, `Closed='closed'`
- `PopulationClass`: `Unemployable='UEM'`, `Unskilled='USK'`, `Professional='PRO'`, `Soldier='SLD'`,
  `ConstructionWorker='CNW'`, `Spy='SPY'`, `Police='PLC'`, `SpecialAgent='SAG'`, `Trainee='TRN'`
- `ColonyKind`: `OpenSurface='COPN'`, `Enclosed='CENC'`, `Orbital='CORB'`
- `UnitCode`: 30 cases (`AUT`, `ESH`, `EWP`, `FCT`, `FRM`, … `RSCH`)

---

## Tasks

---

### Task C14a — Create `ColonyPopulation` model and wire `Colony::population()`

**Status:** DONE
**Effort:** S
**Depends on:** Groups A & B complete

#### Files to create

- `app/Models/ColonyPopulation.php`
- `tests/Feature/Models/ColonyPopulationModelTest.php`

#### Files to modify

- `app/Models/Colony.php` — add `population()` relationship

#### Commands

```bash
php artisan make:test --phpunit Models/ColonyPopulationModelTest
vendor/bin/pint --dirty --format agent
php artisan test --compact tests/Feature/Models/ColonyPopulationModelTest.php
```

#### Implementation — `app/Models/ColonyPopulation.php`

```php
<?php

namespace App\Models;

use App\Enums\PopulationClass;
use Database\Factories\ColonyPopulationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['colony_id', 'population_code', 'quantity', 'pay_rate', 'rebel_quantity'])]
class ColonyPopulation extends Model
{
    /** @use HasFactory<ColonyPopulationFactory> */
    use HasFactory;

    protected $table = 'colony_population';

    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'population_code' => PopulationClass::class,
            'pay_rate' => 'float',
        ];
    }

    /** @return BelongsTo<Colony, $this> */
    public function colony(): BelongsTo
    {
        return $this->belongsTo(Colony::class);
    }
}
```

**Key details:**
- `$table = 'colony_population'` — required because Laravel would default to `colony_populations`
- `$timestamps = false` — table has no timestamp columns
- `population_code` cast to `PopulationClass` enum
- `pay_rate` cast to `float`

#### Implementation — `app/Models/Colony.php` edit

Add the following import and relationship method:

```php
use App\Models\ColonyPopulation;

// Add to existing relationship methods:

/** @return HasMany<ColonyPopulation, $this> */
public function population(): HasMany
{
    return $this->hasMany(ColonyPopulation::class);
}
```

#### Test requirements — `tests/Feature/Models/ColonyPopulationModelTest.php`

1. **`test_it_uses_the_colony_population_table`** — create a `ColonyPopulation` record, assert row exists in
   `colony_population` table using `assertDatabaseHas`
2. **`test_it_casts_population_code_to_population_class_enum`** — create with
   `population_code => PopulationClass::Unskilled`, refresh, assert `$record->population_code === PopulationClass::Unskilled`
3. **`test_it_stores_enum_backing_value_in_database`** — create with `PopulationClass::Soldier`, assert
   `assertDatabaseHas('colony_population', ['population_code' => 'SLD'])`
4. **`test_it_casts_pay_rate_to_float`** — create with `pay_rate => 0.125`, refresh, assert `is_float($record->pay_rate)`
   and value equals `0.125`
5. **`test_it_belongs_to_a_colony`** — create a `ColonyPopulation`, assert `$record->colony` is an instance of `Colony`
6. **`test_colony_has_population_relationship`** — create a `Colony` then create two `ColonyPopulation` records with
   distinct `population_code` values, assert `$colony->population` returns a collection of count 2
7. **`test_it_defaults_rebel_quantity_to_zero`** — create via `ColonyPopulation::create()` without specifying
   `rebel_quantity`, assert the stored value is `0`

Create the `Colony` and `Planet`/`Empire` explicitly or use `Colony::factory()` (factory is updated). For
`ColonyPopulation`, create records directly via `ColonyPopulation::create()` since the factory doesn't exist yet.

#### Done when

- `ColonyPopulation` model reads from/writes to `colony_population` table
- `population_code` casts to `PopulationClass` enum
- `ColonyPopulation->colony` resolves the parent colony
- `Colony->population` returns the related population records
- No timestamp-related SQL errors on insert
- Targeted test passes and Pint reports no issues

---

### Task C14b — Create `ColonyTemplatePopulation` model and wire `ColonyTemplate::population()`

**Status:** DONE
**Effort:** S
**Depends on:** C14a (for pattern reference)

#### Files to create

- `app/Models/ColonyTemplatePopulation.php`
- `tests/Feature/Models/ColonyTemplatePopulationModelTest.php`

#### Files to modify

- `app/Models/ColonyTemplate.php` — add `population()` relationship

#### Commands

```bash
php artisan make:test --phpunit Models/ColonyTemplatePopulationModelTest
vendor/bin/pint --dirty --format agent
php artisan test --compact tests/Feature/Models/ColonyTemplatePopulationModelTest.php
```

#### Implementation — `app/Models/ColonyTemplatePopulation.php`

```php
<?php

namespace App\Models;

use App\Enums\PopulationClass;
use Database\Factories\ColonyTemplatePopulationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['colony_template_id', 'population_code', 'quantity', 'pay_rate'])]
class ColonyTemplatePopulation extends Model
{
    /** @use HasFactory<ColonyTemplatePopulationFactory> */
    use HasFactory;

    protected $table = 'colony_template_population';

    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'population_code' => PopulationClass::class,
            'pay_rate' => 'float',
        ];
    }

    /** @return BelongsTo<ColonyTemplate, $this> */
    public function colonyTemplate(): BelongsTo
    {
        return $this->belongsTo(ColonyTemplate::class);
    }
}
```

**Key details:**
- `$table = 'colony_template_population'` — required because Laravel would default to `colony_template_populations`
- `$timestamps = false` — table has no timestamp columns
- No `rebel_quantity` column — this table doesn't have it (unlike `colony_population`)

#### Implementation — `app/Models/ColonyTemplate.php` edit

Add the following import and relationship method:

```php
use App\Models\ColonyTemplatePopulation;

// Add to existing relationship methods:

/** @return HasMany<ColonyTemplatePopulation, $this> */
public function population(): HasMany
{
    return $this->hasMany(ColonyTemplatePopulation::class);
}
```

Also add the `HasMany` import if not already present.

#### Test requirements — `tests/Feature/Models/ColonyTemplatePopulationModelTest.php`

1. **`test_it_uses_the_colony_template_population_table`** — create a record, assert
   `assertDatabaseHas('colony_template_population', [...])`
2. **`test_it_casts_population_code_to_population_class_enum`** — create with
   `PopulationClass::Professional`, refresh, assert enum cast
3. **`test_it_stores_enum_backing_value_in_database`** — assert raw DB stores `'PRO'`
4. **`test_it_casts_pay_rate_to_float`** — create with `pay_rate => 0.375`, assert `is_float` after refresh
5. **`test_it_belongs_to_a_colony_template`** — assert `$record->colonyTemplate` is a `ColonyTemplate`
6. **`test_colony_template_has_population_relationship`** — create a `ColonyTemplate` then two
   `ColonyTemplatePopulation` records with distinct codes, assert `$template->population->count() === 2`

Create records directly via `ColonyTemplatePopulation::create()` since the factory doesn't exist yet.

#### Done when

- `ColonyTemplatePopulation` model reads from/writes to `colony_template_population` table
- `population_code` casts to `PopulationClass` enum
- `ColonyTemplatePopulation->colonyTemplate` resolves the parent
- `ColonyTemplate->population` returns the related population records
- No timestamp-related SQL errors on insert
- Targeted test passes and Pint reports no issues

---

### Task C15 — Create `Turn` model

**Status:** DONE
**Effort:** S
**Depends on:** Groups A & B complete

#### Files to create

- `app/Models/Turn.php`
- `tests/Feature/Models/TurnModelTest.php`

#### Commands

```bash
php artisan make:test --phpunit Models/TurnModelTest
vendor/bin/pint --dirty --format agent
php artisan test --compact tests/Feature/Models/TurnModelTest.php
```

#### Implementation — `app/Models/Turn.php`

```php
<?php

namespace App\Models;

use App\Enums\TurnStatus;
use Database\Factories\TurnFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['game_id', 'number', 'status', 'reports_locked_at'])]
class Turn extends Model
{
    /** @use HasFactory<TurnFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => TurnStatus::class,
            'reports_locked_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Game, $this> */
    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }
}
```

**Key details:**
- No `$table` needed — `turns` matches Laravel convention
- Do **not** set `$timestamps = false` — the `turns` table has `created_at` and `updated_at`
- `status` casts to `TurnStatus` enum
- `reports_locked_at` casts to `datetime` (returns `Carbon` instance or `null`)

#### Test requirements — `tests/Feature/Models/TurnModelTest.php`

1. **`test_it_casts_status_to_turn_status_enum`** — create a `Turn` with `status => TurnStatus::Pending`, refresh,
   assert `$turn->status === TurnStatus::Pending`
2. **`test_it_stores_status_enum_backing_value_in_database`** — assert
   `assertDatabaseHas('turns', ['status' => 'pending'])`
3. **`test_it_casts_reports_locked_at_to_datetime`** — create with `reports_locked_at => now()`, refresh, assert
   `$turn->reports_locked_at` is an instance of `\Illuminate\Support\Carbon`
4. **`test_reports_locked_at_is_nullable`** — create without `reports_locked_at`, assert it is `null`
5. **`test_it_belongs_to_a_game`** — create a `Turn`, assert `$turn->game` is a `Game` instance
6. **`test_unique_constraint_on_game_id_and_number`** — create a turn for a game with number 0, attempt to create
   another turn with the same game_id and number, assert exception is thrown

Create the `Game` explicitly via `Game::factory()->create()`. Create `Turn` records via `Turn::create()` since the
factory doesn't exist yet.

#### Done when

- `Turn` model persists to the `turns` table
- `status` casts to `TurnStatus` enum
- `reports_locked_at` returns `Carbon|null`
- `Turn->game` relationship works
- Unique constraint enforced on `(game_id, number)`
- Targeted test passes and Pint reports no issues

---

### Task C16 — Add `Game::turns()`, `Game::currentTurn()`, and `Game::canGenerateReports()`

**Status:** DONE
**Effort:** S
**Depends on:** C15

#### Files to modify

- `app/Models/Game.php`

#### Files to create

- `tests/Feature/Models/GameTurnRelationshipTest.php`

#### Commands

```bash
php artisan make:test --phpunit Models/GameTurnRelationshipTest
vendor/bin/pint --dirty --format agent
php artisan test --compact tests/Feature/Models/GameTurnRelationshipTest.php
```

#### Implementation — `app/Models/Game.php` edits

Add the following imports at the top of the file:

```php
use App\Enums\TurnStatus;
use App\Models\Turn;
```

Add these three methods to the `Game` model, placing relationships alongside the existing relationship methods and
the capability helper alongside the existing `can*` helpers:

```php
/** @return HasMany<Turn, $this> */
public function turns(): HasMany
{
    return $this->hasMany(Turn::class)->orderBy('number');
}

/** @return HasOne<Turn, $this> */
public function currentTurn(): HasOne
{
    return $this->hasOne(Turn::class)->latestOfMany('number');
}

public function canGenerateReports(): bool
{
    $currentTurn = $this->currentTurn;

    return $this->isActive()
        && $currentTurn !== null
        && $currentTurn->reports_locked_at === null
        && $currentTurn->status !== TurnStatus::Generating;
}
```

**Key details:**
- `turns()` returns `HasMany` ordered ascending by `number`
- `currentTurn()` uses `latestOfMany('number')` to get the highest-numbered turn
- `canGenerateReports()` checks four conditions per the design doc:
  1. Game is active (`status === GameStatus::Active`)
  2. A current turn exists
  3. Reports are not locked (`reports_locked_at` is null)
  4. Turn is not currently generating (`status !== TurnStatus::Generating`)

#### Test requirements — `tests/Feature/Models/GameTurnRelationshipTest.php`

**Relationship tests:**

1. **`test_game_turns_relationship_returns_turns`** — create a `Game`, create two turns, assert
   `$game->turns->count() === 2`
2. **`test_game_turns_are_ordered_by_number`** — create turns with numbers 2, 0, 1 (out of order), assert
   `$game->turns->pluck('number')->all() === [0, 1, 2]`
3. **`test_game_current_turn_returns_highest_turn_number`** — create turns 0, 1, 2 for a game, assert
   `$game->currentTurn->number === 2`
4. **`test_game_current_turn_returns_null_when_no_turns_exist`** — create a game with no turns, assert
   `$game->currentTurn` is null

**`canGenerateReports()` tests:**

5. **`test_can_generate_reports_returns_true_for_active_game_with_pending_turn`** — active game, turn 0 with status
   `Pending`, not locked → returns `true`
6. **`test_can_generate_reports_returns_true_for_active_game_with_completed_turn`** — active game, turn 0 with status
   `Completed`, not locked → returns `true`
7. **`test_can_generate_reports_returns_false_when_game_is_not_active`** — game with
   `status => GameStatus::Setup`, turn 0 pending → returns `false`
8. **`test_can_generate_reports_returns_false_when_no_turns_exist`** — active game, no turns → returns `false`
9. **`test_can_generate_reports_returns_false_when_turn_is_locked`** — active game, turn 0 with
   `reports_locked_at => now()` → returns `false`
10. **`test_can_generate_reports_returns_false_when_turn_is_generating`** — active game, turn 0 with
    `status => TurnStatus::Generating` → returns `false`
11. **`test_can_generate_reports_returns_false_when_turn_is_closed`** — active game, turn 0 with
    `status => TurnStatus::Closed` → returns `false`

**Important test setup notes:**
- Always set game status explicitly: `Game::factory()->create(['status' => GameStatus::Active])`
- Create turns via `Turn::create()` with explicit attributes — don't rely on `TurnFactory` defaults (it exists by now
  but being explicit is clearer for these tests)
- For test 3, create turns out of insertion order to prove `latestOfMany` works by number, not by ID

#### Done when

- `Game->turns` returns turns ordered ascending by `number`
- `Game->currentTurn` returns the turn with the highest `number`
- `canGenerateReports()` returns `true` only when all four conditions from the design doc are met
- All 11 tests pass
- Pint reports no issues

---

### Task C17a — Create `TurnFactory`

**Status:** DONE
**Effort:** S
**Depends on:** C15

#### Files to create

- `database/factories/TurnFactory.php`
- `tests/Feature/Database/Factories/TurnFactoryTest.php`

#### Commands

```bash
php artisan make:test --phpunit Database/Factories/TurnFactoryTest
vendor/bin/pint --dirty --format agent
php artisan test --compact tests/Feature/Database/Factories/TurnFactoryTest.php
```

#### Implementation — `database/factories/TurnFactory.php`

```php
<?php

namespace Database\Factories;

use App\Enums\TurnStatus;
use App\Models\Game;
use App\Models\Turn;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Turn>
 */
class TurnFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'game_id' => Game::factory(),
            'number' => 0,
            'status' => TurnStatus::Pending,
            'reports_locked_at' => null,
        ];
    }
}
```

**Key details:**
- Default `number` is `0` (setup turn) — matches the most common use case for Layer 1
- Default `status` is `TurnStatus::Pending` — matching the migration default
- `reports_locked_at` is `null` by default

#### Test requirements — `tests/Feature/Database/Factories/TurnFactoryTest.php`

1. **`test_factory_creates_a_valid_turn`** — `Turn::factory()->create()` does not throw
2. **`test_factory_defaults_number_to_zero`** — assert `$turn->number === 0`
3. **`test_factory_defaults_status_to_pending`** — assert `$turn->status === TurnStatus::Pending`
4. **`test_factory_defaults_reports_locked_at_to_null`** — assert `$turn->reports_locked_at` is `null`
5. **`test_factory_auto_creates_game`** — assert `$turn->game` is a `Game` instance
6. **`test_factory_accepts_attribute_overrides`** — create with `['number' => 5, 'status' => TurnStatus::Completed]`,
   assert overridden values persist

#### Done when

- `Turn::factory()->create()` succeeds without errors
- Default values match migration defaults
- Factory integrates with the `Turn` model's enum casts
- Targeted test passes and Pint reports no issues

---

### Task C17b — Create `ColonyPopulationFactory` and `ColonyTemplatePopulationFactory`

**Status:** TODO
**Effort:** S
**Depends on:** C14a, C14b

#### Files to create

- `database/factories/ColonyPopulationFactory.php`
- `database/factories/ColonyTemplatePopulationFactory.php`
- `tests/Feature/Database/Factories/PopulationFactoriesTest.php`

#### Commands

```bash
php artisan make:test --phpunit Database/Factories/PopulationFactoriesTest
vendor/bin/pint --dirty --format agent
php artisan test --compact tests/Feature/Database/Factories/PopulationFactoriesTest.php
```

#### Implementation — `database/factories/ColonyPopulationFactory.php`

```php
<?php

namespace Database\Factories;

use App\Enums\PopulationClass;
use App\Models\Colony;
use App\Models\ColonyPopulation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ColonyPopulation>
 */
class ColonyPopulationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'colony_id' => Colony::factory(),
            'population_code' => fake()->randomElement(PopulationClass::cases()),
            'quantity' => fake()->numberBetween(1, 1000),
            'pay_rate' => fake()->randomFloat(2, 0, 10),
            'rebel_quantity' => 0,
        ];
    }
}
```

#### Implementation — `database/factories/ColonyTemplatePopulationFactory.php`

```php
<?php

namespace Database\Factories;

use App\Enums\PopulationClass;
use App\Models\ColonyTemplate;
use App\Models\ColonyTemplatePopulation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ColonyTemplatePopulation>
 */
class ColonyTemplatePopulationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'colony_template_id' => ColonyTemplate::factory(),
            'population_code' => fake()->randomElement(PopulationClass::cases()),
            'quantity' => fake()->numberBetween(1, 1000),
            'pay_rate' => fake()->randomFloat(2, 0, 10),
        ];
    }
}
```

**Key details:**
- `ColonyPopulationFactory` includes `rebel_quantity` defaulting to `0`
- `ColonyTemplatePopulationFactory` does **not** include `rebel_quantity` — that column doesn't exist on the template
  table
- Both use `fake()->randomElement(PopulationClass::cases())` for the enum, matching existing factory patterns

#### Test requirements — `tests/Feature/Database/Factories/PopulationFactoriesTest.php`

**ColonyPopulationFactory tests:**

1. **`test_colony_population_factory_creates_a_valid_record`** — `ColonyPopulation::factory()->create()` does not throw
2. **`test_colony_population_factory_defaults_rebel_quantity_to_zero`** — assert `$record->rebel_quantity === 0`
3. **`test_colony_population_factory_creates_related_colony`** — assert `$record->colony` is a `Colony` instance
4. **`test_colony_population_factory_uses_population_class_enum`** — assert
   `$record->fresh()->population_code instanceof PopulationClass`
5. **`test_colony_population_factory_accepts_explicit_population_code`** — create with
   `['population_code' => PopulationClass::Soldier]`, assert `$record->population_code === PopulationClass::Soldier`

**ColonyTemplatePopulationFactory tests:**

6. **`test_colony_template_population_factory_creates_a_valid_record`** —
   `ColonyTemplatePopulation::factory()->create()` does not throw
7. **`test_colony_template_population_factory_creates_related_template`** — assert `$record->colonyTemplate` is a
   `ColonyTemplate` instance
8. **`test_colony_template_population_factory_uses_population_class_enum`** — assert enum cast works
9. **`test_colony_template_population_factory_accepts_explicit_population_code`** — create with
   `['population_code' => PopulationClass::Professional]`, assert exact match

**Note on unique constraint collisions:** Each factory test creates a single record per parent, so unique constraint
collisions should not occur. If creating multiple records for the same parent, always specify distinct
`population_code` values.

#### Done when

- Both factories create valid records with auto-created parents
- Enum-backed values persist correctly through the model casts
- `rebel_quantity` defaults to `0` for colony population
- No inserts fail from wrong table names or missing timestamps
- Targeted test passes and Pint reports no issues

---

## Execution Order

```
C14a (ColonyPopulation model + Colony::population)
  → C14b (ColonyTemplatePopulation model + ColonyTemplate::population)
    → C15 (Turn model)
      → C16 (Game::turns, Game::currentTurn, Game::canGenerateReports)
        → C17a (TurnFactory)
          → C17b (ColonyPopulationFactory + ColonyTemplatePopulationFactory)
```

Each task is a separate commit boundary.

---

## Group C Acceptance Criteria

Group C is complete when all of the following are true:

### New Models

- [ ] `app/Models/ColonyPopulation.php` exists with `$table = 'colony_population'`, `$timestamps = false`,
  `population_code` cast to `PopulationClass`, `pay_rate` cast to `float`, `colony()` relationship
- [ ] `app/Models/ColonyTemplatePopulation.php` exists with `$table = 'colony_template_population'`,
  `$timestamps = false`, `population_code` cast to `PopulationClass`, `pay_rate` cast to `float`,
  `colonyTemplate()` relationship
- [ ] `app/Models/Turn.php` exists with `status` cast to `TurnStatus`, `reports_locked_at` cast to `datetime`,
  `game()` relationship, timestamps enabled

### Parent Model Relationships

- [ ] `Colony::population()` returns `HasMany<ColonyPopulation>`
- [ ] `ColonyTemplate::population()` returns `HasMany<ColonyTemplatePopulation>`
- [ ] `Game::turns()` returns `HasMany<Turn>` ordered by `number`
- [ ] `Game::currentTurn()` returns `HasOne<Turn>` using `latestOfMany('number')`
- [ ] `Game::canGenerateReports()` returns `true` only when game is active, current turn exists, reports not locked,
  and turn is not generating

### New Factories

- [ ] `database/factories/TurnFactory.php` — defaults: `number = 0`, `status = Pending`, `reports_locked_at = null`
- [ ] `database/factories/ColonyPopulationFactory.php` — uses `PopulationClass` cases, defaults `rebel_quantity = 0`
- [ ] `database/factories/ColonyTemplatePopulationFactory.php` — uses `PopulationClass` cases, no `rebel_quantity`

### Convention Compliance

- [ ] All new models use `#[Fillable([...])]` attribute
- [ ] All new models use `HasFactory` with explicit generic PHPDoc
- [ ] Both population models define explicit `$table` property
- [ ] Both population models set `$timestamps = false`
- [ ] `Turn` model does **not** set `$timestamps = false`
- [ ] Relationship PHPDocs match existing style
- [ ] All new factories use `fake()->...` not `$this->faker`
- [ ] All tests are PHPUnit classes, not Pest

### Test Coverage

- [ ] Every Group C task has PHPUnit test coverage
- [ ] Model tests verify enum casting, raw persisted values, and relationships in both directions
- [ ] Factory tests verify defaults and enum integration
- [ ] `canGenerateReports()` tests cover all positive and negative conditions
- [ ] Pint passes on all changed files

### Quality Gate

```bash
php artisan test --compact tests/Feature/Models/ColonyPopulationModelTest.php
php artisan test --compact tests/Feature/Models/ColonyTemplatePopulationModelTest.php
php artisan test --compact tests/Feature/Models/TurnModelTest.php
php artisan test --compact tests/Feature/Models/GameTurnRelationshipTest.php
php artisan test --compact tests/Feature/Database/Factories/TurnFactoryTest.php
php artisan test --compact tests/Feature/Database/Factories/PopulationFactoriesTest.php
vendor/bin/pint --dirty --format agent
```

All targeted tests must pass and Pint must report no formatting issues.

---

## Out of Scope for Group C

Do **not** pull these into this burndown:

- Template upload request/controller changes for population section (Group D tasks 18–20)
- `EmpireCreator` updates for colony population seeding (Group E task 21)
- `GameGenerationController::activate()` Turn 0 creation (Group E task 22)
- Report migration/model/service work (Group F tasks 23–30)
- Routes, authorization, and controllers (Group G tasks 31–33)
- Fixing existing broken tests (Layer 1 task 34)
- Frontend work (Group I tasks 36–37)
- `Game::colonyTemplates()` HasMany conversion (tracked for Group D/E)
