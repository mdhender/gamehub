# Layer 1 / Group A — Enums and Live-Schema Migrations

## Scope

This burndown covers **Layer 1, Group A** from `docs/SETUP_REPORT.md`: the foundational enums and schema migrations
needed by all subsequent groups.

1. Create `TurnStatus`, `PopulationClass`, `UnitCode`, `ColonyKind` enums
2. Rebuild migration: `colony_inventory.unit` int→string; `colony_template_items.unit` int→string;
   `colony_templates.kind` int→string; drop unique on `colony_templates.game_id`
3. Rebuild migration: `colonies.kind` int→string; add `name`, `is_on_surface`, `rations`, `sol`, `birth_rate`,
   `death_rate`
4. Migration: `colony_population`
5. Migration: `colony_template_population`
6. Migration: `turns`

---

## Global Guardrails

- **Order is fixed:** do tasks A1 → A6.
- **Use PHPUnit only** (not Pest).
- **Use feature tests** unless purely unit-level.
- **Run after every PHP task:** `vendor/bin/pint --dirty --format agent`
- **SQLite only:** no `->change()`, no DBAL, no `ALTER COLUMN`.
- **Do not update models, factories, or controllers in this group.** Those belong to Group B and later. For Group A
  tests, create rows manually or override attributes explicitly with string values.
- **Do not touch `sample-data/beta/colony-template.json`** — it is already updated.

---

## Canonical Enum Values

### `app/Enums/TurnStatus.php`

```php
enum TurnStatus: string
{
    case Pending = 'pending';
    case Generating = 'generating';
    case Completed = 'completed';
    case Closed = 'closed';
}
```

### `app/Enums/PopulationClass.php`

```php
enum PopulationClass: string
{
    case Unemployable = 'UEM';
    case Unskilled = 'USK';
    case Professional = 'PRO';
    case Soldier = 'SLD';
    case ConstructionWorker = 'CNW';
    case Spy = 'SPY';
    case Police = 'PLC';
    case SpecialAgent = 'SAG';
    case Trainee = 'TRN';
}
```

### `app/Enums/UnitCode.php`

```php
enum UnitCode: string
{
    // Assembly (operational units)
    case Automation = 'AUT';
    case EnergyShields = 'ESH';
    case EnergyWeapons = 'EWP';
    case Factories = 'FCT';
    case Farms = 'FRM';
    case HyperEngines = 'HEN';
    case Laboratories = 'LAB';
    case LifeSupports = 'LFS';
    case Mines = 'MIN';
    case MissileLaunchers = 'MSL';
    case PowerPlants = 'PWP';
    case Sensors = 'SEN';
    case LightStructure = 'SLS';
    case SpaceDrives = 'SPD';
    case Structure = 'STU';

    // Vehicles
    case AntiMissiles = 'ANM';
    case AssaultCraft = 'ASC';
    case AssaultWeapons = 'ASW';
    case Missiles = 'MSS';
    case Transports = 'TPT';

    // Bots
    case MilitaryRobots = 'MTBT';
    case RobotProbes = 'RPV';

    // Consumables
    case ConsumerGoods = 'CNGD';
    case Food = 'FOOD';
    case Fuel = 'FUEL';
    case Gold = 'GOLD';
    case Metals = 'METS';
    case MilitarySupplies = 'MTSP';
    case NonMetals = 'NMTS';
    case Research = 'RSCH';
}
```

### `app/Enums/ColonyKind.php`

```php
enum ColonyKind: string
{
    case OpenSurface = 'COPN';
    case Enclosed = 'CENC';
    case Orbital = 'CORB';
}
```

---

## Legacy Unit Mapping for Rebuild Migrations

The rebuild migrations must use an explicit CASE mapping and **fail fast** on any unknown integer. Never silently coerce
unmapped values.

| Old int | New code |
|---------|----------|
| 1       | AUT      |
| 2       | ESH      |
| 3       | EWP      |
| 4       | FCT      |
| 5       | FRM      |
| 6       | HEN      |
| 7       | LAB      |
| 8       | LFS      |
| 9       | MIN      |
| 10      | MSL      |
| 11      | PWP      |
| 12      | SEN      |
| 13      | SLS      |
| 14      | SPD      |
| 15      | STU      |
| 16      | ANM      |
| 17      | ASC      |
| 18      | ASW      |
| 19      | MSS      |
| 20      | TPT      |
| 21      | MTBT     |
| 22      | RPV      |
| 23      | CNGD     |
| 24      | FOOD     |
| 25      | FUEL     |
| 26      | GOLD     |
| 27      | METS     |
| 28      | MTSP     |
| 29      | NMTS     |
| 30      | RSCH     |

