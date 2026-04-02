# Setup Report Design

This document describes the turn model, population system, and setup report (Turn 0 report) architecture. It covers the data model, report generator service, GM workflow, and the two-layer build plan.

The setup report is the initial report delivered to each empire after the GM activates a game. It tells the player: here is your colony, here is what you have, here is what your homeworld looks like.

---

## Concepts

### SORC — Ship OR Colony

The old engine uses a single `sorcs` table for both ships and colonies, distinguished by a `sorc_cd` discriminator (`COPN`, `CENC`, `CORB`, `SHIP`). This new design uses **two tables** — `colonies` and `ships` — because:

- Colonies can have mines; ships cannot.
- Ships can move between orbits and systems; colonies cannot.
- Laravel's Eloquent models are cleaner with separate tables than with single-table inheritance.
- The game engine reads both into its own data structures for adjudication; these tables are primarily for reporting.

Both colonies and ships share a common set of sub-tables: inventory, population, factory groups, farm groups. Mining groups are colony-only. The report tables mirror this split — `turn_report_colonies` and `turn_report_ships` as separate section tables.

### Turn

A turn is a discrete game cycle. Turn 0 is special — it is created automatically when the GM activates the game and represents the initial state before any orders are processed. The GM runs the report generator to materialize setup reports for all empires that have been assigned.

### Report (data warehouse pattern)

Reports are **materialized snapshots** stored in dedicated tables, not computed on the fly. This means:

- Reports are immutable once the turn is locked.
- The GM can re-run the report generator before locking, which deletes and recreates report data for that turn.
- Frontend queries hit report tables directly — no joins across the live game state.
- Historical reports survive even if game state changes in subsequent turns.

---

## Old Engine Mapping

The old engine's report schema in `a01.db` maps to the new design as follows:

| Old Engine (a01.db) | New Design | Setup Report? | Layer |
|---|---|---|---|
| `reports` (per SORC per turn) | `turn_reports` (per empire per turn) | ✅ | 1 |
| `sorcs` (colony/ship state) | `turn_report_colonies`, `turn_report_ships` | ✅ (colonies only at Turn 0) | 1 |
| `inventory` (per SORC) | `turn_report_colony_inventory` (Layer 1), `turn_report_ship_inventory` (Layer 2) | ✅ | 1 |
| `population` (per SORC) | `turn_report_colony_population` (Layer 1), `turn_report_ship_population` (Layer 2) | ✅ | 1 |
| `deposits` + survey | `turn_report_survey_deposits` | ✅ (homeworld deposits only) | 1 |
| `report_surveys` + `report_survey_deposits` | `turn_report_surveys`, `turn_report_survey_deposits` | ✅ (homeworld planet only; system report is Layer 2) | 1 |
| `mining_groups` + `mining_group` | `turn_report_mining_groups` | ❌ (no mines at Turn 0) | 2 |
| `factory_groups` + `factory_group` | `turn_report_colony_factory_groups`, `turn_report_ship_factory_groups` | ❌ (no factories at Turn 0) | 2 |
| `farm_groups` + `farm_group` | `turn_report_colony_farm_groups`, `turn_report_ship_farm_groups` | ❌ (no farms at Turn 0) | 2 |
| `report_production_inputs` | `turn_report_colony_production_inputs`, `turn_report_ship_production_inputs` | ❌ (no production at Turn 0) | 2 |
| `report_production_outputs` | `turn_report_colony_production_outputs`, `turn_report_ship_production_outputs` | ❌ (no production at Turn 0) | 2 |
| `report_probes` + `report_probe_sorcs` | `turn_report_probes`, `turn_report_probe_sorcs` | ❌ (no probes at Turn 0) | 2 |
| `report_spies` | `turn_report_colony_spies`, `turn_report_ship_spies` | ❌ (no espionage at Turn 0) | 2 |

---

## Lifecycle Overview

