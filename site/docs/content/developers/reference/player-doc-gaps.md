---
title: Player Documentation Gaps
weight: 90
---

This page tracks known gaps in player-facing documentation. Use it as a backlog when prioritizing doc work.

## Structure Issues

### Units and World reference sections need redesign for a space game

The original file structure under `docs/players/reference/` used placeholders borrowed from a fantasy-game template. The following paths were removed because they don't match the EC game model:

| Removed path | Problem |
|---|---|
| `reference/units/armies.md` | EC has no armies |
| `reference/units/characters.md` | EC has no characters |
| `reference/units/factions.md` | EC has no factions |
| `reference/units/locations.md` | Too generic; EC has colonies, ships, star systems, orbits |
| `reference/world/terrain.md` | EC has no terrain |
| `reference/world/regions.md` | EC has no regions |
| `reference/world/map.md` | Map concept needs to be reframed as the cluster/star-system model |
| `reference/world/movement-costs.md` | Needs space-appropriate framing (jump costs, fuel, etc.) |

**Replacement structure needed.** Suggested new layout (to be decided):

```
reference/
├── universe/         # or "galaxy" / "cluster"
│   ├── _index.md     # overview of the star cluster map
│   ├── star-systems.md
│   ├── planets-and-orbits.md
│   └── movement.md   # how ships navigate between systems
├── assets/           # or "units" / "forces"
│   ├── _index.md
│   ├── colonies.md
│   └── ships.md
└── glossary.md
```

## Missing Pages (by section)

### Tutorials

| Page | Notes |
|---|---|
| `tutorials/first-turn.md` | Step-by-step new player walkthrough. Source material exists: referee `set-up-a-game.md` documents the 5-step setup sequence from the operator side; mirror that from the player perspective. |
| `tutorials/writing-orders.md` | How to compose and format an order file. The order language reference and production/movement/admin command pages exist and can be linked. |
| `tutorials/reading-a-turn-report.md` | How to interpret what you receive each turn. Blocked on `reference/turn-report/` being written first. |

### How-To

| Page | Notes |
|---|---|
| `how-to/submit-orders.md` | File format, submission method, deadlines. Depends on game operator setup. |
| `how-to/interpret-errors.md` | Parser error codes, what they mean, how to fix them. The referee `validate-order-files.md` has diagnostic code table — adapt for players. |
| `how-to/plan-expansion.md` | Strategic guidance; defer until game mechanics are stable. |
| `how-to/recover-from-a-bad-turn.md` | What to do when orders parsed with errors. Defer until error-handling behavior is finalized. |

### Explanation

| Page | Notes |
|---|---|
| `explanation/turn-cycle.md` | The game loop: receive report → write orders → submit → adjudication → repeat. High priority; needed context for everything else. |
| `explanation/economics.md` | Production, resources, farms vs. mines. Defer until resource model is finalized. |
| `explanation/fog-of-war.md` | Not in v0. Defer. |
| `explanation/combat-concepts.md` | Not in v0 (combat not available). Defer. |
| `explanation/design-notes.md` | Optional; low priority. |

### Reference — Turn Report

All pages in this section were empty. Needed:

| Page | Notes |
|---|---|
| `turn-report/_index.md` | Overview of the turn report structure |
| `turn-report/sections.md` | What each section of the report contains |
| `turn-report/examples.md` | Annotated example of a real turn report |
| `turn-report/symbols-and-abbreviations.md` | Decoder for shorthand used in reports |

### Reference — Misc

| Page | Notes |
|---|---|
| `reference/glossary.md` | Game-specific terms. Can be seeded from the 1978 manual glossary and updated for v0. |

## Top-Level Reference Gaps

These live under `/reference/` (not under `/docs/players/`):

| Page | Notes |
|---|---|
| `reference/quick-command-index.md` | Alphabetical index of all commands |
| `reference/quick-glossary.md` | Short-form glossary for quick lookup |
| `reference/turn-report-cheat-sheet.md` | Single-page turn report decoder |
| `reference/tables/production-summary.md` | Production rates, costs, caps |
| `reference/tables/movement-costs.md` | Movement costs by ship type or distance |
| `reference/tables/combat-summary.md` | Not in v0; defer |

## Priority Order (suggested)

1. `explanation/turn-cycle.md` — foundational context, unblocks everything else
2. `reference/turn-report/` section — players receive a report every turn and have nothing to help them read it
3. `tutorials/first-turn.md` — highest-value onboarding page
4. `how-to/submit-orders.md` — basic operational need
5. `how-to/interpret-errors.md` — players will hit parser errors
6. `tutorials/writing-orders.md` — can link to existing commands reference
7. Redesign and write `reference/universe/` and `reference/assets/` sections
8. `reference/glossary.md`
9. Remaining tutorial and how-to pages
