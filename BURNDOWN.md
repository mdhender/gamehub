# Game Generation Workflow

## Design

The application has three distinct phases for a game's lifecycle, and it is important not to conflate them:

1. **Game creation** — an admin creates the game record and configures its name and PRNG seed. *(Done.)*
2. **Member management** — the GM adds players and assigns roles (GM / Player). *(Done.)*
3. **Game generation** — the GM generates the universe step by step, creates home systems, and assigns empires to players.

This burndown covers phase 3.

### State Machine

The `games` table carries a `status` column that gates what actions are available:

| Status                  | Meaning                                                                                                          |
|-------------------------|------------------------------------------------------------------------------------------------------------------|
| `setup`                 | Initial state. Templates can be edited. No cluster data exists.                                                  |
| `stars_generated`       | 100 stars placed in the coordinate cube. GM may review, edit star data, or delete this step.                     |
| `planets_generated`     | Planets generated for all stars. GM may review, edit planet data, or delete this step.                           |
| `deposits_generated`    | Deposits generated for all planets. GM may review or delete this step.                                           |
| `home_system_generated` | At least one home system has been created. GM may add more, delete all home systems, or activate the game.       |
| `active`                | Game is live. GM can assign empires and add home systems, but cannot modify or delete cluster data or templates. |

Transitions are forward: `setup → stars_generated → planets_generated → deposits_generated → home_system_generated → active`.

Before activation, the GM can **delete** a step, which cascades to all downstream data and reverts the status:

- Deleting home systems → reverts to `deposits_generated`
- Deleting deposits → also deletes home systems, empires, colonies → reverts to `planets_generated`
- Deleting planets → also deletes deposits, home systems, empires, colonies → reverts to `stars_generated`
- Deleting stars → deletes everything → reverts to `setup` (PRNG resets to initial seed)

Once `active`, no steps can be deleted and no cluster data can be modified.

### Data Model

Cluster entities are stored as relational tables, not JSON blobs. The game engine (a future, separate body of work) will rely on this schema for turn adjudication.

Hierarchy: **Game → Stars → Planets → Deposits**

Stars carry their coordinates (`x`, `y`, `z`) directly. Stars sharing coordinates form a system group, distinguished by a `sequence` number within the group. There is no separate `systems` table.

Home systems are an explicit entity linking a star to the home system template's planetary data. They form an ordered queue consumed by empire assignment.

Empires are created after the game is `active` and are linked to both the game and the `game_user` pivot record. Linking to `game_user` (not directly to `users`) preserves per-game context: if a player leaves the game, their empire continues independently and their `game_user` record is deactivated. The player cannot re-join the same game as a new empire. They can resume control of the existing empire later.

### Templates

Templates are uploaded as JSON files and stored relationally (not as JSON columns on `games`). There is one **home system template** and one **colony template** per game. All home systems use the same template; all empires use the same colony template.

The **home system template** defines the complete set of planets for a home system star — typically 7–9 planets, exactly one marked `homeworld: true`. When applied to a star, it replaces all existing planetary and deposit data on that star (kill-and-fill, not merge).

The **colony template** defines the starting colony and inventory for each new empire.

Templates are editable by the GM before the game becomes `active`. Once `active`, templates are locked.

### PRNG and Generation Steps

The `games` table has two PRNG columns: `prng_seed` (the master seed string) and `prng_state` (the serialized engine state, updated as generation steps run).

Four things consume the PRNG:
1. **Star generation** — initializes `GameRng` from `prng_seed` (or GM override), places 100 stars, saves the resulting engine state to `prng_state`.
2. **Planet generation** — picks up `prng_state` from star generation, creates planets for each star, saves new `prng_state`.
3. **Deposit generation** — picks up `prng_state` from planet generation, creates deposits for each planet, saves new `prng_state`.
4. **Random home system selection** — picks up current `prng_state`, selects a star satisfying the minimum distance constraint, saves new `prng_state`. Only when the GM requests random selection; manual selection does not consume the PRNG.