**Fail-fast rule:** if `colony_inventory.unit` or `colony_template_items.unit` contains any value outside 1–30, throw a
`RuntimeException` with a clear message identifying the table and column.

---

## Tasks

---

### Task A1 — Create enums
**Status:** DONE
**Effort:** S
**Depends on:** none

#### Files to create

- `app/Enums/TurnStatus.php`
- `app/Enums/PopulationClass.php`
- `app/Enums/UnitCode.php`
- `app/Enums/ColonyKind.php`
- `tests/Unit/Enums/LayerOneEnumsTest.php`

#### Commands

```bash
# No make:enum in Laravel — create enum files manually
php artisan make:test --unit --phpunit Enums/LayerOneEnumsTest
vendor/bin/pint --dirty --format agent
php artisan test --compact tests/Unit/Enums/LayerOneEnumsTest.php
```

#### Implementation checklist

- Create the 4 string-backed enums under `App\Enums` using the exact values from the "Canonical Enum Values" section
  above.
- Match naming style with existing enums (`GameStatus`, `DepositResource`).

#### Test requirements — `tests/Unit/Enums/LayerOneEnumsTest.php`

- Assert `TurnStatus::cases()` contains exactly `Pending`, `Generating`, `Completed`, `Closed` with values `pending`,
  `generating`, `completed`, `closed`.
- Assert `ColonyKind::cases()` contains exactly `OpenSurface`, `Enclosed`, `Orbital` with values `COPN`, `CENC`, `CORB`.
- Assert `PopulationClass::cases()` contains exactly 9 cases: `UEM`, `USK`, `PRO`, `SLD`, `CNW`, `SPY`, `PLC`, `SAG`,
  `TRN`.
- Assert `UnitCode::cases()` contains exactly 30 cases with the values from the mapping table.

#### Done when

- All 4 enum files exist with exact values.
- Enum test passes.

---

### Task A2 — Rebuild migration: `colony_inventory`, `colony_template_items`, `colony_templates`
**Status:** DONE
**Effort:** M
**Depends on:** A1

#### Files to create

- Migration: `rebuild_colony_inventory_colony_template_items_and_colony_templates_for_string_codes`
- `tests/Feature/Database/Migrations/RebuildColonyInventoryAndTemplatesMigrationTest.php`

#### Commands

```bash
php artisan make:migration rebuild_colony_inventory_colony_template_items_and_colony_templates_for_string_codes
php artisan make:test --phpunit Database/Migrations/RebuildColonyInventoryAndTemplatesMigrationTest
vendor/bin/pint --dirty --format agent
php artisan test --compact tests/Feature/Database/Migrations/RebuildColonyInventoryAndTemplatesMigrationTest.php
```

#### Existing schemas

**`colony_inventory`** — `id`(PK), `colony_id`(FK→colonies, cascade), `unit`(integer), `tech_level`(integer),
`quantity_assembled`(integer), `quantity_disassembled`(integer). No timestamps.

**`colony_template_items`** — `id`(PK), `colony_template_id`(FK→colony_templates, cascade), `unit`(integer),
`tech_level`(integer), `quantity_assembled`(integer), `quantity_disassembled`(integer). No timestamps.

**`colony_templates`** — `id`(PK), `game_id`(FK→games, cascade, **currently unique**), `kind`(integer), `tech_level`(
integer), `created_at`, `updated_at`.

#### Target schemas after rebuild

- `colony_inventory.unit` → string (was integer)
- `colony_template_items.unit` → string (was integer)
- `colony_templates.kind` → string (was integer)
- `colony_templates.game_id` — **drop unique constraint** (multi-colony templates per game)

#### SQLite rebuild pattern

Wrap the entire migration in `Schema::disableForeignKeyConstraints()` / `Schema::enableForeignKeyConstraints()`.

**Preflight validation (before any copy):**

1. Check `colony_inventory.unit` — all values must be in 1–30. If not, throw `RuntimeException`.
2. Check `colony_template_items.unit` — all values must be in 1–30. If not, throw `RuntimeException`.
3. Check `colony_templates.kind` — all values must be `1`. If not, throw `RuntimeException`.

**For each table:**

1. Create temp table with target schema (string `unit`/`kind` column).
2. Copy data using `INSERT INTO temp SELECT ... CASE unit WHEN 1 THEN 'AUT' ... END ...` — use the full 30-value CASE
   mapping from the table above.
