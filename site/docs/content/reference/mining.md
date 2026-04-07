---
title: Mining
---

# Mining

Mine groups, deposit assignment, and natural resource extraction for colonies.

Only surface colonies can mine. Ships and orbiting colonies cannot.

---

## Mine Groups

A mine group is a set of MIN units assigned to a single deposit. A group can contain mine units of **different tech levels** (e.g., 200 MIN-1 and 50 MIN-2).

There is a **1:1 relationship** between a mine group and a deposit. Each deposit may have at most one mine group assigned to it. A mining change order assigns mine units to a deposit.

Each mine group has:

| Field | Description |
|---|---|
| `group_number` | Identifies the group within the colony |
| `deposit` | The deposit being mined |
| `units` | The MIN units in the group, each with tech level and quantity |

---

## Output

Mines produce natural resources (FUEL, GOLD, METS, NMTS) based on the deposit type. Output is **quarterly** — mines produce every turn, not once per year.

Each mine unit produces one-quarter of its annual output per turn:

| Tech Level | Annual Output (MU) | Output per Turn (MU) | Fuel per Turn |
|---|---|---|---|
| TL-1 | 100 | 25 | 0.5 |
| TL-2 | 200 | 50 | 1.0 |
| TL-3 | 300 | 75 | 1.5 |
| TL-4 | 400 | 100 | 2.0 |
| TL-5 | 500 | 125 | 2.5 |
| TL-6 | 600 | 150 | 3.0 |
| TL-7 | 700 | 175 | 3.5 |
| TL-8 | 800 | 200 | 4.0 |
| TL-9 | 900 | 225 | 4.5 |
| TL-10 | 1,000 | 250 | 5.0 |

Mines begin producing the turn after assembly. There is no startup delay or pipeline.

---

## Input Requirements

### Labor

Each mine unit requires:
- 3 unskilled worker units (or equivalent automation units)
- 1 professional unit

This is a flat ratio regardless of group size.

### Fuel

Fuel is allocated to mines and farms **before** factories. Mines on orbiting colonies within the fifth orbit use solar power and require no fuel.

---

## Shortages

If a mine group lacks sufficient inputs (fuel or labor), output is **reduced proportionally** for that turn. There is no reset penalty — production resumes at full capacity as soon as inputs are restored.

This is simpler than farms (which reset to 0% on shortage) and factories (which stall the pipeline).

---

## Deposit Location

Deposits are located by sensor probes and may be found on:
- Surfaces of terrestrial planets
- Asteroids
- Moons of gas giants

---

## Template Defaults

At game start, mine groups in a template are assigned to deposits and produce starting on the first turn.
