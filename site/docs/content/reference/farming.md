---
title: Farming
---

# Farming

Farm groups, harvest cycles, and food production for colonies.

Ships cannot have farm groups. Only colonies may produce food.

---

## Farm Groups

A farm group is a set of FRM units that produce FOOD. A group can contain farm units of **different tech levels** (e.g., 100 FRM-1 and 50 FRM-2).

Each farm group tracks its units in **quarter buckets** representing progress toward the next harvest.

| Field | Description |
|---|---|
| `group_number` | Identifies the group within the colony |
| `units` | The FRM units in the group, each with tech level and quantity |
| `quarter buckets` | Four stages (q1–q4) tracking farm units progressing toward harvest |

---

## Harvest Cycle

Farm units produce FOOD **once per year**. Units progress through a five-stage cycle, with the first harvest occurring four turns after assembly:

```
0% → 25% → 50% → 75% → 100% (harvest, then back to 0%)
```

Each turn, during phase 1 (production):
1. Units at 100% **harvest** — FOOD is produced and delivered. Units return to 0%.
2. 75% advances to 100%.
3. 50% advances to 75%.
4. 25% advances to 50%.
5. 0% advances to 25%.

Newly assembled units enter at 0% (during phase 9, assembly).

Unlike factory WIP, the stage buckets track **the farm units themselves**, not the output product. FOOD appears only at harvest (100%).

---

## Food Output

Annual food production per farm unit depends on tech level:

| Tech Level | Annual Output (FOOD) | Fuel per Turn |
|---|---|---|
| TL-1 | 100 | 0.5 |
| TL-2 | 40 | 1.0 |
| TL-3 | 60 | 1.5 |
| TL-4 | 80 | 2.0 |
| TL-5 | 100 | 2.5 |
| TL-6 | 120 | 6.0 |
| TL-7 | 140 | 7.0 |
| TL-8 | 160 | 8.0 |
| TL-9 | 180 | 9.0 |
| TL-10 | 200 | 10.0 |

TL-1 farms are standard open-colony farms. TL-2 through TL-5 are hydroponic farms. TL-6 through TL-10 are hydroponic farms using artificial sunlight (higher fuel cost).

The harvest delivers the full annual output at once, not spread across turns.

---

## Farm Types and Placement

| Tech Level | Type | Placement |
|---|---|---|
| TL-1 | Open-colony farm | Surface colonies on habitable planets only. Max units = habitability x 100,000. |
| TL-2 through TL-5 | Hydroponic (solar) | Orbiting colonies or surface colonies within orbits 1–5. |
| TL-6 through TL-10 | Hydroponic (artificial light) | Ships, orbiting colonies, or colonies beyond orbit 5. |

Farms on orbiting colonies within the fifth orbit use solar power and require no fuel.

---

## Input Requirements

### Labor

Each farm unit requires:
- 3 unskilled worker units (or equivalent automation units)
- 1 professional unit

This is a flat ratio regardless of group size (unlike factories, which scale with group size).

### Fuel

Fuel is allocated to mines and farms **before** factories. See the output table above for per-unit fuel costs.

---

## Harvest Failure

If a farm group lacks sufficient inputs (fuel or labor) when a quarter's units are due to advance, the harvest **fails**. Failed units are reset to 0%, restarting the full one-year cycle.

If inputs remain insufficient the following quarter, the clock resets again. Units continue resetting until inputs are restored.

A farm group that experiences a shortage can end up with units scattered across different quarter buckets, each on its own harvest schedule.

---

## Template Defaults

At game start, all farm units in a template are placed at 0%. The first harvest occurs four turns into the game.