```
[Game activated] → Turn 0 created (status: pending)
                       │
                       ├─ GM assigns empires (over time)
                       │
                       ├─ GM runs "Generate Reports"
                       │      └─ Materializes turn_reports for all empires with colonies
                       │      └─ Idempotent: deletes and recreates for this turn
                       │
                       ├─ GM adds more players, assigns empires
                       │
                       ├─ GM re-runs "Generate Reports"
                       │      └─ New empires get reports; existing reports are refreshed
                       │
                       └─ GM locks turn (reports_locked_at)
                              └─ No more report generation for this turn
```

---

## Data Model

### `turns` table

Tracks turn lifecycle for a game.

| Column | Type | Notes |
|---|---|---|
| `id` | integer PK | |
| `game_id` | integer FK → games | |
| `number` | integer | 0 for setup, 1+ for subsequent turns |
| `status` | string (enum) | `pending`, `generating`, `completed`, `closed` |
| `reports_locked_at` | datetime nullable | When set, no more report generation for this turn |
| `created_at` | datetime | |
| `updated_at` | datetime | |

**Unique constraint:** `(game_id, number)`

**Status transitions:**

| Status | Meaning |
|---|---|
| `pending` | Turn exists but reports have not been generated yet |
| `generating` | Report generator is currently running (concurrency guard) |
| `completed` | Reports have been generated; GM can re-run or lock |
| `closed` | Turn is locked; reports are immutable; next turn can begin |

Turn 0 is auto-created when the game is activated. Its initial status is `pending`.

### `turn_reports` table

One record per empire per turn. The report header.

| Column | Type | Notes |
|---|---|---|
| `id` | integer PK | |
| `game_id` | integer FK → games | Denormalized for fast scoping |
| `turn_id` | integer FK → turns | |
| `empire_id` | integer FK → empires | |
| `generated_at` | datetime | When this report was last generated |

**Unique constraint:** `(turn_id, empire_id)`

Re-running the generator deletes and recreates the `turn_report` row (and all child rows via cascade) for each empire.

### `turn_report_colonies` table

Denormalized snapshot of each colony owned by the empire at report time.

| Column | Type | Notes |
|---|---|---|
| `id` | integer PK | |
| `turn_report_id` | integer FK → turn_reports | cascadeOnDelete |
| `source_colony_id` | integer nullable | Reference to live colony at snapshot time (plain integer, not FK — historical reports survive colony deletion) |
| `name` | string | Colony name at report time |
| `kind` | string | Colony type code (COPN, CENC, CORB) |
| `tech_level` | integer | |
| `planet_id` | integer nullable | Denormalized (not FK — snapshot survives planet changes) |
| `orbit` | integer | Denormalized from planet |
| `star_x` | integer | Denormalized from star |
| `star_y` | integer | Denormalized from star |
| `star_z` | integer | Denormalized from star |
| `star_sequence` | integer | Denormalized from star |
| `is_on_surface` | boolean | Surface or orbital |
| `rations` | float | Ration level (1.0 = full) |
| `sol` | float | Standard of living |
| `birth_rate` | float | |
| `death_rate` | float | |

### `turn_report_ships` table (Layer 2)

Same structure as `turn_report_colonies` but for ships. Deferred to Layer 2. See Layer 2 Tables section below.

### `turn_report_colony_inventory` table

Snapshot of inventory for a colony. Mirrors the live `colony_inventory` schema for straightforward snapshotting.

| Column | Type | Notes |
|---|---|---|
| `id` | integer PK | |
| `turn_report_colony_id` | integer FK → turn_report_colonies | cascadeOnDelete |
| `unit_code` | string | Unit type code (FCT, FRM, MIN, FUEL, METS, etc.) |
| `tech_level` | integer | 0 for resources, 1+ for manufactured |
| `quantity_assembled` | integer | Assembled (operational) quantity |
| `quantity_disassembled` | integer | Disassembled (stored) quantity |

**Note:** In Layer 2, a separate `turn_report_ship_inventory` table will be added for ships, following the same schema but with `turn_report_ship_id` FK. This avoids the nullable polymorphic FK pattern and gives each entity type clean Eloquent relations and proper cascading.

