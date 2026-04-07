# Generator Reference

This document describes the game generation workflow: what runs, in what order, what consumes the PRNG, and how state is carried between steps.

Generation is distinct from game creation and member management. A game can have members without ever being generated. Generation produces the physical universe (the cluster) and the starting empires for each player. It is a multi-step process with GM review between each step. Once the GM sets the game to `active`, completed steps are locked — but new home systems and empires can still be added.

---

## Lifecycle Overview

```
[Game created] → setup
                   │
                   ├─ GM configures templates
                   │
                   └─ GM generates stars (PRNG)
                              │
                     stars_generated
                              │
                   └─ GM generates planets (PRNG)
                              │
                     planets_generated
                              │
                   └─ GM generates deposits (PRNG)
                              │
                     deposits_generated
                              │
                   └─ GM creates home system(s) (PRNG if random, not if manual)
                              │
                     home_system_generated
                              │
                   └─ GM sets game active (available after first home system)
                              │
                           active
                              │
                   └─ GM assigns empires to players (any order)
                   └─ GM may add more home systems as needed
```

### Status transitions

| Status                  | Meaning                                                                                             |
|-------------------------|-----------------------------------------------------------------------------------------------------|
| `setup`                 | Initial state. Templates can be edited. No cluster data exists.                                     |
| `stars_generated`       | 100 stars placed in the coordinate cube. GM may review, edit star data, or delete this step.        |
| `planets_generated`     | Planets generated for all stars. GM may review, edit planet data, or delete this step.              |
| `deposits_generated`    | Deposits generated for all planets. GM may review or delete this step.                              |
| `home_system_generated` | At least one home system has been created. GM may add more or delete all home systems.              |
| `active`                | Game is live. GM can assign empires and add home systems, but cannot modify or delete cluster data. |

Transitions are forward, except that the GM can **delete** a step, which cascades backward:

- Deleting deposits → status reverts to `planets_generated`
- Deleting planets → also deletes deposits, home systems, empires, colonies → status reverts to `stars_generated`
- Deleting stars → deletes everything → status reverts to `setup` (PRNG resets to initial seed)

Deleting a step **always prompts for confirmation** because it is destructive.

Once the game is `active`, no steps can be deleted and no cluster data can be modified.

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

Used to resume generation from the output state of a prior step.

### Saving state

```php
$game->update(['prng_state' => $rng->saveState()]);
```

State is serialized PHP — store it in the `prng_state` text column on `games`.

### Seed vs. state

| Column       | Purpose                                                                                                                                                           |
|--------------|-------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| `prng_seed`  | The human-readable master seed. Set at game creation. Displayed in the UI. Used to initialize the star generation step.                                           |
| `prng_state` | The serialized engine state after the most recent generation step. Updated as each step runs. Chains into subsequent steps and eventually into turn adjudication. |

The GM can override the seed before running star generation without changing `prng_seed`. If she does, the entire PRNG chain descends from the override seed. The stored `prng_seed` remains as-is (it is a label, not a mechanical input after the first step runs).

### State display

The serialized PRNG state is stored in machine-ready format. When displayed to the GM (in logs or reports), format it as a hexadecimal string or a set of unsigned integers. This is a display concern, not a storage concern.

---

## Generation Steps