3. For `colony_templates.kind`: `CASE kind WHEN 1 THEN 'COPN' END`.
4. Drop original table.
5. Rename temp → original name.

**Foreign keys to recreate:**

- `colony_inventory`: FK `colony_id` → `colonies.id` cascade delete
- `colony_template_items`: FK `colony_template_id` → `colony_templates.id` cascade delete
- `colony_templates`: FK `game_id` → `games.id` cascade delete — **no unique index on `game_id`**

#### Test requirements — `tests/Feature/Database/Migrations/RebuildColonyInventoryAndTemplatesMigrationTest.php`

Since `RefreshDatabase` runs all migrations (including this one), these tests verify the post-migration state:

1. **migrates known unit ints to string codes in `colony_inventory`** — seed legacy int values before migration, verify
   string values after
2. **migrates known unit ints to string codes in `colony_template_items`** — same pattern
3. **migrates `colony_templates.kind` from 1 to COPN**
4. **drops unique constraint on `colony_templates.game_id`** — insert 2 templates for the same game_id; assert no
   exception
5. **preserves foreign keys** — use `PRAGMA foreign_key_list(...)` to verify FK relationships on all 3 tables
6. **fails fast on unknown unit integer** — seed `unit = 99`, assert `RuntimeException`
7. **fails fast on unknown template kind** — seed `kind = 2`, assert `RuntimeException`

#### Done when

- All three tables are rebuilt via temp-table pattern.
- `unit`/`kind` columns are string-based.
- `colony_templates.game_id` is no longer unique.
- Unknown legacy ints fail loudly.
- Migration test passes.

---

### Task A3 — Rebuild migration: `colonies`
**Status:** DONE
**Effort:** M
**Depends on:** A1 (ideally after A2)

#### Files to create

- Migration: `rebuild_colonies_for_string_kind_and_setup_report_columns`
- `tests/Feature/Database/Migrations/RebuildColoniesTableMigrationTest.php`

#### Commands

```bash
php artisan make:migration rebuild_colonies_for_string_kind_and_setup_report_columns
php artisan make:test --phpunit Database/Migrations/RebuildColoniesTableMigrationTest
vendor/bin/pint --dirty --format agent
php artisan test --compact tests/Feature/Database/Migrations/RebuildColoniesTableMigrationTest.php
```

#### Existing schema

**`colonies`** — `id`(PK), `empire_id`(FK→empires, cascade), `planet_id`(FK→planets, cascade), `kind`(integer),
`tech_level`(integer). No timestamps.

#### Target schema after rebuild

- `id` — integer PK
- `empire_id` — FK → empires.id cascade delete
- `planet_id` — FK → planets.id cascade delete
- `kind` — string (was integer)
- `tech_level` — integer
- `name` — string, default `'Not Named'`
- `is_on_surface` — boolean, default `true`
- `rations` — float, default `1.0`
- `sol` — float, default `0.0`
- `birth_rate` — float, default `0.0`
- `death_rate` — float, default `0.0`

#### SQLite rebuild pattern

Wrap in `Schema::disableForeignKeyConstraints()` / `Schema::enableForeignKeyConstraints()`.

**Preflight validation:**

- All `colonies.kind` values must be `1`. Otherwise throw `RuntimeException`.

**Data copy mapping:**

- `kind`: `CASE kind WHEN 1 THEN 'COPN' END`
- `name`: `'Not Named'` (literal default for all existing rows)
- `is_on_surface`: `1` (true)
- `rations`: `1.0`
- `sol`: `0.0`
- `birth_rate`: `0.0`
- `death_rate`: `0.0`

**Rebuild steps:**

1. Create `colonies_temp` with target schema.
2. Copy from old table with CASE mapping and literal defaults.
3. Drop `colonies`.
4. Rename `colonies_temp` → `colonies`.

**Foreign keys to recreate:**

- FK `empire_id` → `empires.id` cascade delete
- FK `planet_id` → `planets.id` cascade delete

#### Test requirements — `tests/Feature/Database/Migrations/RebuildColoniesTableMigrationTest.php`

1. **migrates legacy `kind=1` to `COPN`**
2. **adds all six new columns with correct defaults** — verify `name='Not Named'`, `is_on_surface=1`, `rations=1.0`,
   `sol=0.0`, `birth_rate=0.0`, `death_rate=0.0`