### `turn_report_colony_population` table

Snapshot of population classes for a colony.

| Column | Type | Notes |
|---|---|---|
| `id` | integer PK | |
| `turn_report_colony_id` | integer FK → turn_report_colonies | cascadeOnDelete |
| `population_code` | string | UEM, USK, PRO, SLD, CNW, SPY, PLC, SAG, TRN |
| `quantity` | integer | |
| `pay_rate` | float | Current pay rate |
| `rebel_quantity` | integer | Rebels in this class |

**Note:** In Layer 2, a separate `turn_report_ship_population` table will be added for ships.

### `turn_report_surveys` table

Snapshot of planet survey data visible to the empire. For the setup report, this includes **only the homeworld planet**. System-level surveys (all planets in the home system) are a separate report type designed in a future iteration.

| Column | Type | Notes |
|---|---|---|
| `id` | integer PK | |
| `turn_report_id` | integer FK → turn_reports | cascadeOnDelete |
| `planet_id` | integer nullable | Denormalized (plain integer, not FK — snapshot survives planet changes) |
| `orbit` | integer | Denormalized |
| `star_x` | integer | Denormalized |
| `star_y` | integer | |
| `star_z` | integer | |
| `star_sequence` | integer | |
| `planet_type` | string | terrestrial, asteroid, gas_giant |
| `habitability` | integer | |

### `turn_report_survey_deposits` table

Deposit data visible in a survey. For the setup report, the homeworld shows **all** deposits (the homeworld is special — full visibility without requiring a survey action).

| Column | Type | Notes |
|---|---|---|
| `id` | integer PK | |
| `turn_report_survey_id` | integer FK → turn_report_surveys | cascadeOnDelete |
| `deposit_no` | integer | Sequence number within planet (1-based) |
| `resource` | string | gold, fuel, metallics, non_metallics |
| `yield_pct` | integer | |
| `quantity_remaining` | integer | |

---

## Layer 2 Tables (deferred)

These tables follow the same pattern — child tables of `turn_reports` with denormalized snapshots. They will be added via additive migrations when their corresponding engine features are built.

Colony-specific and ship-specific variants use separate tables (e.g., `turn_report_colony_factory_groups` and `turn_report_ship_factory_groups`) rather than nullable polymorphic FKs. This gives each entity type clean Eloquent relations, proper FK cascading, and no nullable column ambiguity.

### `turn_report_ships`

| Column | Type | Notes |
|---|---|---|
| `id` | integer PK | |
| `turn_report_id` | integer FK → turn_reports | cascadeOnDelete |
| `source_ship_id` | integer nullable | Reference to live ship at snapshot time (plain integer, not FK) |
| `name` | string | Ship name at report time |
| `kind` | string | Ship type code |
| `tech_level` | integer | |
| `orbit_id` | integer nullable | Current orbit (null if in transit) |
| `star_x` | integer | Current location |
| `star_y` | integer | |
| `star_z` | integer | |
| `star_sequence` | integer | |
| `is_on_surface` | boolean | |
| `rations` | float | |
| `sol` | float | |
| `birth_rate` | float | |
| `death_rate` | float | |

### `turn_report_ship_inventory`

Same schema as `turn_report_colony_inventory` but with `turn_report_ship_id` FK.

### `turn_report_ship_population`

Same schema as `turn_report_colony_population` but with `turn_report_ship_id` FK.

### `turn_report_mining_groups`

| Column | Type | Notes |
|---|---|---|
| `id` | integer PK | |
| `turn_report_colony_id` | integer FK → turn_report_colonies | cascadeOnDelete |
| `group_no` | integer | Mining group number (1–35) |
| `deposit_id` | integer nullable | Which deposit this group mines (plain integer, not FK) |
| `deposit_no` | integer | Denormalized deposit sequence |
| `deposit_resource` | string | Denormalized resource type |
| `deposit_yield_pct` | integer | Denormalized yield |
| `deposit_qty_remaining` | integer | Denormalized quantity |
| `unit_code` | string | Mine unit code (always MIN for now) |
| `tech_level` | integer | |
| `nbr_of_units` | integer | Number of mine units in this group |