**Empire assignment is deterministic logic** — assign to the first home system in the queue with capacity — and does not consume the PRNG.

Each PRNG-consuming event writes a `generation_steps` record capturing the input state, output state, and the step name. These records track the current pipeline state — they are deleted when their step is deleted (they are not an append-only audit log). When a step is deleted, `game->prng_state` is restored to that step's `input_state`.

### Generate Page

A dedicated page at `/games/{game}/generate`. The Generate tab on the game detail page navigates here. The page is state-driven: each section is rendered based on `game.status`. Sections after the current step are disabled until their prerequisites are met.

### GM Editing Between Steps

The GM can edit star and planet attributes between steps:
- Star coordinates/attributes are editable at `stars_generated` (before planets exist).
- Planet attributes are editable at `planets_generated` (before deposits exist).
- Deposits are not directly editable — delete and re-run deposit generation to change.

To edit data after a downstream step exists, the GM must first delete the downstream step.

### Members Tab Integration

Player members without an assigned empire show a visual indicator on the Members tab. A direct link to the generate page is provided per-member so the GM can quickly jump to assign them.

### Empire Assignment

The GM determines the order in which empires are assigned. There is no fixed sequence. The GM can either assign an empire to a specific home system or let the system pick the first available (ordered by home system creation order, first not-full). "Full" means 25 empires on that home system's homeworld planet — this is a fixed game-wide constant. Maximum 250 empires per game.

The GM may reassign an existing empire to a different home system without creating a new empire.

### Home System Capacity and the GM's Responsibility

Empire assignment will fail if no home system has remaining capacity. The system does not silently create a new home system — the GM must explicitly create one. The GM can create home systems at any time after deposits are generated, including after the game is `active`.

### Concurrency

Only one generation process may run for a given game at a time. This prevents double-click and concurrent GM session issues. Use a database-level lock on the game row for the duration of each generation action.

---

## Tasks

### 1 — Add `status` to the `games` table

Create a migration adding a `status` string column (default `setup`) to `games`. Create a `GameStatus` enum with cases: `Setup`, `StarsGenerated`, `PlanetsGenerated`, `DepositsGenerated`, `HomeSystemGenerated`, `Active`. Add the cast to the `Game` model.

Add status helper methods to the `Game` model:
- `isSetup()`, `isStarsGenerated()`, `isPlanetsGenerated()`, `isDepositsGenerated()`, `isHomeSystemGenerated()`, `isActive()`

Add capability helpers:
- `canEditTemplates()` — true when not `active`
- `canGenerateStars()` — true at `setup`
- `canGeneratePlanets()` — true at `stars_generated`
- `canGenerateDeposits()` — true at `planets_generated`
- `canCreateHomeSystems()` — true at `deposits_generated`, `home_system_generated`, or `active`
- `canDeleteStep()` — true when not `setup` and not `active`
- `canActivate()` — true at `home_system_generated`
- `canAssignEmpires()` — true at `active`

Also add `min_home_system_distance` (float, default `9`) to this migration — this is the per-game minimum Euclidean distance between home system stars used during random selection.

---

### 2 — Relational template schema

Create migrations and models for relational template storage. Templates are per-game.

**`home_system_templates`** — `id`, `game_id` (unique FK), `created_at`, `updated_at`

**`home_system_template_planets`** — `id`, `home_system_template_id`, `orbit` (integer), `type` (enum: `terrestrial`, `asteroid`, `gas_giant`), `habitability` (integer 0–25), `is_homeworld` (boolean, default false)

**`home_system_template_deposits`** — `id`, `home_system_template_planet_id`, `resource` (enum: `gold`, `fuel`, `metallics`, `non_metallics`), `yield_pct` (integer), `quantity_remaining` (integer)

**`colony_templates`** — `id`, `game_id` (unique FK), `kind` (integer), `tech_level` (integer), `created_at`, `updated_at`

**`colony_template_items`** — `id`, `colony_template_id`, `unit` (integer), `tech_level` (integer), `quantity_assembled` (integer), `quantity_disassembled` (integer)