3. **preserves `empire_id`, `planet_id`, `tech_level`, and primary key**
4. **preserves both foreign keys** — use `PRAGMA foreign_key_list('colonies')`
5. **fails fast on unknown legacy `kind`** — seed `kind = 2`, assert `RuntimeException`

#### Done when

- `colonies.kind` is string-based.
- Six new columns exist with correct defaults.
- Existing data is preserved and converted.
- Unknown legacy kinds fail loudly.
- Migration test passes.

---

### Task A4 — Create `colony_population` table
**Status:** TODO
**Effort:** S
**Depends on:** A1, A3

#### Files to create

- Migration: `create_colony_population_table`
- `tests/Feature/Database/Migrations/CreateColonyPopulationTableMigrationTest.php`

#### Commands

```bash
php artisan make:migration create_colony_population_table --create=colony_population
php artisan make:test --phpunit Database/Migrations/CreateColonyPopulationTableMigrationTest
vendor/bin/pint --dirty --format agent
php artisan test --compact tests/Feature/Database/Migrations/CreateColonyPopulationTableMigrationTest.php
```

#### Target schema

| Column            | Type                     | Notes                                       |
|-------------------|--------------------------|---------------------------------------------|
| `id`              | integer PK               |                                             |
| `colony_id`       | integer FK → colonies.id | cascadeOnDelete                             |
| `population_code` | string                   | UEM, USK, PRO, SLD, CNW, SPY, PLC, SAG, TRN |
| `quantity`        | integer                  |                                             |
| `pay_rate`        | float                    |                                             |
| `rebel_quantity`  | integer                  | default 0                                   |

**Unique constraint:** `(colony_id, population_code)`
**No timestamps.**

#### Test requirements — `tests/Feature/Database/Migrations/CreateColonyPopulationTableMigrationTest.php`

1. **table has expected columns** — use `Schema::hasColumns()`
2. **`rebel_quantity` defaults to 0**
3. **composite unique prevents duplicate `population_code` per colony** — insert same code twice for same colony, assert
   exception
4. **same `population_code` may exist on different colonies**
5. **deleting a colony cascades to population rows**

#### Done when

- Table exists exactly as spec'd.
- Unique and FK cascade behavior covered by tests.

---

### Task A5 — Create `colony_template_population` table
**Status:** TODO
**Effort:** S
**Depends on:** A1, A2

#### Files to create

- Migration: `create_colony_template_population_table`
- `tests/Feature/Database/Migrations/CreateColonyTemplatePopulationTableMigrationTest.php`

#### Commands

```bash
php artisan make:migration create_colony_template_population_table --create=colony_template_population
php artisan make:test --phpunit Database/Migrations/CreateColonyTemplatePopulationTableMigrationTest
vendor/bin/pint --dirty --format agent
php artisan test --compact tests/Feature/Database/Migrations/CreateColonyTemplatePopulationTableMigrationTest.php
```

#### Target schema

| Column               | Type                             | Notes           |
|----------------------|----------------------------------|-----------------|
| `id`                 | integer PK                       |                 |
| `colony_template_id` | integer FK → colony_templates.id | cascadeOnDelete |
| `population_code`    | string                           |                 |
| `quantity`           | integer                          |                 |
| `pay_rate`           | float                            |                 |

**Unique constraint:** `(colony_template_id, population_code)`
**No timestamps.**

#### Test requirements — `tests/Feature/Database/Migrations/CreateColonyTemplatePopulationTableMigrationTest.php`

1. **table has expected columns**
2. **composite unique prevents duplicate `population_code` per template**
3. **same `population_code` may exist on different templates**
4. **deleting a template cascades to population rows**
5. **multiple templates can exist for one game** — confirms `colony_templates.game_id` unique was dropped in A2

#### Done when

- Table exists exactly as spec'd.
- Uniqueness and cascade behavior covered.

---

### Task A6 — Create `turns` table
**Status:** TODO
**Effort:** S
**Depends on:** A1

#### Files to create

- Migration: `create_turns_table`
- `tests/Feature/Database/Migrations/CreateTurnsTableMigrationTest.php`

#### Commands

```bash
php artisan make:migration create_turns_table --create=turns
php artisan make:test --phpunit Database/Migrations/CreateTurnsTableMigrationTest
vendor/bin/pint --dirty --format agent
php artisan test --compact tests/Feature/Database/Migrations/CreateTurnsTableMigrationTest.php
```

#### Target schema