### `turn_report_colony_factory_groups` / `turn_report_ship_factory_groups`

| Column | Type | Notes |
|---|---|---|
| `id` | integer PK | |
| `turn_report_colony_id` or `turn_report_ship_id` | integer FK | cascadeOnDelete |
| `group_no` | integer | Factory group number (1–30) |
| `unit_code` | string | Factory unit code (FCT) |
| `tech_level` | integer | |
| `nbr_of_units` | integer | |
| `orders_code` | string | What this group is producing |
| `orders_tech_level` | integer | |
| `wip_25pct_qty` | integer | Work in process — 25% complete |
| `wip_50pct_qty` | integer | Work in process — 50% complete |
| `wip_75pct_qty` | integer | Work in process — 75% complete |

### `turn_report_colony_farm_groups` / `turn_report_ship_farm_groups`

| Column | Type | Notes |
|---|---|---|
| `id` | integer PK | |
| `turn_report_colony_id` or `turn_report_ship_id` | integer FK | cascadeOnDelete |
| `group_no` | integer | Farm group number (1–30) |
| `unit_code` | string | Farm unit code (FRM) |
| `tech_level` | integer | |
| `nbr_of_units` | integer | |

### `turn_report_colony_production_inputs` / `turn_report_ship_production_inputs`

| Column | Type | Notes |
|---|---|---|
| `id` | integer PK | |
| `turn_report_colony_id` or `turn_report_ship_id` | integer FK | cascadeOnDelete |
| `category` | string | e.g., "farming", "mining", "manufacturing" |
| `fuel` | integer | Fuel consumed |
| `gold` | integer | Gold consumed |
| `metals` | integer | Metals consumed |
| `non_metals` | integer | Non-metals consumed |

### `turn_report_colony_production_outputs` / `turn_report_ship_production_outputs`

| Column | Type | Notes |
|---|---|---|
| `id` | integer PK | |
| `turn_report_colony_id` or `turn_report_ship_id` | integer FK | cascadeOnDelete |
| `category` | string | |
| `unit_code` | string | What was produced |
| `tech_level` | integer | |
| `quantity_farmed` | integer | |
| `quantity_mined` | integer | |
| `quantity_manufactured` | integer | |

### `turn_report_probes`

| Column | Type | Notes |
|---|---|---|
| `id` | integer PK | |
| `turn_report_id` | integer FK → turn_reports | cascadeOnDelete |
| `orbit_id` | integer | Target orbit |
| `star_x` | integer | Target system |
| `star_y` | integer | |
| `star_z` | integer | |
| `habitability` | integer | Detected habitability |
| `fuel_qty` | integer | Estimated fuel deposits |
| `gold_qty` | integer | Estimated gold deposits |
| `metals_qty` | integer | Estimated metals deposits |
| `non_metals_qty` | integer | Estimated non-metals deposits |

### `turn_report_probe_sorcs`

| Column | Type | Notes |
|---|---|---|
| `id` | integer PK | |
| `turn_report_probe_id` | integer FK → turn_report_probes | cascadeOnDelete |
| `empire_id` | integer | Empire that controls the detected SORC |
| `sorc_type` | string | Colony or ship |
| `sorc_name` | string | |
| `sorc_mass` | integer | Estimated mass |

### `turn_report_colony_spies` / `turn_report_ship_spies`

| Column | Type | Notes |
|---|---|---|
| `id` | integer PK | |
| `turn_report_colony_id` or `turn_report_ship_id` | integer FK | cascadeOnDelete |
| `spy_type` | string | A, B, C, D, E, F, G |
| `quantity` | integer | Number of spies of this type |

---

## New Enums

### `TurnStatus`

```php
enum TurnStatus: string
{
    case Pending = 'pending';
    case Generating = 'generating';
    case Completed = 'completed';
    case Closed = 'closed';
}
```

### `PopulationClass`

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

### `UnitCode`

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

### `ColonyKind`

