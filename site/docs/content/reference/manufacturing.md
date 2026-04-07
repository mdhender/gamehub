---
title: Manufacturing
---

# Manufacturing

Factory groups, production pipelines, and retooling for colony manufacturing.

Ships cannot have factory groups. Only colonies may manufacture units.

---

## Factory Groups

A factory group (FG) is a set of FCT units assigned to produce a single unit type. A group can contain factory units of **different tech levels** (e.g., 50 FCT-1 and 10 FCT-2).

Factory groups can manufacture any unit **except** natural resources (FUEL, GOLD, METS, NMTS), food (FOOD), and population.

Each factory group has:

| Field | Description |
|---|---|
| `group_number` | Identifies the group within the colony |
| `units` | The FCT units in the group, each with tech level and quantity |
| `orders` | The unit type currently being produced (e.g., CNGD, AUT-1) |
| `pending_orders` | The unit type the group is retooling to produce (nullable) |
| `work-in-progress` | Three-quarter pipeline of output units (q1, q2, q3) |

---

## Production Pipeline

Production takes one year (four turns). Output flows through a three-quarter work-in-progress (WIP) pipeline, where each quarter represents a stage of completion:

| Column | Completion |
|---|---|
| q1 | 25% complete |
| q2 | 50% complete |
| q3 | 75% complete |
| Delivery | 100% complete |

```
[new production] → q1 → q2 → q3 → [delivered to Cargo]
```

Each turn:
1. q3 output is delivered to Cargo
2. q2 advances to q3
3. q1 advances to q2
4. New production enters q1

The WIP columns contain **output units only** (the product being built). Input materials (METS, NMTS, FUEL, labor) are consumed each turn to drive the pipeline but are not stored in the WIP columns.

### Pipeline Flow Constraints

Movement between WIP columns can be blocked at each stage by:

| Constraint | Effect |
|---|---|
| **Input shortage** (resources, labor, fuel) | Limits new production entering q3 and advancement between stages |
| **Output space** | If the colony is at capacity, finished goods cannot leave q1 |
| **Column capacity** | If the receiving WIP column cannot hold the full amount, movement is throttled |

### Input Calculation

Each turn, the FG calculates `INPUT_Y`, the input required for 100% annual production. The actual input consumed is `INPUT_Y / 4`, retaining any fractional remainder for the next turn. Fractional resource units are rounded up when drawn from Cargo (e.g., 0.25 METS draws 1 full unit; the 0.75 remainder carries forward). The fractional remainders for metallic and non-metallic resources are tracked on the production group itself, separate from the WIP pipeline.

### Volume Effects During Production

Inputs are drawn from Cargo, where natural resources have an effective volume of 0.5 VU per unit. When consumed by the factory, they are no longer in Cargo and revert to 1 VU per unit. This can cause a colony to temporarily exceed its enclosed capacity.

Finished output is delivered to Cargo at 0.5 VU per unit (the standard Cargo volume reduction).

---

## Retooling

A build change order causes a factory group to retool — switch from producing one unit type to another.

When a retool order is issued:
1. `pending_orders` is set to the new unit type
2. The pipeline **drains** — no new production enters q1, but existing WIP continues advancing (q1→q2, q2→q3, q3→delivered)
3. Once all WIP columns are empty, `pending_orders` replaces `orders` and new production begins

### Retooling Edge Cases

| Scenario | Behavior |
|---|---|
| **Shortage during retool** | Pipeline advancement stalls. Retooling is delayed until the shortage is resolved and WIP finishes draining. |
| **Pipeline already empty** | Retool completes immediately. No waiting period. |

---

## Input Requirements

### Labor

Factory group labor requirements scale with group size. Larger groups are more efficient.

| No. of Factory Units | Professional per FU | Unskilled per FU |
|---|---|---|
| 1 -- 4 | 6 | 18 |
| 5 -- 49 | 5 | 15 |
| 50 -- 499 | 4 | 12 |
| 500 -- 4,999 | 3 | 9 |
| 5,000 -- 49,999 | 2 | 6 |
| 50,000+ | 1 | 3 |

Unskilled worker units may be replaced by automation units.

### Resource Costs

Each factory unit can convert 20 mass units (times its tech level) of natural resources per year. Materials required per output unit:

| Unit | Metallic | Non-Metallic |
|---|---|---|
| Assault Weapon | 1 x TL | 1 x TL |
| Assault Craft | 3 x TL | 2 x TL |
| Anti-missile | 2 x TL | 2 x TL |
| Automation | 2 x TL | 2 x TL |
| Consumer Good | 0.2 | 0.4 |
| Energy Shield | 25 x TL | 25 x TL |
| Energy Weapon | 5 x TL | 5 x TL |
| Factory | 8 + TL | 4 + TL |
| Farm | 4 + TL | 2 + TL |
| Hyper Engine | 25 x TL | 20 x TL |
| Life Support | 3 x TL | 5 x TL |
| Light Structural | 0.01 | 0.04 |
| Military Robot | 10 + TL | 10 + TL |
| Military Supplies | 0.02 | 0.02 |
| Mine | 5 + TL | 5 + TL |
| Missile | 2 x TL | 2 x TL |
| Missile Launcher | 15 x TL | 10 x TL |
| Sensor | 999 + TL | 1,999 + TL |
| Space Drive | 15 x TL | 10 x TL |
| Structural | 0.1 | 0.4 |
| Transport | 3 x TL | 1 x TL |

Light structural units can only be manufactured in orbiting colonies. Orbiting colonies will not manufacture regular structural units.

### Fuel Priority

Fuel is allocated to mines and farms before factories. Fuel shortages usually affect only the factories.
