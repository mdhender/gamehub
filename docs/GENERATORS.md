# Generator Reference

This document describes the game generation workflow: what runs, in what order, what consumes the PRNG, and how state is carried between steps.

Generation is distinct from game creation and member management. A game can have members without ever being generated. Generation produces the physical universe (the cluster) and the starting empires for each player. It is a one-way process — once the GM accepts the cluster and the game becomes `active`, generation is locked.

---

## Lifecycle Overview

```
[Game created] → setup
                   │
                   ├─ GM configures templates
                   │
                   └─ GM generates cluster
                              │
                     cluster_generated
                              │
                   ├─ GM reviews, downloads JSON
                   ├─ GM may reset and regenerate
                              │
                   └─ GM accepts cluster
                              │
                           active
                              │
                   └─ GM assigns empires to players (any order)
```

Once a game reaches `active`, neither the cluster nor the templates can be modified. The `status` column on `games` is the single source of truth for what actions are permitted.

---

## PRNG System

All randomness in generation flows through `App\Services\GameRng`, which wraps PHP's `Xoshiro256StarStar` engine.

### Initialization from a seed

```php
$rng = new GameRng($game->prng_seed);
```

The seed string is hashed with SHA-256 to produce the 256-bit engine state. Any string is a valid seed.

### Restoring from a saved state

```php
$rng = GameRng::fromState($game->prng_state);
```

Used to resume generation mid-workflow without replaying prior steps.

### Saving state

```php
$game->update(['prng_state' => $rng->saveState()]);
```

State is serialized PHP — store it in the `prng_state` text column on `games`.

### Seed vs. state

| Column | Purpose |
|---|---|
| `prng_seed` | The human-readable master seed. Set at game creation. Displayed in the UI as the starting point. |
| `prng_state` | The serialized engine state after the most recent generation step. Updated in place as steps run. |

A GM can override the seed used for a single run (e.g., try a different cluster) without permanently changing `prng_seed`. The override is passed at runtime; the stored seed is only updated if the GM explicitly saves it.

---

## What Consumes the PRNG

Only two events consume randomness:

### 1. Cluster generation

Triggered once per game when the GM generates the cluster. Consumes the PRNG for:

- Star placement (positions within the 31×31×31 cube)
- System grouping (co-located stars)
- Planet placement per orbit
- Planet attributes (type, habitability)
- Deposit generation per planet

Input: `prng_seed` (or GM override). Output state saved to `prng_state`.

### 2. Homeworld generation

An explicit GM action on the generate page, available whenever the game is `active` and all existing homeworld planets are at capacity (25 empires each). Consumes the PRNG to select and configure a new homeworld planet from the cluster. Input: current `prng_state`. Output state saved to `prng_state`.

This is never triggered automatically. Empire assignment will fail if no homeworld has capacity — the GM must run this step manually before further assignment is possible.

In most games (≤ 25 players) this step never fires at all, because the cluster step produces at least one eligible homeworld.

### Empire assignment itself is not a PRNG event

Finding a homeworld for an empire is deterministic logic: assign to the homeworld planet with the most empires that still has capacity. No randomness is involved. No log entry is written.

---

## Generation Log

Every PRNG-consuming event writes an append-only record to `generation_logs`.

| Column | Description |
|---|---|
| `game_id` | The game this event belongs to |
| `step` | `cluster` or `homeworld` |
| `input_state` | The seed string or serialized state used as input |
| `output_state` | The serialized state after the step completed |
| `subject_id` | The first system ID (cluster step) or the homeworld planet ID (homeworld step) |
| `created_at` | When the event ran |

Logs are never updated or deleted, including when the cluster is reset and regenerated. This gives the GM a full audit trail of every generation run.

---

## Cluster Generation (`App\Services\ClusterGenerator`)

### Inputs

- `Game $game`
- `?string $seed` — optional override; falls back to `$game->prng_seed`

### What it produces

A cluster is a 31×31×31 coordinate cube containing exactly 100 stars.