```php
enum ColonyKind: string
{
    case OpenSurface = 'COPN';
    case Enclosed = 'CENC';
    case Orbital = 'CORB';
}
```

---

## Population System

Population is a new concept not yet in the current GameHub data model. It needs to be added to the **live** colony (and eventually ship) model, not just the report tables.

### Live data tables

**`colony_population`** — population classes for a colony.

| Column | Type | Notes |
|---|---|---|
| `id` | integer PK | |
| `colony_id` | integer FK → colonies | cascadeOnDelete |
| `population_code` | string | UEM, USK, PRO, SLD, CNW, SPY, PLC, SAG, TRN |
| `quantity` | integer | |
| `pay_rate` | float | Current pay rate for this class |
| `rebel_quantity` | integer | Default 0 |

**Unique constraint:** `(colony_id, population_code)`

### Colony template extension

The colony template needs a population section. Extend `colony_template_items` or add a new `colony_template_population` table:

**`colony_template_population`** — starting population for each new empire.

| Column | Type | Notes |
|---|---|---|
| `id` | integer PK | |
| `colony_template_id` | integer FK → colony_templates | cascadeOnDelete |
| `population_code` | string | |
| `quantity` | integer | |
| `pay_rate` | float | Starting pay rate |

**Unique constraint:** `(colony_template_id, population_code)`

### Colony model extension

The `Colony` model gains:

- `kind` column value changes from integer to string (ColonyKind enum)
- `name` column (string, default "Not Named")
- `is_on_surface` column (boolean, default true)
- `rations` column (float, default 1.0)
- `sol` column (float, default 0.0 — calculated by engine)
- `birth_rate` column (float, default 0.0 — calculated by engine)
- `death_rate` column (float, default 0.0 — calculated by engine)
- `population()` hasMany relationship to `ColonyPopulation`

The `EmpireCreator` is extended to populate `colony_population` from the template when creating a new colony.

---

## Setup Report Generator Service

### `App\Services\SetupReportGenerator`

```
SetupReportGenerator::generate(Game $game, Turn $turn): int
```

Returns the number of empire reports generated.

**Preconditions:**
- Game status is `Active`
- Turn number is 0
- Turn status is `pending` or `completed` (not `generating`, not `closed`)

**Process:**

1. Acquire turn via atomic status transition (see Concurrency section below)
2. For each empire in the game that has at least one colony:
   a. Delete any existing `turn_report` for this (turn, empire) — cascade deletes children
   b. Create `turn_report` header
   c. For each colony:
      - Create `turn_report_colonies` with denormalized location data
      - Snapshot `colony_inventory` → `turn_report_colony_inventory`
      - Snapshot `colony_population` → `turn_report_colony_population`
   d. Survey the homeworld:
      - Create a `turn_report_surveys` entry for the homeworld planet
      - Snapshot **all** deposits on the homeworld → `turn_report_survey_deposits`
3. Set turn status to `completed`
4. Return count of empires processed

**Idempotency:** The delete-and-recreate pattern ensures re-running produces clean results.

**Concurrency:** SQLite does not support row-level `FOR UPDATE` locks. Instead, use an atomic status transition:

```php
$updated = Turn::where('id', $turn->id)
    ->whereIn('status', [TurnStatus::Pending, TurnStatus::Completed])
    ->update(['status' => TurnStatus::Generating]);

if ($updated === 0) {
    throw new \RuntimeException('Turn is not available for report generation.');
}
```

If zero rows are affected, another process already acquired the turn or the turn is closed. If the process crashes mid-generation, the status remains `generating` and must be manually reset by the GM.

---

## GM Workflow

### Activation triggers Turn 0

When `GameGenerationController::activate()` sets the game to `Active`, it also creates a Turn 0 record:

```php
Turn::create([
    'game_id' => $game->id,
    'number' => 0,
    'status' => TurnStatus::Pending,
]);
```

### Generate Reports action

New controller action: `TurnReportController::generate(Game $game, Turn $turn)`

