# Game Generation Workflow

## Design

The application has three distinct phases for a game's lifecycle, and it is important not to conflate them:

1. **Game creation** — an admin creates the game record and configures its name and PRNG seed. *(Done.)*
2. **Member management** — the GM adds players and assigns roles (GM / Player). *(Done.)*
3. **Game generation** — the GM generates the game entities and creates empires for each player.

This burndown covers phase 3.

### State Machine

The `games` table carries a `status` column that gates what actions are available:

| Status | Meaning |
|---|---|
| `setup` | Members are being added; nothing has been generated yet |
| `cluster_generated` | Cluster has been generated; GM is reviewing; can still regenerate |
| `active` | GM accepted the cluster; empires can be created; generation is locked |

Transitions are strictly forward: `setup → cluster_generated → active`. Once `active`, the cluster cannot be regenerated and no generation actions can be undone.

### Data Model

Cluster entities are stored as relational tables, not JSON blobs. The game engine (a future, separate body of work) will rely on this schema for turn adjudication.

Hierarchy: **Game → Systems → Stars → Planets → Deposits**

Empires are created after the cluster is accepted and are linked to both the game and the `game_user` pivot record. Linking to `game_user` (not directly to `users`) preserves per-game context: if a player leaves the game, their empire continues independently and their `game_user` record is deactivated. The player cannot re-join the same game as a new empire. They can resume control of the existing empire later.

### Templates

`homeworld_template` and `colony_template` are stored as JSON columns on the `games` table. They are per-game and identical for all players. There are no per-player templates.

### PRNG and the Generation Log

The `games` table has two PRNG columns: `prng_seed` (the master seed string) and `prng_state` (the serialized engine state, updated as generation steps run).

Only two things consume the PRNG:
1. **Cluster generation** — initializes `GameRng` from `prng_seed`, generates the full cluster, saves the resulting engine state to `prng_state`.
2. **Homeworld generation** — triggered only when no existing homeworld has remaining capacity (< 25 empires). Picks up current `prng_state`, generates a new homeworld star, saves new `prng_state`.

**Empire assignment itself is deterministic logic** — find the first homeworld planet with capacity — and does not consume the PRNG or produce a log entry.

Each PRNG-consuming event writes a `generation_logs` record capturing the input state, output state, the step name, and a reference to what was produced (cluster or homeworld planet ID). The GM can see this log and override the seed/state at any step before re-running it. The log is append-only; re-running a step adds a new entry rather than updating the old one.

### Generate Page

A dedicated page at `/games/{game}/generate`. The Generate tab on the game detail page navigates here. The page is state-driven: each step is rendered based on `game.status` and the presence of generated data. Steps after the first are disabled until their prerequisites are met.

For cluster review, the GM can download the cluster data as JSON for local analysis (Excel-friendly). No visual star map.

### Members Tab Integration

Player members without an assigned empire show a visual indicator on the Members tab. A direct link to the generate page (anchored to the empire creation step) is provided per-member so the GM can quickly jump to assign them.

### Empire Assignment Order

The GM determines the order in which empires are assigned. There is no fixed sequence. The GM may assign Player 2 before Player 1 in one session and reverse the order next time.

### Homeworld Capacity and the GM's Responsibility

Empire assignment will fail if no homeworld planet has remaining capacity. The system does not silently generate a new homeworld — the GM must explicitly run the homeworld generation step on the generate page. This keeps the GM in control of when PRNG state is consumed.

The GM may continue assigning empires to players after the game is `active`, provided homeworld capacity remains. Once all homeworlds are full, the GM must generate a new homeworld before further assignment is possible.

---

## Tasks

### 1 — Add `status` to the `games` table

Create a migration adding a `status` string column (default `setup`) to `games`. Create a `GameStatus` enum (`Setup`, `ClusterGenerated`, `Active`). Add the cast and a helper method (`isActive()`, etc.) to the `Game` model.

---

### 2 — Add template columns to the `games` table

Create a migration adding `homeworld_template` (JSON, nullable) and `colony_template` (JSON, nullable) to `games`. Add `array` casts on the `Game` model. Seed default values from `sample-data/beta/homeworld-template.json` and `sample-data/beta/colony-template.json` in the factory and/or a database seeder.

---

### 3 — Cluster entity migrations and models

Create migrations and Eloquent models for the cluster hierarchy. Each entity carries a `game_id` directly (for easy scoping and bulk deletion on regeneration) in addition to its parent FK.

