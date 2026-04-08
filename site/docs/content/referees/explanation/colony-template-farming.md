---
title: Farm Stages and Staggering
---

# Farm Stages and Staggering

Farms produce FOOD through a stage-based harvest cycle. Unlike factories, farms have no orders or work-in-progress pipeline. Each farm unit tracks its own growth stage independently.

## The harvest cycle

Farm units advance through five stages each turn:

| Stage | Progress | Next stage |
|---|---|---|
| 1 | 0% | 2 (25%) |
| 2 | 25% | 3 (50%) |
| 3 | 50% | 4 (75%) |
| 4 | 75% | 100% (harvest) |
| -- | 100% | 1 (0%) |

At the start of each turn, every farm unit advances one stage. Units at stage 4 (75%) reach 100%, deliver FOOD to Cargo, and immediately reset to stage 1 (0%). The 100% state is transient -- it exists only during the harvest step and is never stored.

After the harvest step, all farm units are at stages 1 through 4. FOOD output is calculated at harvest time from the unit count and tech level -- it is not accumulated as work-in-progress.

## Reset on input shortage

If a farm unit cannot advance because inputs (fuel, labor) are insufficient, it resets to stage 1. All accumulated progress is lost. This is harsher than mines, which simply reduce output proportionally on shortage. A farm shortage means the colony loses turns of growth, not just a fraction of one turn's output.

## Why staggering matters

If all farm units start at the same stage, the colony receives no FOOD for the first three turns, then gets a massive harvest on turn four. This feast-or-famine pattern is almost always wrong for a game-start template.

Staggering distributes farm units across stages 1 through 4 so that one batch harvests every turn, providing a steady FOOD supply from turn one.

## How to stagger

Divide the total farm unit count evenly across four entries, one per stage. For example, 130,000 FRM-1 split into four groups:

```json
"farms": [
  {
    "group": 1,
    "units": [
      { "unit": "FRM-1", "quantity": 32500, "stage": 1 },
      { "unit": "FRM-1", "quantity": 32500, "stage": 2 },
      { "unit": "FRM-1", "quantity": 32500, "stage": 3 },
      { "unit": "FRM-1", "quantity": 32500, "stage": 4 }
    ]
  }
]
```

Each turn, the 32,500 units at stage 4 harvest and reset, while the others advance. The colony receives FOOD from 32,500 farms every turn instead of 130,000 farms every fourth turn.

The importer backfills any missing stages with quantity 0, so a GM who only specifies stages 1 and 4 will still get entries for stages 2 and 3 (with zero units). However, GMs are encouraged to explicitly stagger for sensible game-start conditions.