- Authorized by `GamePolicy::update` (GM only)
- Validates: game is active, turn is 0, turn is not closed
- Calls `SetupReportGenerator::generate()`
- Redirects back with success count

### Lock Turn action

New controller action: `TurnReportController::lock(Game $game, Turn $turn)`

- Sets `reports_locked_at` to now
- Sets status to `closed`
- After this, no more report generation for this turn

### View Report action (text)

New controller action: `TurnReportController::show(Game $game, Turn $turn, Empire $empire)`

- GM can view any empire's report
- Player can view only their own empire's report
- Reads from report tables, not live game state
- Renders a text-style report in the browser (styled after the original `turn-report.txt` section structure)

### Download Report action (JSON)

New controller action: `TurnReportController::download(Game $game, Turn $turn, Empire $empire)`

- Same authorization as `show`
- Returns the report data as a structured JSON download
- File named `report-{game_id}-turn-{number}-empire-{empire_id}.json`

---

## Game Model Changes

Add relationship and capability helper:

```php
// Game model
public function turns(): HasMany
{
    return $this->hasMany(Turn::class)->orderBy('number');
}

public function currentTurn(): HasOne
{
    return $this->hasOne(Turn::class)->latestOfMany('number');
}

public function canGenerateReports(): bool
{
    $turn = $this->currentTurn;
    return $this->isActive()
        && $turn !== null
        && $turn->reports_locked_at === null
        && $turn->status !== TurnStatus::Generating;
}
```

---

## SQLite Column-Type Migrations

Three tables need column-type changes from integer to string: `colonies.kind`, `colony_inventory.unit`, and `colony_template_items.unit`.

**SQLite does not support `ALTER COLUMN`.** Do not use Laravel's `->change()` method for these. Instead, use a table-rebuild migration pattern:

1. Create a temporary table with the desired schema
2. Copy data with explicit `CASE` mapping (e.g., `1 → 'COPN'`)
3. Drop the original table
4. Rename the temporary table
5. Recreate all indexes and foreign keys

**Data mapping:**

| Table | Column | Old Value | New Value |
|---|---|---|---|
| `colonies` | `kind` | `1` | `COPN` |
| `colony_inventory` | `unit` | integer IDs | String codes (see UnitCode enum) |
| `colony_template_items` | `unit` | integer IDs | String codes (see UnitCode enum) |

The integer-to-string unit code mapping must be explicit and fail fast on unknown integers. The full mapping is defined in the `UnitCode` enum.

The `colony_templates.kind` column follows the same migration: `1 → COPN`.

---

## Build Plan

### Layer 1 — Setup Report (this iteration)

Tasks are grouped by dependency. Groups must be completed in order; tasks within a group may be done in any order.

#### A. Enums and live-schema migrations

| # | Task | Effort |
|---|---|---|
| 1 | Create `TurnStatus`, `PopulationClass`, `UnitCode`, `ColonyKind` enums | S |
| 2 | Migration (table rebuild): `colony_inventory.unit` int → string; `colony_template_items.unit` int → string; `colony_templates.kind` int → string. Use SQLite rebuild pattern, not `->change()`. | M |
| 3 | Migration (table rebuild): `colonies.kind` int → string; add `name`, `is_on_surface`, `rations`, `sol`, `birth_rate`, `death_rate` columns | M |
| 4 | Migration: `colony_population` table with unique `(colony_id, population_code)` | S |
| 5 | Migration: `colony_template_population` table with unique `(colony_template_id, population_code)` | S |
| 6 | Migration: `turns` table with unique `(game_id, number)` | S |

#### B. Update existing models and factories

| # | Task | Effort |
|---|---|---|
| 7 | Update `Colony` model — add `ColonyKind` cast, new fillable columns, `population()` relationship | S |
| 8 | Update `ColonyInventory` model — cast `unit` with `UnitCode` enum | S |
| 9 | Update `ColonyTemplate` model — cast `kind` with `ColonyKind` enum | S |
| 10 | Update `ColonyTemplateItem` model — cast `unit` with `UnitCode` enum | S |
| 11 | Update `ColonyFactory` — use `ColonyKind` enum, add defaults for new columns | S |
| 12 | Update `ColonyInventoryFactory` — use `UnitCode` enum values | S |
| 13 | Update `ColonyTemplateItemFactory` — use `UnitCode` enum values | S |