Relationships: `Game` hasOne `HomeSystemTemplate`, `Game` hasOne `ColonyTemplate`. Templates have their child rows.

Validation rules:
- Home system template must have at least one planet.
- Exactly one planet must have `is_homeworld = true`.
- Colony template must have at least one inventory item.

---

### 3 — Template upload and management

Add `GameGenerationController::uploadHomeSystemTemplate` (`POST /games/{game}/generate/templates/home-system`). Accepts a JSON file upload. Validates the JSON structure, deletes any existing home system template for the game, and creates the relational records. Reject if `active`.

Add `GameGenerationController::uploadColonyTemplate` (`POST /games/{game}/generate/templates/colony`). Same pattern for colony templates.

Seed default templates from `sample-data/beta/homeworld-template.json` (renamed concept: now a home system template) and `sample-data/beta/colony-template.json` in the factory and/or a database seeder. Update the sample JSON files to match the new home system template structure if needed.

Wire in the generate page: show a summary of the current template (planet count, homeworld orbit, deposit summary for home system; unit count for colony). Provide upload buttons for each. Disabled once `active`.

---

### 4 — Star migration and model

Create migration and Eloquent model for stars. Stars carry coordinates directly — there is no separate `systems` table.

**`stars`** — `id`, `game_id`, `x` (integer 0–30), `y` (integer 0–30), `z` (integer 0–30), `sequence` (integer, 1-based position within the system group at that coordinate), plus any additional star attributes needed by generation.

The display string for a star's location is formatted as `"XX-YY-ZZ"` (zero-padded). The combination of (`game_id`, `x`, `y`, `z`, `sequence`) is unique.

Relationships: `Game` hasMany `Star`. `Star` hasMany `Planet`.

---

### 5 — Planet and deposit migrations and models

**`planets`** — `id`, `game_id`, `star_id`, `orbit` (1–11), `type` (enum: `terrestrial`, `asteroid`, `gas_giant`), `habitability` (integer 0–25), `is_homeworld` (boolean, default false)

**`deposits`** — `id`, `game_id`, `planet_id`, `resource` (enum: `gold`, `fuel`, `metallics`, `non_metallics`), `yield_pct` (integer), `quantity_remaining` (integer)

Each entity carries `game_id` directly for fast scoping and bulk deletion.

Relationships: `Star` hasMany `Planet`. `Planet` hasMany `Deposit`. `Game` hasMany of each.

---

### 6 — Home system migration and model

Create a **`home_systems`** table to track which stars have been designated as home systems and in what order:

**`home_systems`** — `id`, `game_id`, `star_id` (unique FK — a star can only be a home system once), `homeworld_planet_id` (FK to `planets` — the planet marked `homeworld: true` after template application), `queue_position` (integer — creation order within the game), `created_at`

The combination of (`game_id`, `queue_position`) is unique.

Relationships: `Game` hasMany `HomeSystem` (ordered by `queue_position`). `HomeSystem` belongsTo `Star`. `HomeSystem` belongsTo `Planet` (the homeworld).

This table is populated when the GM creates a home system (task 16). It is deleted when home systems are deleted as part of step deletion (task 15).

---

### 7 — Empire and colony migrations and models

**`empires`** — `id`, `game_id`, `game_user_id` (FK to `game_user` pivot, unique within `game_id`), `name`, `home_system_id` (FK to `home_systems`)

**`colonies`** — `id`, `empire_id`, `planet_id`, `kind` (integer), `tech_level` (integer)

**`colony_inventory`** — `id`, `colony_id`, `unit` (integer), `tech_level` (integer), `quantity_assembled` (integer), `quantity_disassembled` (integer)

Relationships: `Game` hasMany `Empire`. `Empire` belongsTo the `game_user` pivot and belongsTo `HomeSystem`. `Empire` hasMany `Colony`. `Colony` hasMany `ColonyInventory`.

---

### 8 — Generation steps migration and model

Create a **`generation_steps`** table:

