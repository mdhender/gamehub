# Layer 1 / Group B — Update Existing Models and Factories

## Scope

This burndown covers **Layer 1, Group B** from `docs/SETUP_REPORT.md`: updating existing Eloquent models and factories
to use the string-backed enum casts and new column definitions introduced in Group A's migrations.

1. Update `Colony` model — add `ColonyKind` cast, new fillable columns
2. Update `ColonyInventory` model — cast `unit` with `UnitCode` enum
3. Update `ColonyTemplate` model — cast `kind` with `ColonyKind` enum
4. Update `ColonyTemplateItem` model — cast `unit` with `UnitCode` enum
5. Update `ColonyFactory` — use `ColonyKind` enum, add defaults for new columns
6. Update `ColonyTemplateFactory` — use `ColonyKind` enum *(gap fix — omitted from SETUP_REPORT)*
7. Update `ColonyInventoryFactory` — use `UnitCode` enum values
8. Update `ColonyTemplateItemFactory` — use `UnitCode` enum values

---

## Global Guardrails

- **Order is fixed:** do tasks B7 → B13.
- **Use PHPUnit only** (not Pest).
- **Use feature tests** for all Group B work.
- Match existing repo conventions:
  - `#[Fillable([...])]` attribute for mass assignment
  - `/** @use HasFactory<XxxFactory> */` PHPDoc on the trait
  - Explicit relationship PHPDoc return types: `/** @return BelongsTo<X, $this> */`
  - Casts via `protected function casts(): array` (see `Game` model for the pattern)
- **Assume Group A migrations have been applied.** Tests use `RefreshDatabase` which runs migrations fresh.
  New tests must assert **string enum values in the database**, not legacy integers.
- **Do not create stub `ColonyPopulation` or `ColonyTemplatePopulation` models in this group.** Those belong to Group C.
- **Defer `population()` relationships to Group C task 14** — the `ColonyPopulation` model does not exist yet.
- Until the relevant factory task is complete, do **not** rely on `Colony::factory()` or `ColonyTemplate::factory()`
  defaults in model tests — create records manually or override attributes explicitly with enum values.
- When writing to casted attributes through Eloquent, use **enum cases** (`ColonyKind::OpenSurface`,
  `UnitCode::Factories`).
- When asserting raw stored values with `assertDatabaseHas()`, use **`->value`** to compare the backing string.
- **Run after every PHP task:** `vendor/bin/pint --dirty --format agent`
- **Do not try to make the full suite green in this group.** Legacy tests that assume integer `kind` / `unit` values
  are addressed later in Layer 1 task 34.

---

## Design Decisions

### 1. `population()` relationships are deferred to Group C

Both `Colony::population()` and `ColonyTemplate::population()` reference models that do not exist yet
(`ColonyPopulation`, `ColonyTemplatePopulation`). Adding relationship methods now would create a broken intermediate
state. Group C task 14 creates those models and adds the relationships in one atomic step.

### 2. `Game::colonyTemplates()` is tracked but not in this group

Group A dropped the unique constraint on `colony_templates.game_id` (multi-colony templates per game). The existing
`Game::colonyTemplate(): HasOne` should eventually become `colonyTemplates(): HasMany`. This change affects downstream
consumers (`EmpireCreator`, template upload flow, existing tests) and is best handled as a separate existing-model gap
task immediately before or during Group D/E when multi-template consumers are updated.

---

## Current File State (Post–Group A)

### Database columns (after Group A migrations run)

- **`colonies`:** `id`, `empire_id`, `planet_id`, `kind` (varchar), `tech_level`, `name` (default `'Not Named'`),
  `is_on_surface` (default `1`), `rations` (default `1.0`), `sol` (default `0.0`), `birth_rate` (default `0.0`),
  `death_rate` (default `0.0`)
- **`colony_inventory`:** `id`, `colony_id`, `unit` (varchar), `tech_level`, `quantity_assembled`,
  `quantity_disassembled`