| Entity | Table | Notes |
|---|---|---|
| System | `systems` | Grouping of co-located stars; identified by display string e.g. `"04-12-07"` |
| Star | `stars` | One per orbit sequence within a system |
| Planet | `planets` | Up to 11 orbits per star; terrestrial planets in orbits 3–5 are homeworld candidates |
| Deposit | `deposits` | Natural resources on a planet; up to 40 per planet |

All entities carry `game_id` directly for fast scoping and bulk deletion.

### Regeneration

Before writing, the service deletes all existing cluster data for the game (systems, stars, planets, deposits) as well as any empire and colony data derived from it. This cascades cleanly via `game_id`. The `generation_logs` table is not touched.

### After the run

- `game->prng_state` is updated to the output state
- A `GenerationLog` record is written (`step: cluster`)
- `game->status` is set to `cluster_generated`

---

## Empire Creation (`App\Services\EmpireCreator`)

### Inputs

- `Game $game` — must be `active`
- `GameUser $gameUser` — the pivot record for the player being assigned an empire

### What it produces

| Entity | Table | Notes |
|---|---|---|
| Empire | `empires` | Linked to `game_user` (not directly to `users`) |
| Colony | `colonies` | Starting colony on the homeworld planet, seeded from `colony_template` |
| Colony inventory | `colony_inventory` | Per-unit starting quantities from `colony_template` |

### Homeworld assignment logic

1. Find the homeworld planet with the most assigned empires that still has capacity (< 25). This is deterministic — no PRNG.
2. If no eligible homeworld exists, throw an exception. Empire assignment cannot proceed until the GM explicitly generates a new homeworld.
3. Create the empire record with `homeworld_planet_id` pointing to the chosen planet.
4. Create the colony and inventory from `game->colony_template`.

### Homeworld generation (explicit GM action)

Run by `GameGenerationController::generateHomeworld` when the GM determines that additional homeworld capacity is needed. Never called automatically by `EmpireCreator`.

1. Pick up `game->prng_state`.
2. Select the next eligible terrestrial planet (orbit 3–5) with no homeworld designation.
3. Apply `game->homeworld_template` to it (habitability, deposits).
4. Save updated `prng_state` to the game.
5. Write a `GenerationLog` record (`step: homeworld`, `subject_id`: the planet's ID).

### Guards

- A `GameUser` record can only be assigned one empire per game. Attempting to assign a second raises an exception.
- A deactivated `GameUser` record cannot be assigned a new empire. Their existing empire (if any) persists independently.

---

## Templates

Templates are stored as JSON columns on the `games` table and define the starting state applied to each player's homeworld and colony.

### `homeworld_template`

```json
{
  "Habitability": 25,
  "Deposits": [
    { "Resource": 1, "YieldPct": 1,  "QuantityRemaining": 30000000 },
    { "Resource": 2, "YieldPct": 9,  "QuantityRemaining": 99999999 },
    { "Resource": 3, "YieldPct": 29, "QuantityRemaining": 99999999 },
    { "Resource": 4, "YieldPct": 29, "QuantityRemaining": 99999999 }
  ]
}
```

Applied to the homeworld planet (and its star) when a homeworld is generated. Resources are: 1 = Gold, 2 = Fuel, 3 = Metallics, 4 = Non-metallics.

### `colony_template`

```json
{
  "Kind": 1,
  "TechLevel": 1,
  "inventory": [
    { "unit": 1,  "TechLevel": 1, "QuantityAssembled": 1000000, "QuantityDisassembled": 0 },
    { "unit": 2,  "TechLevel": 1, "QuantityAssembled": 1000000, "QuantityDisassembled": 0 },
    ...
  ]
}
```

Applied when creating the starting colony for each empire. All players receive identical starting inventory.

Templates are editable by the GM before the cluster is accepted. Once the game is `active`, templates are locked.

---

## Determinism Guarantee

Given the same `prng_seed`, `ClusterGenerator` will always produce an identical cluster. This is enforced by the `Xoshiro256StarStar` engine and verified in the generator's test suite using a fixed seed.

Empire creation order affects which PRNG state each homeworld generation event starts from (if homeworld generation fires at all). The GM controls assignment order — there is no system-enforced sequence.