**`generation_steps`** — `id`, `game_id`, `step` (enum: `stars`, `planets`, `deposits`, `home_system`), `sequence` (integer, monotonically increasing within a game), `input_state` (text — serialized PRNG state), `output_state` (text — serialized PRNG state after step), `created_at`

This table tracks the current generation pipeline state. Records are **not** append-only — they are deleted when a step is deleted or when upstream steps are deleted.

Add a `GenerationStep` model with a `game` relationship. Add a `generationSteps` hasMany to `Game` (ordered by `sequence`). No `updated_at` column needed.

Manual home system creation does not write a step record.

---

### 9 — `GameGenerationController` and route

Create `GameGenerationController` with a `show` action. Register `GET /games/{game}/generate` → `GameGenerationController::show` named `games.generate`. Authorize with `GamePolicy::update`.

Pass to the Inertia view:
- `game` (with `status`, `min_home_system_distance`, `prng_seed`)
- `homeSystemTemplate` (summary of current template, if any)
- `colonyTemplate` (summary of current template, if any)
- `generationSteps` (ordered by `sequence`)
- `stars` (count and summary when they exist)
- `planets` (count and summary when they exist)
- `deposits` (count when they exist)
- `homeSystems` (list with queue position, star location, empire count, capacity)
- `members` (players only, each with their empire if one exists)

Update the Generate tab to navigate to this route.

---

### 10 — Generate page frontend shell

Create `resources/js/pages/games/generate.tsx`. Render sections for each generation phase. Sections are shells with correct enabled/disabled states based on `game.status` — no actions wired yet:

- **Templates** — upload/view home system and colony templates (enabled while not `active`)
- **Stars** — generate stars, review/edit, delete step (enabled at `setup`)
- **Planets** — generate planets, review/edit, delete step (enabled at `stars_generated`)
- **Deposits** — generate deposits, review, delete step (enabled at `planets_generated`)
- **Home Systems** — create home systems (random or manual), view queue, delete all (enabled at `deposits_generated` or later)
- **Activate Game** — set game to `active` (enabled at `home_system_generated`)
- **Empires** — assign empires to players, view assignments (enabled at `active`)

---

### 11 — `StarGenerator` service

Create `App\Services\StarGenerator`. Accepts a `Game` and an optional seed override string.

Uses `GameRng` (from the provided seed or `game->prng_seed`) to place 100 stars within a 31×31×31 coordinate cube. Stars at the same coordinates form a system group; each gets a unique `sequence` number within the group.

Before writing, delete all existing stars for the game (cascading to planets, deposits, home systems, empires, colonies). Acquire a database lock on the game row before starting.

After writing:
- Save the resulting PRNG engine state to `game->prng_state`
- Write a `generation_steps` record (`step: stars`, `input_state`: the serialized initial PRNG state, `output_state`: the resulting state)
- Set `game->status` to `stars_generated`

Cover with unit tests using a fixed seed to assert deterministic output.

---

### 12 — Generate stars action

Add `GameGenerationController::generateStars` (`POST /games/{game}/generate/stars`). Authorize `update`. Reject if status is not `setup`. Accept an optional `seed` override in the request (falls back to `game->prng_seed`). Dispatch `StarGenerator`. Redirect back.

Wire the Stars section: seed input field (pre-filled with `game.prng_seed`, editable), "Generate Stars" button with loading state. After generation, display a summary (star count, system group count).

---

### 13 — `PlanetGenerator` service

Create `App\Services\PlanetGenerator`. Accepts a `Game` (must be at `stars_generated`).

Uses `GameRng::fromState($game->prng_state)` to generate planets for each star. Up to 11 orbits per star. Determines planet type, habitability, and orbital position.

Before writing, delete all existing planets for the game (cascading to deposits, home systems, empires, colonies). Acquire a database lock on the game row.

After writing:
- Save the resulting PRNG state to `game->prng_state`
- Write a `generation_steps` record (`step: planets`)
- Set `game->status` to `planets_generated`

Cover with unit tests using a fixed seed.

---

### 14 — Generate planets action