Each step consumes the PRNG (chaining from the prior step's output state), produces entities, and records its work. The GM reviews the output before proceeding to the next step.

### Step workflow table

| # | Step                 | PRNG input                               | Produces                                       | Editable by GM after?             |
|---|----------------------|------------------------------------------|------------------------------------------------|-----------------------------------|
| 1 | Star generation      | `prng_seed` (or GM override)             | 100 stars in systems                           | Yes, until planets are generated  |
| 2 | Planet generation    | Output state from step 1                 | Planets for all stars (up to 11 orbits each)   | Yes, until deposits are generated |
| 3 | Deposit generation   | Output state from step 2                 | Deposits for all planets (up to 40 per planet) | No (delete and re-run to change)  |
| 4 | Home system creation | Output state from prior step (if random) | Home system entities                           | Delete and re-create to change    |

### Step records

Each generation step writes a record to the `generation_steps` table when it runs:

| Column         | Description                                           |
|----------------|-------------------------------------------------------|
| `game_id`      | The game this step belongs to                         |
| `step`         | `stars`, `planets`, `deposits`, or `home_system`      |
| `sequence`     | Ordering key (monotonically increasing within a game) |
| `input_state`  | The serialized PRNG state used as input to this step  |
| `output_state` | The serialized PRNG state after the step completed    |
| `created_at`   | When the step ran                                     |

When the GM deletes a step, its record and all downstream records are deleted (these are not append-only audit logs — they track the current state of the generation pipeline). The game's `prng_state` is restored to the deleted step's `input_state`.

**Home system steps**: Each home system creation (when random) writes its own step record. The first home system step's input is the deposit step's output. The second home system step's input is the first home system step's output. And so on. If all home systems are deleted, the PRNG state reverts to the deposit step's output.

**Manual home system assignment**: When the GM manually picks a star for a home system, no PRNG is consumed and no step record is written. The home system entity is created directly.

---

## What Consumes the PRNG

Four categories of events consume randomness:

### 1. Star generation

Places 100 stars within the 31×31×31 coordinate cube. Stars at the same coordinates form a system group, each with a unique `sequence` number within that group.

### 2. Planet generation

Creates planets for each star (up to 11 orbits). Determines planet type (terrestrial, asteroid, gas giant), habitability, and orbital position.

### 3. Deposit generation

Creates natural resource deposits on each planet (up to 40 per planet). Determines resource type, yield percentage, and quantity.

### 4. Random home system selection

When the GM asks the system to randomly select a star for a home system, the PRNG is consumed to make the selection. The selection is constrained by minimum distance from existing home systems (see below).

### What does NOT consume the PRNG

- **Manual home system assignment** — the GM picks the star directly.
- **Empire assignment** — deterministic queue logic; see below.
- **GM edits** to star/planet/deposit attributes between steps.

---

## Star Generation (`App\Services\StarGenerator`)

### Inputs

- `Game $game`
- `?string $seed` — optional override; falls back to `$game->prng_seed`

### What it produces

100 stars placed within a 31×31×31 coordinate cube. Stars sharing coordinates form a system group. Each star has a `sequence` number (1-based) unique within its system group.

| Entity | Table   | Notes                                                      |
|--------|---------|------------------------------------------------------------|
| Star   | `stars` | `game_id`, `x`, `y`, `z`, `sequence` |

The `display` string for a star's coordinates is formatted as `"XX-YY-ZZ"` (zero-padded).

### Before writing

Deletes all existing stars (and cascading: planets, deposits, home systems, empires, colonies) for the game.

### After the run

- `game->prng_state` is updated to the output state
- A `generation_steps` record is written (`step: stars`)
- `game->status` is set to `stars_generated`

---

## Planet Generation (`App\Services\PlanetGenerator`)

### Inputs

- `Game $game` — must be `stars_generated`

### What it produces

Planets for each star, using the PRNG state saved from star generation.

| Entity | Table     | Notes                                                        |
|--------|-----------|--------------------------------------------------------------|
| Planet | `planets` | `game_id`, `star_id`, `orbit` (1–11), `type`, `habitability` |

### Before writing

Deletes all existing planets (and cascading: deposits, home systems, empires, colonies) for the game.

### After the run

- `game->prng_state` is updated
- A `generation_steps` record is written (`step: planets`)
- `game->status` is set to `planets_generated`

---

## Deposit Generation (`App\Services\DepositGenerator`)

### Inputs

- `Game $game` — must be `planets_generated`

### What it produces

Deposits for each planet, using the PRNG state from planet generation.

| Entity  | Table      | Notes                                                      |
|---------|------------|------------------------------------------------------------|
| Deposit | `deposits` | `planet_id`, `resource`, `yield_pct`, `quantity_remaining` |

Up to 40 deposits per planet. Resources: Gold, Fuel, Metallics, Non-metallics.

### Before writing

Deletes all existing deposits (and cascading: home systems, empires, colonies) for the game.

### After the run

- `game->prng_state` is updated
- A `generation_steps` record is written (`step: deposits`)
- `game->status` is set to `deposits_generated`

---

## Home System Creation

An explicit GM action, available when the game is `deposits_generated` or later (including `active`). The GM can create home systems one at a time. Each home system is added to the **home system queue** in creation order.

### Two modes

#### Random selection (consumes PRNG)

The GM requests a random star. The system:

1. Picks up the current `prng_state`.
2. Selects a star that is at least N units (Euclidean distance in the coordinate cube) from every existing home system star. N is a per-game configurable value (default: 9, may be a real number like 1.41).
3. **Replaces all planetary data** on the selected star with the game's home system template. This is a kill-and-fill: all existing planets and deposits on that star are deleted and replaced with the template's planet definitions. One planet in the template has `homeworld: true`.
4. Saves the updated `prng_state`.
5. Writes a `generation_steps` record (`step: home_system`).

#### Manual assignment (does not consume PRNG)

The GM picks any star in the cluster. No distance constraint is enforced (the GM may override it). The system:

1. Replaces all planetary data on the selected star with the home system template (same kill-and-fill as random).
2. Does **not** consume the PRNG or write a step record.

If the star is in a system group (multiple stars at the same coordinates), the GM identifies it by coordinates + sequence number. Only the selected star is affected; other stars in the group are untouched.

### Constraints

- No star may be designated as a home system more than once.
- A system group may contain multiple home systems (on different stars).
- There is no constraint on the star's existing planet types or habitability — the template replaces everything.

### Home System Template

The home system template defines the complete set of planets for a home system star.

```json
{
  "planets": [
    {
      "orbit": 1,
      "type": "asteroid",
      "habitability": 0,
      "homeworld": false,
      "deposits": []
    },
    {
      "orbit": 3,
      "type": "terrestrial",
      "habitability": 25,
      "homeworld": true,
      "deposits": [
        { "resource": "gold", "yield_pct": 1, "quantity_remaining": 30000000 },
        { "resource": "fuel", "yield_pct": 9, "quantity_remaining": 99999999 },
        { "resource": "metallics", "yield_pct": 29, "quantity_remaining": 99999999 },
        { "resource": "non_metallics", "yield_pct": 29, "quantity_remaining": 99999999 }
      ]
    }
  ]
}
```

Typically 7–9 planets. Exactly one planet has `homeworld: true`. The template is stored per-game and is identical for all home systems. It is editable by the GM before the game becomes `active`.

---

## Empire Creation (`App\Services\EmpireCreator`)

### Inputs

- `Game $game` — must be `active`
- `GameUser $gameUser` — the pivot record for the player being assigned an empire

### What it produces

| Entity           | Table              | Notes                                               |
|------------------|--------------------|-----------------------------------------------------|
| Empire           | `empires`          | Linked to `game_user` (not directly to `users`)     |
| Colony           | `colonies`         | Starting colony on the homeworld planet             |
| Colony inventory | `colony_inventory` | Per-unit starting quantities from `colony_template` |

### Empire-to-home-system assignment

The GM can either:

1. **Assign to a specific home system** — the GM picks which homeworld planet the empire starts on.
2. **Use the first available** — the system assigns to the first home system in the queue (ordered by creation) that is not full.

"Full" means 25 empires are assigned to that home system's homeworld planet. 25 is a fixed game-wide constant.

**This is deterministic logic. No PRNG is consumed. No step record is written.**

### Colony template

The colony template defines the starting colony and inventory for each empire. All empires receive identical starting resources.

```json
{
  "kind": "COPN",
  "tech_level": 1,
  "sol": 1.0,
  "birth-rate-pct": 0.0625,
  "death-rate-pct": 0.0625,
  "inventory": {
    "super-structure": [
      { "unit": "STU", "quantity": 50357496 }
    ],
    "operational": [
      { "unit": "FCT-1", "quantity": 850000 }
    ],
    "cargo": [
      { "unit": "METS", "quantity": 5354167 }
    ]
  }
}
```

The template is stored per-game and is editable by the GM before the game becomes `active`.

### Guards

- A `GameUser` record can only be assigned one empire per game. Attempting to assign a second raises an exception.
- A deactivated `GameUser` record cannot be assigned a new empire. Their existing empire (if any) persists independently.
- Maximum 250 empires per game.
- The GM may reassign an empire to a different home system without creating a new empire.

---

## Templates

Templates are uploaded as JSON files and stored relationally (not as JSON columns on `games`). There is one home system template and one colony template per game. All home systems and all empires use the same template.

Templates are editable by the GM before the game becomes `active`. Once `active`, templates are locked.

The system provides a JSON upload interface for the GM to import templates. The uploaded JSON is validated and stored in the appropriate tables.

---

## Concurrency

Only one generation process may run for a given game at a time. This prevents issues from double-clicks or concurrent GM sessions.

Implementation should use a database-level lock (e.g., `SELECT ... FOR UPDATE` on the game row, or a dedicated lock column with atomic compare-and-swap) to ensure mutual exclusion. The lock is held for the duration of a single generation step, not across steps.

---

## Determinism Guarantee

Given the same `prng_seed`, the star generator will always produce identical stars. Given the same input state, each subsequent step (planets, deposits, random home system selection) will always produce identical output. This is enforced by the `Xoshiro256StarStar` engine and verified in each generator's test suite using fixed seeds.

Empire creation order does not affect PRNG state (empire assignment does not consume the PRNG). Home system creation order affects the PRNG chain only when random selection is used — manual assignments do not alter the PRNG state.

---

## GM Editing Between Steps

The GM can edit star and planet attributes between steps, subject to these rules:

| Data                            | Editable when                               | Locked when                                    |
|---------------------------------|---------------------------------------------|------------------------------------------------|
| Star coordinates and attributes | `stars_generated` (no planets exist yet)    | Planets are generated or game is `active`      |
| Planet attributes               | `planets_generated` (no deposits exist yet) | Deposits are generated or game is `active`     |
| Deposits                        | Never directly editable                     | Delete and re-run deposit generation to change |

Editing is always at the batch level — the GM cannot edit a single star's data while leaving others untouched (though the UI may present it that way). If the GM wants to change planets after deposits exist, she must delete deposits first, make edits, then re-run deposit generation.

Note: GM edits do not affect the PRNG chain. If the GM edits a planet's habitability and then re-runs deposit generation, the deposits will be different from an un-edited run **only** if the deposit generator's logic depends on planet attributes (which it may). The PRNG state input is the same either way.

---

## Deleting a Step

Deleting a step is the mechanism for "re-running" generation. The GM does not re-run a step directly — instead, she deletes the step (which removes all downstream data and restores the PRNG state), optionally edits the data from the prior step, and then runs the step again.

### Cascade rules

| Deleting     | Also deletes                                                         | Status reverts to    |
|--------------|----------------------------------------------------------------------|----------------------|
| Home systems | All home system entities, empires, colonies, colony inventory        | `deposits_generated` |
| Deposits     | Deposits, home systems, empires, colonies, colony inventory          | `planets_generated`  |
| Planets      | Planets, deposits, home systems, empires, colonies, colony inventory | `stars_generated`    |
| Stars        | Everything (full reset)                                              | `setup`              |

### PRNG state restoration

When a step is deleted, `game->prng_state` is restored to the `input_state` of the deleted step's `generation_steps` record. For star deletion, `prng_state` is cleared (the next run will initialize from `prng_seed`).

### What is NOT deleted

- The `games` record itself
- `game_user` pivot records (members are not affected by generation)
- Templates

### Confirmation

Every delete action must prompt the GM for confirmation, clearly stating what will be destroyed.