- **`systems`** — `id`, `game_id`, `display` (string, e.g. `"00-14-19"`), `x`, `y`, `z`
- **`stars`** — `id`, `game_id`, `system_id`, `sequence` (integer, position within system)
- **`planets`** — `id`, `game_id`, `star_id`, `orbit` (1–11), `type` (enum: `terrestrial`, `asteroid`, `gas_giant`), `habitability` (0–25)
- **`deposits`** — `id`, `planet_id`, `resource` (enum: `gold`, `fuel`, `metallics`, `non_metallics`), `yield_pct`, `quantity_remaining`

Relationships: `Game` hasMany of each cluster entity. `System` hasMany `Star`. `Star` hasMany `Planet`. `Planet` hasMany `Deposit`.

---

### 4 — Empire and colony migrations and models

- **`empires`** — `id`, `game_id`, `game_user_id` (FK to `game_user` pivot), `name`, `homeworld_planet_id` (FK to `planets`)
- **`colonies`** — `id`, `empire_id`, `planet_id`, `kind` (integer), `tech_level`
- **`colony_inventory`** — `id`, `colony_id`, `unit` (integer), `tech_level`, `quantity_assembled`, `quantity_disassembled`

Relationships: `Game` hasMany `Empire`. `Empire` belongsTo the `game_user` pivot and hasMany `Colony`. `Colony` hasMany `ColonyInventory`.

---

### 5 — Generation log migration and model

Create a **`generation_logs`** table:

- `id`, `game_id`, `step` (enum: `cluster`, `homeworld`), `input_state` (string), `output_state` (string), `subject_id` (nullable integer — the cluster's first system ID or the homeworld planet ID), `created_at`

This table is append-only. Add a `GenerationLog` model with a `game` relationship. Add a `generationLogs` hasMany to `Game`. No `updated_at` column needed.

---

### 6 — `GameGenerationController` and route

Create `GameGenerationController` with a `show` action. Register `GET /games/{game}/generate` → `GameGenerationController::show` named `games.generate`. Authorize with `GamePolicy::update`.

Pass to the Inertia view: `game` (with `status`, `homeworld_template`, `colony_template`), `members` (players only, each with their empire if one exists), and `generationLogs` (ordered newest first).

Update the Generate tab to navigate to this route.

---

### 7 — Generate page frontend shell

Create `resources/js/pages/games/generate.tsx`. Render the four steps as cards in sequence. For this task, cards are shells with correct enabled/disabled states based on `game.status` — no actions wired yet:

- **Step 1 — Configure Templates** (always enabled while not `active`)
- **Step 2 — Generate Cluster** (enabled once templates are saved)
- **Step 3 — Review & Accept** (enabled when `status === 'cluster_generated'`)
- **Step 4 — Create Empires** (enabled when `status === 'active'`; shows player member list)

---

### 8 — Template editor (Step 1)

Wire Step 1. The GM can view and edit `homeworld_template` and `colony_template` using structured forms (not raw JSON textareas): Habitability and a Deposits table for the homeworld; Kind, TechLevel, and an inventory unit table for the colony.

Add `GameGenerationController::updateTemplates` (`PUT /games/{game}/generate/templates`). Validate the shape server-side. Store on the `games` record. Disabled once `status === 'active'`.

---

### 9 — `ClusterGenerator` service

Create `App\Services\ClusterGenerator`. Accepts a `Game` and an optional seed override string. Uses `GameRng` (from the provided seed or `game->prng_seed`) to generate:

- A 31×31×31 cube containing 100 stars distributed across systems
- Each star has 11 orbits; planets placed per game rules (terrestrial in orbits 3–5 are homeworld candidates)
- Each planet receives deposits generated from the PRNG

Before writing, delete all existing cluster entity records for the game (systems, stars, planets, deposits — cascade or explicit). After writing, save the resulting PRNG engine state to `game->prng_state` and write a `GenerationLog` record (step: `cluster`, input: seed used, output: resulting state).

Cover with unit tests using a fixed seed to assert deterministic output.

---

### 10 — Generate cluster action (Step 2)

Add `GameGenerationController::generate` (`POST /games/{game}/generate`). Authorize `update`. Reject if `status === 'active'`. Accept an optional `seed` override in the request (falls back to `game->prng_seed`). Dispatch `ClusterGenerator`. Set `status = 'cluster_generated'`. Redirect back.

Wire the Step 2 card: seed input field (pre-filled with `game.prng_seed`, editable), "Generate" button with loading state. After generation, display a summary (system count, star count, planet count, deposit count) and the output PRNG state from the latest generation log.

---

### 11 — Reset cluster action

Add `GameGenerationController::reset` (`DELETE /games/{game}/generate`). Authorize `update`. Reject if `status === 'active'`. Delete all cluster entities and empire/colony records for the game. Set `status = 'setup'`. Do not delete generation logs (they are append-only audit records). Redirect back.

Wire a "Reset" button in the Step 2 card, visible only when `status === 'cluster_generated'`. Gate behind a confirmation dialog — resetting is destructive and wipes any assigned empires.

---

### 12 — Cluster JSON download (Step 3)

Add `GameGenerationController::download` (`GET /games/{game}/generate/download`). Returns the cluster data as a JSON file download (systems, stars, planets, deposits — structured for readability). The GM uses this for local analysis. Only available when `status` is `cluster_generated` or `active`.

Wire a "Download JSON" link in the Step 3 card.

---

### 13 — Accept cluster action (Step 3)

Add `GameGenerationController::accept` (`POST /games/{game}/generate/accept`). Authorize `update`. Reject if not `cluster_generated`. Set `status = 'active'`. Redirect back.

Wire in Step 3: show the cluster summary alongside the download link. The "Accept Cluster" button permanently locks generation. Gate behind a confirmation dialog.

---

### 14 — `EmpireCreator` service

Create `App\Services\EmpireCreator`. Accepts a `Game` and a `GameUser` pivot record.

Logic:
1. Find the homeworld planet with the most assigned empires that still has capacity (< 25). Assignment is pure logic — no PRNG.
2. If no homeworld has capacity, throw an exception. The GM must run the homeworld generation step explicitly before retrying.
3. Create the `Empire` record linked to the game and the `game_user` pivot.
4. Create the starting `Colony` and its `ColonyInventory` records from `game->colony_template`.

Cover with feature tests: happy path (homeworld exists with capacity), failure when no homeworld has capacity (no silent generation), template application, duplicate empire guard (same `game_user_id` rejected).

---

### 15 — Empire creation step (Step 4) and Members tab indicator

**Generate page (Step 4):** Show the current homeworld status (how many homeworlds exist, total capacity, total empires assigned) and the player member list. Each member row displays their name and either their empire name + homeworld planet, or an "Assign Empire" button.

When all homeworlds are at capacity, the "Assign Empire" buttons are disabled and a "Generate Homeworld" button is shown instead. The GM must generate a new homeworld before further assignment is possible.

Add `GameGenerationController::createEmpire` (`POST /games/{game}/generate/empires`). Accepts `game_user_id`. Authorize `update`. Reject if not `active`. Dispatch `EmpireCreator`. Return a validation error (not an exception) if no homeworld has capacity — the UI should surface this clearly rather than silently failing.

Add `GameGenerationController::generateHomeworld` (`POST /games/{game}/generate/homeworld`). Authorize `update`. Reject if not `active`. Selects the next eligible terrestrial planet (orbit 3–5) with no homeworld designation, applies `game->homeworld_template`, consumes `game->prng_state`, updates it, and writes a `GenerationLog` record (`step: homeworld`). Redirect back.

**Members tab:** Player rows without an assigned empire show a visual indicator (e.g. a muted badge "No empire"). Include a link to the generate page so the GM can jump directly to assign them.

---

## Exit Criteria

- [ ] Game status transitions correctly through `setup → cluster_generated → active`; no backwards transitions are possible via any route
- [ ] GM can configure homeworld and colony templates via structured forms before the cluster is generated
- [ ] GM can generate the cluster with a seed (defaulting to `game.prng_seed`) and see a summary of what was created
- [ ] GM can override the seed at the cluster generation step without permanently changing `game.prng_seed`
- [ ] GM can reset and regenerate the cluster while `status === 'cluster_generated'`; empire data is also wiped on reset
- [ ] Generation logs are written for every PRNG-consuming event (cluster generation, homeworld generation) and are never deleted
- [ ] GM can download cluster data as JSON for external analysis
- [ ] GM can accept the cluster; after acceptance all generation and template actions are locked
- [ ] GM can assign empires to player members in any order after the cluster is accepted
- [ ] Empire assignment finds the first homeworld with capacity; it fails with a clear error if no homeworld has capacity — no silent generation
- [ ] The GM can explicitly generate a new homeworld from the generate page when capacity is exhausted; this is the only way to extend capacity
- [ ] The GM can continue assigning empires to new players after the game is `active`, up to the limit of available homeworld capacity
- [ ] A player member cannot be assigned more than one empire per game
- [ ] A deactivated member's empire persists; the member cannot be assigned a new empire in the same game
- [ ] Player members without an empire are indicated on the Members tab with a link to the generate page
- [ ] Cluster generation is deterministic: the same seed always produces the same cluster (proven by tests with a fixed seed)
- [ ] All controller actions are covered by feature tests; `ClusterGenerator` and `EmpireCreator` are covered by unit/feature tests
- [ ] No generation actions are available once the game is `active` (enforced at the controller level, not just the UI)