Add `GameGenerationController::generatePlanets` (`POST /games/{game}/generate/planets`). Authorize `update`. Reject if status is not `stars_generated`. Dispatch `PlanetGenerator`. Redirect back.

Wire the Planets section: "Generate Planets" button. After generation, display a summary (planet count by type).

---

### 15 — `DepositGenerator` service

Create `App\Services\DepositGenerator`. Accepts a `Game` (must be at `planets_generated`).

Uses `GameRng::fromState($game->prng_state)` to generate deposits for each planet. Up to 40 deposits per planet. Resources: Gold, Fuel, Metallics, Non-metallics.

Before writing, delete all existing deposits for the game (cascading to home systems, empires, colonies). Acquire a database lock on the game row.

After writing:
- Save the resulting PRNG state to `game->prng_state`
- Write a `generation_steps` record (`step: deposits`)
- Set `game->status` to `deposits_generated`

Cover with unit tests using a fixed seed.

---

### 16 — Generate deposits action

Add `GameGenerationController::generateDeposits` (`POST /games/{game}/generate/deposits`). Authorize `update`. Reject if status is not `planets_generated`. Dispatch `DepositGenerator`. Redirect back.

Wire the Deposits section: "Generate Deposits" button. After generation, display a deposit count summary.

---

### 17 — Delete step actions

Add `GameGenerationController::deleteStep` (`DELETE /games/{game}/generate/{step}`). The `step` parameter is one of: `stars`, `planets`, `deposits`, `home_systems`. Authorize `update`. Reject if `active`.

For each step, cascade deletion follows these rules:

| Deleting       | Also deletes                                      | Status reverts to    | PRNG state restored to                          |
|----------------|---------------------------------------------------|----------------------|-------------------------------------------------|
| `home_systems` | Home systems, empires, colonies, colony inventory | `deposits_generated` | Deposit step's `output_state`                   |
| `deposits`     | Deposits + everything above                       | `planets_generated`  | Planet step's `output_state`                    |
| `planets`      | Planets + everything above                        | `stars_generated`    | Star step's `output_state`                      |
| `stars`        | Everything                                        | `setup`              | Cleared (next run initializes from `prng_seed`) |

Delete the corresponding `generation_steps` records (and all downstream records).

Each action must require confirmation from the frontend (confirmation dialog stating what will be destroyed).

---

### 18 — Star editing

Add `GameGenerationController::updateStar` (`PUT /games/{game}/generate/stars/{star}`). Authorize `update`. Reject if status is not `stars_generated`. Validate and update the star's attributes.

Wire in the Stars section: when stars exist and status is `stars_generated`, show a table/list of stars with editable fields. Provide a "Delete Stars" button that calls the delete step action (task 17) with confirmation.

---

### 19 — Planet editing

Add `GameGenerationController::updatePlanet` (`PUT /games/{game}/generate/planets/{planet}`). Authorize `update`. Reject if status is not `planets_generated`. Validate and update the planet's attributes.

Wire in the Planets section: when planets exist and status is `planets_generated`, show a table/list of planets with editable fields. Provide a "Delete Planets" button with confirmation.

---

### 20 — `HomeSystemCreator` service

Create `App\Services\HomeSystemCreator`. Supports two modes:

**Random selection** (`createRandom`):
- Accepts `Game` and acquires a database lock on the game row.
- Picks up `game->prng_state`.
- Selects a star that is at least `game->min_home_system_distance` units (Euclidean distance) from every existing home system star.
- Deletes all existing planets and deposits on the selected star.
- Creates new planets and deposits from `game->homeSystemTemplate` (kill-and-fill).
- Marks the template's homeworld planet with `is_homeworld = true`.
- Creates a `HomeSystem` record with the next `queue_position`.
- Saves the updated `prng_state`.
- Writes a `generation_steps` record (`step: home_system`).
- Sets status to `home_system_generated` if not already `active`.

**Manual selection** (`createManual`):
- Accepts `Game` and a specific `Star` (identified by coordinates + sequence).
- No distance constraint is enforced.
- Same kill-and-fill of planetary data from the template.
- Creates a `HomeSystem` record with the next `queue_position`.
- Does **not** consume the PRNG or write a step record.
- Sets status to `home_system_generated` if not already `active`.

