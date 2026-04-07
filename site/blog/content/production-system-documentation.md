---
title: "Production System Documentation and PR 2–4 Burndown"
date: 2026-04-06T23:59:01
---

{{< callout type="info" >}}
   Documented the full production system — factories, farms, and mines — across agent reference docs and player-facing explanations. Updated the colony template import burndown plan from a single PR into three focused PRs: factory groups (PR 2), farm groups (PR 3), and mine groups (PR 4). No code changes — documentation and planning only.
{{< /callout >}}

## Why Document Before Coding

The production system has three subsystems (factories, farms, mines) that share surface similarities but differ in fundamental ways. Factories use a multi-quarter WIP pipeline for output. Farms track units through a harvest cycle that resets on shortage. Mines just produce proportionally every turn.

Getting the schema wrong for any of these would mean a painful migration later. The original 1978 manual documents the basics but leaves gaps — what happens during retooling shortages, how farm harvest timing resets, whether mines need a pipeline at all. Those gaps had to be resolved before designing tables.

---

## Factory Groups

Factories turned out to be the most complex subsystem. Key design decisions that emerged from the documentation pass:

**Orders live on the group, not the unit.** Every unit in a factory group builds the same product, so `orders_unit` and `orders_tech_level` belong on the group row. The original burndown had them on individual units.

**WIP is a separate table.** The original plan crammed `wip_q1_unit`, `wip_q1_tech_level`, `wip_q1_quantity` (times three quarters) onto the unit row. That's 9 nullable columns. A `colony_factory_wip` table with a `quarter` column is cleaner and mirrors the pipeline concept directly.

**Retooling needs `pending_orders`.** When a player issues a build change order, the group drains its pipeline before switching. The schema tracks this with nullable `pending_orders_unit` and `pending_orders_tech_level` on the group. The turn engine checks: if `pending_orders` is set and all WIP rows are empty, swap orders and clear pending.

**Input remainders are live-colony-only.** Each turn, the factory calculates annual input (`INPUT_Y`), divides by 4, and draws from cargo — rounding up fractional resource units. The 0.75 leftover carries forward. `input_remainder_mets` and `input_remainder_nmts` live on `colony_factory_groups` but not on templates, since templates always start fresh.

---

## Farm Groups

Farms looked simple until the shortage mechanic surfaced. The pipeline tracks **farm units progressing toward harvest**, not output:

```text
0% → 25% → 50% → 75% → 100% (harvest, back to 0%)
```

Five stages, four turns per cycle, first harvest four turns after assembly. If inputs (fuel or labor) fail on any turn, affected units reset to 0% — losing all progress. A farm group that was 75% toward harvest and misses one turn of fuel is back to four turns from food.

This means the schema needs a `stage` column on farm units (0–4), not a separate WIP table. The pipeline tracks the units themselves, so the stage travels with the unit row.

The consequence of shortages fragmenting a group across stages was undocumented. After a partial shortage, some units might be at 75% while others are back at 25%. Each subset harvests on a different turn. Not a bug — just an emergent property of the reset mechanic.

---

## Mine Groups

Mines are straightforward. No pipeline, no stages, no WIP. They produce one-quarter of annual output every turn, proportionally reduced by any input shortage. No reset penalty.

The interesting schema detail: `deposit_id` is nullable on templates (the deposit isn't known until empire creation assigns a home system) but required on live colony mine groups. Each mine group maps 1:1 to a deposit.

---

## PR Split

The original burndown had a single PR 2 covering all production. That's now three PRs:

| PR | Scope | New tables | New models |
|----|-------|------------|------------|
| 2 | Factory groups | 6 (template + live: groups, units, wip) | 6 |
| 3 | Farm groups | 4 (template + live: groups, units) | 4 |
| 4 | Mine groups | 4 (template + live: groups, units) | 4 |

Each PR follows the same pattern: migration → models → factories → importer → validation → empire creator → tests. Independent and reviewable on its own.

---

## Player Documentation

Created the first two entries in the player explanation section (`players/explanation/`):

- **How Manufacturing Works** — factory groups, the 4-quarter pipeline, retooling, shortage effects, volume quirks during production
- **How Farming Works** — the 5-stage harvest cycle, the reset penalty, scattered harvests, farm types and placement
- **How Mining Works** — steady quarterly output, proportional shortage, deposit assignment

These are Diátaxis explanation docs — they build a mental model of *why* the systems work the way they do, not just the mechanics. Players reading them should be able to make informed decisions about factory sizing, retooling timing, and food stockpiling.

---

## What's Next

Implementation starts with PR 2 — factory group tables, models, and the import pipeline. The schema is designed, the burndown steps are numbered, and the sample data already contains the factory groups we'll be importing.