#### C. New models and factories

| # | Task | Effort |
|---|---|---|
| 14 | Models: `ColonyPopulation`, `ColonyTemplatePopulation` with relationships | S |
| 15 | Model: `Turn` with `TurnStatus` cast, `Game` relationship | S |
| 16 | Add `Game::turns()`, `Game::currentTurn()` relationships | S |
| 17 | Factories: `TurnFactory`, `ColonyPopulationFactory`, `ColonyTemplatePopulationFactory` | S |

#### D. Template ingestion updates

| # | Task | Effort |
|---|---|---|
| 18 | Update `sample-data/beta/colony-template.json` — string unit codes, string kind, population section | S |
| 19 | Update `UploadColonyTemplateRequest` — validate population section and string unit codes | M |
| 20 | Update `TemplateController::uploadColony()` — parse and store population section | S |

#### E. Business logic extensions

| # | Task | Effort |
|---|---|---|
| 21 | Extend `EmpireCreator` to populate `colony_population` from template. Note: `insert()` bypasses casts — use `->value` for enum values. | S |
| 22 | Extend `GameGenerationController::activate()` to create Turn 0 in the same transaction | S |

#### F. Report schema and service

| # | Task | Effort |
|---|---|---|
| 23 | Migration: `turn_reports` table with unique `(turn_id, empire_id)` | S |
| 24 | Migration: `turn_report_colonies` table (no FK to live colonies — plain `source_colony_id` integer) | S |
| 25 | Migration: `turn_report_colony_inventory` table | S |
| 26 | Migration: `turn_report_colony_population` table | S |
| 27 | Migration: `turn_report_surveys` + `turn_report_survey_deposits` tables | S |
| 28 | Models: `TurnReport`, `TurnReportColony`, `TurnReportColonyInventory`, `TurnReportColonyPopulation`, `TurnReportSurvey`, `TurnReportSurveyDeposit` | M |
| 29 | Factories: `TurnReportFactory` and child factories | S |
| 30 | Service: `SetupReportGenerator` (atomic status transition, returns empire count) | M |

#### G. Routes, authorization, and controller

| # | Task | Effort |
|---|---|---|
| 31 | Routes: `TurnReportController` (generate, lock, show, download) with scoped bindings | S |
| 32 | Policy: `TurnReportPolicy` — GM-only for generate/lock; player-or-GM for show/download | S |
| 33 | Controller: `TurnReportController` (generate, lock, show, download) | M |

#### H. Tests

| # | Task | Effort |
|---|---|---|
| 34 | Fix existing tests broken by schema changes (EmpireCreatorTest, GameGenerationControllerTest, template upload tests) | M |
| 35 | New tests: Turn model, SetupReportGenerator service, TurnReportController actions | L |

#### I. Frontend

| # | Task | Effort |
|---|---|---|
| 36 | Run `wayfinder:generate` for new routes and frontend types | S |
| 37 | Frontend: GM report generation UI + text report viewer + JSON download | L |

### Layer 2 — Production & Intelligence Reports (future)

| # | Task | Effort |
|---|---|---|
| 38 | Migration: `turn_report_ships` | S |
| 39 | Migration: `turn_report_ship_inventory`, `turn_report_ship_population` | S |
| 40 | Migration: `turn_report_mining_groups` | S |
| 41 | Migration: `turn_report_colony_factory_groups` + `turn_report_ship_factory_groups` | S |
| 42 | Migration: `turn_report_colony_farm_groups` + `turn_report_ship_farm_groups` | S |
| 43 | Migration: `turn_report_colony_production_inputs/outputs` + `turn_report_ship_production_inputs/outputs` | S |
| 44 | Migration: `turn_report_probes` + `turn_report_probe_sorcs` | S |
| 45 | Migration: `turn_report_colony_spies` + `turn_report_ship_spies` | S |
| 46 | Extend report generator for production/intelligence sections | L |