- **`colony_template_items`:** `id`, `colony_template_id`, `unit` (varchar), `tech_level`, `quantity_assembled`,
  `quantity_disassembled`
- **`colony_templates`:** `id`, `game_id` (NOT unique), `kind` (varchar), `tech_level`, `created_at`, `updated_at`

### Enums (created in Group A)

- `ColonyKind`: `OpenSurface='COPN'`, `Enclosed='CENC'`, `Orbital='CORB'`
- `UnitCode`: 30 cases (`AUT`, `ESH`, `EWP`, `FCT`, `FRM`, … `RSCH`)
- `PopulationClass`: 9 cases (`UEM`, `USK`, `PRO`, `SLD`, `CNW`, `SPY`, `PLC`, `SAG`, `TRN`)

---

## Tasks

---

### Task B7 — Update `Colony` model
**Status:** DONE
**Effort:** S
**Depends on:** A1, A3

#### Files to modify

- `app/Models/Colony.php`

#### Files to create

- `tests/Feature/Models/ColonyModelTest.php`

#### Commands

```bash
php artisan make:test --phpunit Models/ColonyModelTest
vendor/bin/pint --dirty --format agent
php artisan test --compact tests/Feature/Models/ColonyModelTest.php
```

#### Implementation checklist

- Import `App\Enums\ColonyKind`.
- Expand the `#[Fillable([...])]` attribute to include the new columns:

```php
#[Fillable(['empire_id', 'planet_id', 'kind', 'tech_level', 'name', 'is_on_surface', 'rations', 'sol', 'birth_rate', 'death_rate'])]
```

- Add a `casts()` method following the `Game` model pattern:

```php
/**
 * @return array<string, string>
 */
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
```

- Keep `public $timestamps = false;` unchanged.
- Keep existing `empire()`, `planet()`, and `inventory()` relationships unchanged.
- **Do not add `population()` in this task.** That relationship is deferred to Group C task 14.

#### Test requirements — `tests/Feature/Models/ColonyModelTest.php`

1. **casts `kind` to `ColonyKind`** — create a colony with `kind => ColonyKind::Enclosed`, assert
   `$colony->fresh()->kind === ColonyKind::Enclosed`
2. **mass assignment accepts new fillable columns** — create via `Colony::query()->create([...])` including `name`,
   `is_on_surface`, `rations`, `sol`, `birth_rate`, `death_rate`; assert all values persist
3. **primitive casts round-trip correctly** — assert `is_on_surface` is `bool`, `rations`/`sol`/`birth_rate`/`death_rate`
   are `float`
4. **raw database stores the enum backing value** — `assertDatabaseHas('colonies', ['kind' => 'CENC'])`
5. **existing relationships still work** — assert `$colony->empire` and `$colony->planet` resolve

Do not use `Colony::factory()` defaults in this test; create an `Empire` and `Planet` explicitly and pass attributes
with enum values.

#### Done when

- `Colony` casts `kind` to `ColonyKind`
- New live-schema columns are fillable and cast correctly
- No missing-class `population()` relationship has been introduced
- Targeted test passes

---

### Task B8 — Update `ColonyInventory` model
**Status:** DONE
**Effort:** S
**Depends on:** A1, A2

#### Files to modify

- `app/Models/ColonyInventory.php`

#### Files to create

- `tests/Feature/Models/ColonyInventoryModelTest.php`

#### Commands

```bash
php artisan make:test --phpunit Models/ColonyInventoryModelTest
vendor/bin/pint --dirty --format agent
php artisan test --compact tests/Feature/Models/ColonyInventoryModelTest.php
```

#### Implementation checklist

- Import `App\Enums\UnitCode`.
- Add a `casts()` method:

```php
/**
 * @return array<string, string>
 */
protected function casts(): array
{
    return [
        'unit' => UnitCode::class,
    ];
}
```

- Keep `protected $table = 'colony_inventory';`, `public $timestamps = false;`, and `colony()` relationship unchanged.

#### Test requirements — `tests/Feature/Models/ColonyInventoryModelTest.php`