| Column              | Type                  | Notes                          |
|---------------------|-----------------------|--------------------------------|
| `id`                | integer PK            |                                |
| `game_id`           | integer FK → games.id | cascadeOnDelete                |
| `number`            | integer               | 0 for setup, 1+ for subsequent |
| `status`            | string                | default `'pending'`            |
| `reports_locked_at` | datetime              | nullable                       |
| `created_at`        | datetime              |                                |
| `updated_at`        | datetime              |                                |

**Unique constraint:** `(game_id, number)`

#### Implementation notes

- Use `$table->string('status')->default('pending')` (or `TurnStatus::Pending->value`).
- Use `$table->dateTime('reports_locked_at')->nullable()`.
- Use `$table->timestamps()`.
- Add `$table->unique(['game_id', 'number'])`.

#### Test requirements — `tests/Feature/Database/Migrations/CreateTurnsTableMigrationTest.php`

1. **table has expected columns**
2. **default `status` is `pending`**
3. **composite unique prevents duplicate turn number in same game**
4. **same turn number may exist in different games**
5. **deleting a game cascades to turns**
6. **`reports_locked_at` is nullable**

#### Done when

- `turns` table exists with lifecycle fields and uniqueness constraint.
- Defaults and FK cascade covered by tests.

---

## Execution Order

```
A1 (enums) → A2 (rebuild inventory/templates) → A3 (rebuild colonies) → A4 (colony_population) → A5 (colony_template_population) → A6 (turns)
```

Each task is a separate commit boundary.

---

## Group A Acceptance Criteria

Group A is complete when all of the following are true:

### Enums

- [ ] `app/Enums/TurnStatus.php` exists with values: `pending`, `generating`, `completed`, `closed`
- [ ] `app/Enums/PopulationClass.php` exists with 9 cases: `UEM`, `USK`, `PRO`, `SLD`, `CNW`, `SPY`, `PLC`, `SAG`, `TRN`
- [ ] `app/Enums/UnitCode.php` exists with the exact 30 codes from the mapping table
- [ ] `app/Enums/ColonyKind.php` exists with values: `COPN`, `CENC`, `CORB`

### Rebuild Migrations

- [ ] `colony_inventory.unit` is string (was integer)
- [ ] `colony_template_items.unit` is string (was integer)
- [ ] `colony_templates.kind` is string (was integer)
- [ ] `colony_templates.game_id` is **not unique** (multi-colony templates per game)
- [ ] `colonies.kind` is string (was integer)
- [ ] `colonies` has new columns: `name`, `is_on_surface`, `rations`, `sol`, `birth_rate`, `death_rate`
- [ ] Both rebuild migrations use the SQLite temp-table copy/rename pattern
- [ ] No rebuild uses `->change()`
- [ ] Unknown legacy integer values fail loudly with `RuntimeException`

### New Tables

- [ ] `colony_population` exists with unique `(colony_id, population_code)` and cascade delete
- [ ] `colony_template_population` exists with unique `(colony_template_id, population_code)` and cascade delete
- [ ] `turns` exists with unique `(game_id, number)`, cascade delete, nullable `reports_locked_at`

### Foreign Keys / Cascades

- [ ] Rebuilt tables retain correct cascade FKs
- [ ] New tables have correct cascade FKs
- [ ] `turns.game_id` cascades on game delete

### Test Coverage

- [ ] Every task has its own PHPUnit test file
- [ ] Rebuild migrations are tested with explicit data verification
- [ ] Additive tables are tested with schema, uniqueness, and cascade assertions

### Quality Gate

```bash
php artisan test --compact tests/Unit/Enums/LayerOneEnumsTest.php
php artisan test --compact tests/Feature/Database/Migrations/RebuildColonyInventoryAndTemplatesMigrationTest.php
php artisan test --compact tests/Feature/Database/Migrations/RebuildColoniesTableMigrationTest.php
php artisan test --compact tests/Feature/Database/Migrations/CreateColonyPopulationTableMigrationTest.php
php artisan test --compact tests/Feature/Database/Migrations/CreateColonyTemplatePopulationTableMigrationTest.php
php artisan test --compact tests/Feature/Database/Migrations/CreateTurnsTableMigrationTest.php
vendor/bin/pint --dirty --format agent
```

All tests must pass and Pint must report no formatting issues.

---

## Out of Scope for Group A

Do **not** pull these into this burndown:

- Model casts / relationships (Group B)
- Factory updates (Group B)
- `EmpireCreator` changes (Group E)
- `TemplateController` / request validation changes (Group D)
- Turn 0 creation on activation (Group E)
- Report tables / generator / controller work (Groups F–I)