**Effort key:** S = < 1 hour, M = 1–3 hours, L = 3+ hours

---

## Resolved Design Decisions

### 1. Starting population

From the original game's initial report (`turn-report.txt`), the starting colony population is:

| Class | Code | Quantity | Pay Rate |
|---|---|---|---|
| Unemployable | UEM | 3,500,000 | 0.0 |
| Unskilled | USK | 3,700,000 | 0.125 |
| Professional | PRO | 1,000,000 | 0.375 |
| Soldier | SLD | 1,500,000 | 0.25 |

These will be added to the colony template JSON format. The colony template upload must be updated to accept a `population` section alongside the existing `inventory` section.

### 2. Colony kinds

There are four SORC types, with colonies and ships using separate tables:

| Code | Name | Table |
|---|---|---|
| `COPN` | Open Surface Colony | `colonies` |
| `CENC` | Enclosed Surface Colony | `colonies` |
| `CORB` | Orbiting Colony | `colonies` |
| `SHIP` | Ship | `ships` |

The existing integer `1` in `colonies.kind` maps to `COPN`. This is the only value currently in use.

### 3. Unit codes — migrate to strings

The `colony_inventory.unit` column will be migrated from integer to string codes (e.g., `FCT`, `FRM`, `MIN`). This makes JSON extraction straightforward and aligns with the old engine's code system. The colony template format will also switch from integer unit IDs to string codes.

The `colony_template_items.unit` column follows the same migration. The `colony_templates.kind` column also migrates from integer to string (`1` → `COPN`).

This requires a mapping for existing data. The current colony template uses integer unit IDs — the sample file must be updated with string codes, and a data migration must convert any existing rows.

**Important:** These migrations must use the SQLite table-rebuild pattern (create temp table, copy data with CASE mapping, drop original, rename). See the "SQLite Column-Type Migrations" section above.

### 4. Snapshot FK strategy — no hard FKs to live entities

Report snapshot tables (`turn_report_colonies`, `turn_report_ships`) store `source_colony_id` / `source_ship_id` as **plain nullable integers**, not as foreign keys to live tables. This ensures historical reports survive if a colony or ship is later deleted in gameplay.

Similarly, `turn_report_colonies.planet_id` is a plain integer for traceability, not a constrained FK. The `turn_report_surveys.planet_id` follows the same pattern.

The only hard FKs in the report schema are parent-child relationships within the report tables themselves (e.g., `turn_report_colony_inventory.turn_report_colony_id` → `turn_report_colonies.id`), which cascade on delete for idempotent regeneration.

### 5. Colony/ship report children — separate tables, not polymorphic

Report child tables (`inventory`, `population`, `factory_groups`, etc.) use **separate colony and ship tables** rather than a single table with nullable polymorphic FKs. For example: `turn_report_colony_inventory` and `turn_report_ship_inventory` (Layer 2).

This avoids nullable FK ambiguity, gives each entity type clean Eloquent relations with proper cascading, and eliminates the need for application-level "exactly one must be set" enforcement. SQLite does support `CHECK` constraints, but separate tables are cleaner and more idiomatic in Laravel.

### 6. Report output formats

Two output formats:

- **JSON download** — structured report data for programmatic consumption.
- **Text report view** — rendered in the browser in the style of the original `turn-report.txt`. 100% visual fidelity to the original is not required, but the section structure (Colony ID, Population Report, Storage Report, Operational Units, Mining Report, Manufacturing Report) is preserved.

### 7. Report scope — planet level only

The setup report is a **planet-level report** — it shows the homeworld and its deposits. It does **not** show deposits on other planets in the home system.

A **system-level report** (which shows all planets in the system and their deposits) is a separate report type designed in the next round.

The `turn_report_surveys` table is therefore scoped: for the setup report, it includes only the homeworld planet entry with full deposit visibility. Other planets in the home system are not included in this report type.