1. **casts `unit` to `UnitCode`** — create a row with `unit => UnitCode::Factories`, assert
   `$inventory->fresh()->unit === UnitCode::Factories`
2. **stores enum backing value in database** — `assertDatabaseHas('colony_inventory', ['unit' => 'FCT'])`
3. **relationship to colony still works** — assert `$inventory->colony` resolves correctly

Do not rely on `ColonyInventory::factory()` or `Colony::factory()` defaults; create the colony explicitly with string
`kind` and pass `unit` as a `UnitCode` enum case.

#### Done when

- `ColonyInventory` casts `unit` to `UnitCode`
- Raw DB value is the string code, not an integer
- Targeted test passes

---

### Task B9 — Update `ColonyTemplate` model
**Status:** TODO
**Effort:** S
**Depends on:** A1, A2

#### Files to modify

- `app/Models/ColonyTemplate.php`

#### Files to create

- `tests/Feature/Models/ColonyTemplateModelTest.php`

#### Commands

```bash
php artisan make:test --phpunit Models/ColonyTemplateModelTest
vendor/bin/pint --dirty --format agent
php artisan test --compact tests/Feature/Models/ColonyTemplateModelTest.php
```

#### Implementation checklist

- Import `App\Enums\ColonyKind`.
- Add a `casts()` method:

```php
/**
 * @return array<string, string>
 */
protected function casts(): array
{
    return [
        'kind' => ColonyKind::class,
    ];
}
```

- Keep existing `game()` and `items()` relationships unchanged.
- **Do not add `population()` in this task.** That relationship is deferred to Group C task 14.

#### Test requirements — `tests/Feature/Models/ColonyTemplateModelTest.php`

1. **casts `kind` to `ColonyKind`** — create a template with `kind => ColonyKind::Orbital`, assert
   `$template->fresh()->kind === ColonyKind::Orbital`
2. **stores enum backing value** — `assertDatabaseHas('colony_templates', ['kind' => 'CORB'])`
3. **existing relationships still work** — verify `$template->game` resolves; create a child item and verify
   `$template->items` contains it
4. **multiple templates per game** — create two templates for the same game, assert both persist (confirms unique
   constraint was dropped in A2)

Do not rely on `ColonyTemplate::factory()` defaults; create rows manually with string `kind`.

#### Done when

- `ColonyTemplate` casts `kind` to `ColonyKind`
- Existing relationships still pass
- No missing-class `population()` relationship has been introduced
- Targeted test passes

---

### Task B10 — Update `ColonyTemplateItem` model
**Status:** TODO
**Effort:** S
**Depends on:** A1, A2

#### Files to modify

- `app/Models/ColonyTemplateItem.php`

#### Files to create

- `tests/Feature/Models/ColonyTemplateItemModelTest.php`

#### Commands

```bash
php artisan make:test --phpunit Models/ColonyTemplateItemModelTest
vendor/bin/pint --dirty --format agent
php artisan test --compact tests/Feature/Models/ColonyTemplateItemModelTest.php
```

#### Implementation checklist

- Import `App\Enums\UnitCode`.
- Add a `casts()` method:

```php
/**
 * @return array<string, string>
 */
protected function casts(): array
{
    return [
        'unit' => UnitCode::class,
    ];
}
```

- Keep `public $timestamps = false;` and `colonyTemplate()` relationship unchanged.

#### Test requirements — `tests/Feature/Models/ColonyTemplateItemModelTest.php`

1. **casts `unit` to `UnitCode`** — create an item with `unit => UnitCode::Food`, assert
   `$item->fresh()->unit === UnitCode::Food`
2. **stores enum backing value** — `assertDatabaseHas('colony_template_items', ['unit' => 'FOOD'])`
3. **relationship to colony template still works** — assert `$item->colonyTemplate` resolves correctly

Do not rely on `ColonyTemplateItem::factory()` defaults; create the parent template explicitly with string `kind`
and pass `unit` as a `UnitCode` enum case.

#### Done when