Guards:
- A star cannot be designated as a home system more than once.
- The game must have a home system template.

Cover with feature tests for both modes, distance constraint enforcement, duplicate star rejection.

---

### 21 — Home system creation actions

Add `GameGenerationController::createHomeSystemRandom` (`POST /games/{game}/generate/home-systems/random`). Authorize `update`. Reject if status is before `deposits_generated`. Dispatch `HomeSystemCreator::createRandom`. Redirect back.

Add `GameGenerationController::createHomeSystemManual` (`POST /games/{game}/generate/home-systems/manual`). Accepts star identification (coordinates + sequence, or star ID). Authorize `update`. Reject if status is before `deposits_generated`. Dispatch `HomeSystemCreator::createManual`. Redirect back.

Wire in the Home Systems section:
- Show the current home system queue (star location, queue position, empire count / 25 capacity).
- "Create Random Home System" button (available at `deposits_generated`, `home_system_generated`, or `active`).
- Star selector for manual creation (dropdown or coordinate + sequence input).
- "Delete All Home Systems" button with confirmation (only before `active`).

---

### 22 — Activate game action

Add `GameGenerationController::activate` (`POST /games/{game}/generate/activate`). Authorize `update`. Reject if status is not `home_system_generated`. Set `game->status` to `active`. Redirect back.

Wire in the Activate section: show a summary of the game state (star count, planet count, deposit count, home system count and total capacity). "Activate Game" button with confirmation dialog — activation locks templates and cluster data permanently. The GM can still add home systems and assign empires after activation.

---

### 23 — `EmpireCreator` service

Create `App\Services\EmpireCreator`. Accepts a `Game` (must be `active`), a `GameUser` pivot record, and an optional target `HomeSystem`.

Logic:
1. If a target home system is provided, assign to it (if not full).
2. Otherwise, assign to the first home system in the queue (ordered by `queue_position`) that is not full (< 25 empires on its homeworld planet).
3. If no home system has capacity, throw an exception. The GM must create a new home system before retrying.
4. Reject if the game already has 250 empires.
5. Reject if the `GameUser` already has an empire in this game.
6. Reject if the `GameUser` is deactivated.
7. Create the `Empire` record linked to the game, the `game_user` pivot, and the home system.
8. Create the starting `Colony` on the homeworld planet and its `ColonyInventory` from the game's colony template.

No PRNG is consumed. No step record is written.

Cover with feature tests: happy path (specific home system, first available), full home system rejection, 250-cap rejection, duplicate empire guard, deactivated member guard, colony template application.

---

### 24 — Empire assignment actions and Members tab

**Generate page (Empires section):** Show the current home system status (how many home systems exist, total capacity, total empires assigned) and the player member list. Each member row displays their name and either their empire name + home system location, or an "Assign Empire" button.

The "Assign Empire" button allows the GM to either pick a specific home system or use "first available." When all home systems are at capacity, the "Assign Empire" buttons are disabled and a message directs the GM to create a new home system.

Add `GameGenerationController::createEmpire` (`POST /games/{game}/generate/empires`). Accepts `game_user_id` and optional `home_system_id`. Authorize `update`. Reject if not `active`. Dispatch `EmpireCreator`. Return a validation error (not an exception) if no home system has capacity — the UI should surface this clearly.

Add `GameGenerationController::reassignEmpire` (`PUT /games/{game}/generate/empires/{empire}`). Accepts a new `home_system_id`. Authorize `update`. Reject if not `active`. Update the empire's home system and move its colony to the new homeworld planet.

**Members tab:** Player rows without an assigned empire show a visual indicator (e.g. a muted badge "No empire"). Include a link to the generate page so the GM can jump directly to assign them.

---

### 25 — Cluster JSON download

Add `GameGenerationController::download` (`GET /games/{game}/generate/download`). Returns the cluster data as a JSON file download (stars, planets, deposits — structured for readability). The GM uses this for local analysis. Only available when status is `stars_generated` or later.