- `ColonyTemplateItem` casts `unit` to `UnitCode`
- Raw DB value is the string unit code
- Targeted test passes

---

### Task B11 — Update `ColonyFactory`
**Status:** TODO
**Effort:** S
**Depends on:** B7

#### Files to modify

- `database/factories/ColonyFactory.php`

#### Files to create

- `tests/Feature/Database/Factories/ColonyFactoryTest.php`

#### Commands

```bash
php artisan make:test --phpunit Database/Factories/ColonyFactoryTest
vendor/bin/pint --dirty --format agent
php artisan test --compact tests/Feature/Database/Factories/ColonyFactoryTest.php
```

#### Implementation checklist

- Import `App\Enums\ColonyKind`.
- Replace the legacy integer `kind` default with a deterministic enum case:

```php
'kind' => ColonyKind::OpenSurface,
```

- Add defaults for the six new `colonies` columns matching the migrated schema defaults:

```php
'name' => 'Not Named',
'is_on_surface' => true,
'rations' => 1.0,
'sol' => 0.0,
'birth_rate' => 0.0,
'death_rate' => 0.0,
```

- Keep `empire_id`, `planet_id`, and `tech_level` unchanged.

#### Test requirements — `tests/Feature/Database/Factories/ColonyFactoryTest.php`

1. **factory creates a valid colony** — `Colony::factory()->create()` does not throw
2. **default `kind` is enum-backed** — assert `$colony->fresh()->kind === ColonyKind::OpenSurface` and DB stores
   `kind = 'COPN'`
3. **new columns have coherent defaults** — assert `name = 'Not Named'`, `is_on_surface = true`, `rations = 1.0`,
   `sol = 0.0`, `birth_rate = 0.0`, `death_rate = 0.0`
4. **overrides work** — create with `kind => ColonyKind::Orbital` and `is_on_surface => false`, assert overrides
   persist after refresh

#### Done when

- `Colony::factory()` no longer emits invalid integer `kind` values
- Factory output matches the migrated schema
- Targeted test passes

---

### Task B11a — Update `ColonyTemplateFactory` *(gap fix — omitted from SETUP_REPORT)*
**Status:** TODO
**Effort:** S
**Depends on:** B9

#### Files to modify

- `database/factories/ColonyTemplateFactory.php`

#### Files to create

- `tests/Feature/Database/Factories/ColonyTemplateFactoryTest.php`

#### Commands

```bash
php artisan make:test --phpunit Database/Factories/ColonyTemplateFactoryTest
vendor/bin/pint --dirty --format agent
php artisan test --compact tests/Feature/Database/Factories/ColonyTemplateFactoryTest.php
```

#### Implementation checklist

- Import `App\Enums\ColonyKind`.
- Replace the legacy integer `kind` default with a deterministic enum case:

```php
'kind' => ColonyKind::OpenSurface,
```

- Keep `game_id` and `tech_level` behavior unchanged.

#### Test requirements — `tests/Feature/Database/Factories/ColonyTemplateFactoryTest.php`

1. **factory creates a valid template** — `ColonyTemplate::factory()->create()` does not throw
2. **default `kind` is enum-backed** — assert `$template->fresh()->kind === ColonyKind::OpenSurface` and DB stores
   `kind = 'COPN'`
3. **multiple templates per game** — create two templates for the same game, assert both rows persist (confirms
   unique constraint was dropped in A2)

#### Done when

- `ColonyTemplate::factory()` no longer emits invalid integer `kind` values
- Factory is compatible with multi-template schema
- Targeted test passes

---

### Task B12 — Update `ColonyInventoryFactory`
**Status:** TODO
**Effort:** S
**Depends on:** B8, B11

#### Files to modify

- `database/factories/ColonyInventoryFactory.php`

#### Files to create

- `tests/Feature/Database/Factories/ColonyInventoryFactoryTest.php`

#### Commands

```bash
php artisan make:test --phpunit Database/Factories/ColonyInventoryFactoryTest
vendor/bin/pint --dirty --format agent
php artisan test --compact tests/Feature/Database/Factories/ColonyInventoryFactoryTest.php
```

#### Implementation checklist

- Import `App\Enums\UnitCode`.
- Replace the legacy integer `unit` default:

```php
'unit' => fake()->randomElement(UnitCode::cases()),
```

- Keep `colony_id`, `tech_level`, `quantity_assembled`, `quantity_disassembled` unchanged.

#### Test requirements — `tests/Feature/Database/Factories/ColonyInventoryFactoryTest.php`

1. **factory creates a valid row** — `ColonyInventory::factory()->create()` does not throw
2. **default `unit` resolves as `UnitCode`** — assert `$inventory->fresh()->unit instanceof UnitCode`
3. **raw DB value is a valid enum backing value** — compare against
   `array_map(fn (UnitCode $code) => $code->value, UnitCode::cases())`
4. **explicit override works** — create with `unit => UnitCode::Fuel`, assert exact enum case after refresh

#### Done when

- `ColonyInventory::factory()` no longer emits integers for `unit`
- Default output is valid against the casted model
- Targeted test passes

---

### Task B13 — Update `ColonyTemplateItemFactory`
**Status:** TODO
**Effort:** S
**Depends on:** B10, B11a

#### Files to modify

- `database/factories/ColonyTemplateItemFactory.php`

#### Files to create

- `tests/Feature/Database/Factories/ColonyTemplateItemFactoryTest.php`

#### Commands

```bash
php artisan make:test --phpunit Database/Factories/ColonyTemplateItemFactoryTest
vendor/bin/pint --dirty --format agent
php artisan test --compact tests/Feature/Database/Factories/ColonyTemplateItemFactoryTest.php
```

#### Implementation checklist

- Import `App\Enums\UnitCode`.
- Replace the legacy integer `unit` default:

```php
'unit' => fake()->randomElement(UnitCode::cases()),
```

- Keep `colony_template_id`, `tech_level`, `quantity_assembled`, `quantity_disassembled` unchanged.

#### Test requirements — `tests/Feature/Database/Factories/ColonyTemplateItemFactoryTest.php`

1. **factory creates a valid row** — `ColonyTemplateItem::factory()->create()` does not throw
2. **default `unit` resolves as `UnitCode`** — assert `$item->fresh()->unit instanceof UnitCode`
3. **raw DB value is a valid enum backing value**
4. **explicit override works** — create with `unit => UnitCode::Metals`, assert exact enum case after refresh

#### Done when

- `ColonyTemplateItem::factory()` no longer emits integers for `unit`
- Default output is valid against the casted model
- Targeted test passes

---

## Execution Order

```
B7 (Colony model) → B8 (ColonyInventory model) → B9 (ColonyTemplate model) → B10 (ColonyTemplateItem model) → B11 (ColonyFactory) → B11a (ColonyTemplateFactory) → B12 (ColonyInventoryFactory) → B13 (ColonyTemplateItemFactory)
```

Each task is a separate commit boundary.

---

## Group B Acceptance Criteria

Group B is complete when all of the following are true:

### Models

- [ ] `app/Models/Colony.php` casts `kind` to `ColonyKind`
- [ ] `Colony` `#[Fillable]` includes: `name`, `is_on_surface`, `rations`, `sol`, `birth_rate`, `death_rate`
- [ ] `Colony` casts: `is_on_surface` → `boolean`, `rations` → `float`, `sol` → `float`, `birth_rate` → `float`,
  `death_rate` → `float`
- [ ] `app/Models/ColonyInventory.php` casts `unit` to `UnitCode`
- [ ] `app/Models/ColonyTemplate.php` casts `kind` to `ColonyKind`
- [ ] `app/Models/ColonyTemplateItem.php` casts `unit` to `UnitCode`

### Factories

- [ ] `database/factories/ColonyFactory.php` uses `ColonyKind`, not integers
- [ ] `ColonyFactory` includes valid defaults for the six new colony columns
- [ ] `database/factories/ColonyTemplateFactory.php` uses `ColonyKind`, not integers
- [ ] `database/factories/ColonyInventoryFactory.php` uses `UnitCode` values, not integers
- [ ] `database/factories/ColonyTemplateItemFactory.php` uses `UnitCode` values, not integers