Wire a "Download JSON" link in the generate page, visible whenever cluster data exists.

---

## Task Status
To be updated upon completion of each task.

| Task | Description                               | Status |
|------|-------------------------------------------|--------|
| 1    | Add `status` to the `games` table         | TODO   |
| 2    | Relational template schema                | TODO   |
| 3    | Template upload and management            | TODO   |
| 4    | Star migration and model                  | TODO   |
| 5    | Planet and deposit migrations and models  | TODO   |
| 6    | Home system migration and model           | TODO   |
| 7    | Empire and colony migrations and models   | TODO   |
| 8    | Generation steps migration and model      | TODO   |
| 9    | `GameGenerationController` and route      | TODO   |
| 10   | Generate page frontend shell              | TODO   |
| 11   | `StarGenerator` service                   | TODO   |
| 12   | Generate stars action                     | TODO   |
| 13   | `PlanetGenerator` service                 | TODO   |
| 14   | Generate planets action                   | TODO   |
| 15   | `DepositGenerator` service                | TODO   |
| 16   | Generate deposits action                  | TODO   |
| 17   | Delete step actions                       | TODO   |
| 18   | Star editing                              | TODO   |
| 19   | Planet editing                            | TODO   |
| 20   | `HomeSystemCreator` service               | TODO   |
| 21   | Home system creation actions              | TODO   |
| 22   | Activate game action                      | TODO   |
| 23   | `EmpireCreator` service                   | TODO   |
| 24   | Empire assignment actions and Members tab | TODO   |
| 25   | Cluster JSON download                     | TODO   |

---

## Exit Criteria

- [ ] Game status transitions correctly through `setup → stars_generated → planets_generated → deposits_generated → home_system_generated → active`
- [ ] Before `active`, the GM can delete any step, which cascades to all downstream data and reverts the status
- [ ] Once `active`, no steps can be deleted and no cluster/template data can be modified
- [ ] GM can upload home system and colony templates as JSON files; templates are stored relationally
- [ ] Templates are locked once the game is `active`
- [ ] GM can generate stars with a seed (defaulting to `game.prng_seed`) and see a summary
- [ ] GM can override the seed at star generation without permanently changing `game.prng_seed`
- [ ] GM can generate planets and deposits as separate steps, each chaining PRNG state from the prior step
- [ ] GM can edit star attributes at `stars_generated` (before planets exist)
- [ ] GM can edit planet attributes at `planets_generated` (before deposits exist)
- [ ] GM can create home systems by random selection (consumes PRNG, respects minimum distance) or manual selection (no PRNG)
- [ ] Random home system selection enforces minimum Euclidean distance N (per-game, default 9) from existing home systems
- [ ] Manual home system selection allows any star with no distance constraint
- [ ] Home system creation replaces all planetary/deposit data on the selected star (kill-and-fill from template)
- [ ] A star cannot be designated as a home system more than once
- [ ] Home systems form an ordered queue by creation order
- [ ] GM can activate the game once at least one home system exists
- [ ] After activation, the GM can still create home systems and assign empires
- [ ] GM can assign empires to a specific home system or use first-available queue order
- [ ] Empire assignment finds the first home system in the queue with capacity; it fails with a clear error if no home system has capacity — no silent generation
- [ ] "Full" means 25 empires on the home system's homeworld planet (fixed game-wide constant)
- [ ] Maximum 250 empires per game
- [ ] GM can reassign an existing empire to a different home system
- [ ] A player member cannot be assigned more than one empire per game
- [ ] A deactivated member's empire persists; the member cannot be assigned a new empire
- [ ] Player members without an empire are indicated on the Members tab with a link to the generate page
- [ ] Generation steps are recorded for each PRNG-consuming event and deleted when their step is deleted
- [ ] Each generator is deterministic: the same input state always produces the same output (proven by tests with fixed seeds)
- [ ] Only one generation process can run per game at a time (database-level concurrency guard)
- [ ] All controller actions are covered by feature tests; generator services are covered by unit/feature tests