### Schema Compatibility

- [ ] All new tests assert **string-backed enum values in the database**
- [ ] No Group B code assumes the old integer `kind` / `unit` schema
- [ ] No Group B task introduces references to missing `ColonyPopulation` / `ColonyTemplatePopulation` model classes

### Explicit Deferrals

- [ ] `Colony::population()` is deferred to Group C task 14
- [ ] `ColonyTemplate::population()` is deferred to Group C task 14
- [ ] `Game::colonyTemplates()` is tracked as a separate existing-model gap task for Group D/E

### Test Coverage

- [ ] Every Group B task has its own PHPUnit feature test file
- [ ] Model tests verify enum casting and raw persisted values
- [ ] Factory tests verify defaults are valid against the migrated schema
- [ ] Pint passes on all changed files

### Quality Gate

```bash
php artisan test --compact tests/Feature/Models/ColonyModelTest.php
php artisan test --compact tests/Feature/Models/ColonyInventoryModelTest.php
php artisan test --compact tests/Feature/Models/ColonyTemplateModelTest.php
php artisan test --compact tests/Feature/Models/ColonyTemplateItemModelTest.php
php artisan test --compact tests/Feature/Database/Factories/ColonyFactoryTest.php
php artisan test --compact tests/Feature/Database/Factories/ColonyTemplateFactoryTest.php
php artisan test --compact tests/Feature/Database/Factories/ColonyInventoryFactoryTest.php
php artisan test --compact tests/Feature/Database/Factories/ColonyTemplateItemFactoryTest.php
vendor/bin/pint --dirty --format agent
```

All targeted tests must pass and Pint must report no formatting issues.

---

## Pre-existing Test Failures

These tests were failing before Group B work began and need to be fixed:

### `EmpireCreatorTest` — integer vs string type mismatches

- `create_creates_starting_colony` — `assertSame(1, $colony->kind)` fails because `kind` is now a string (`'1'`). Needs updating to use `ColonyKind` enum.
- `create_applies_colony_template_inventory` — `assertSame(10, $inventory->first()->unit)` fails because `unit` is now a string (`'10'`). Needs updating to use `UnitCode` enum.

**File:** `tests/Feature/EmpireCreatorTest.php`

### `GameGenerationControllerTest` — factory and enum issues

- `generate_creates_empires_for_active_players` — `Undefined array key "kind"` in `GameFactory.php:77`. The `ColonyTemplate` creation in the factory expects a `kind` key that is missing from the input data.
- `upload_home_system_template_creates_template_and_children` — `"gold" is not a valid backing value for enum DepositResource`. The `TemplateController` passes a raw string (`gold`) that doesn't match the `DepositResource` enum's backing values.
- `upload_colony_template_creates_template_and_items` — same `Undefined array key "kind"` in `GameFactory.php:77`.

**File:** `tests/Feature/GameGenerationControllerTest.php`, `database/factories/GameFactory.php`, `app/Http/Controllers/GameGeneration/TemplateController.php`

---

## Out of Scope for Group B

Do **not** pull these into this burndown:

- `ColonyPopulation` and `ColonyTemplatePopulation` model creation (Group C task 14)
- `population()` relationships on `Colony` / `ColonyTemplate` (defer to C14)
- `Turn` model / `Game::turns()` / `Game::currentTurn()` (Group C tasks 15–16)
- `TurnFactory`, `ColonyPopulationFactory`, `ColonyTemplatePopulationFactory` (Group C task 17)
- Template upload request/controller changes (Group D)
- `EmpireCreator` updates for multi-template colonies or colony population seeding (Group E)
- Report generation / turn report tables / controllers (Groups F–I)
- Broad cleanup of legacy tests that assume integer `kind` / `unit` values (Layer 1 task 34)
- Breaking removal of `Game::colonyTemplate()` before its consumers are migrated
