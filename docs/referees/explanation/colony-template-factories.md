# Factory Production Pipeline

Factories produce manufactured goods through a 3-quarter work-in-progress (WIP) pipeline. All factories in a group share the same production order and contribute to the same pipeline.

## The WIP pipeline

Each turn, factory production advances through three quarter stages before delivery:

```
New production → q1 (25%) → q2 (50%) → q3 (75%) → delivered to Cargo
```

| Quarter | Progress | What happens |
|---|---|---|
| q1 | 25% | Units enter the pipeline from new production. |
| q2 | 50% | Units advance from q1. |
| q3 | 75% | Units advance from q2. |
| Delivery | 100% | Units from q3 are completed and placed in Cargo. |

At the start of each turn, q3 units are delivered, q2 units move to q3, q1 units move to q2, and new production enters q1. This means a factory group that starts manufacturing a unit will not deliver its first batch until four turns later (three turns in the pipeline plus the turn it enters).

## Group-level orders

`orders` and `work-in-progress` are group-level fields. Every factory in the group produces the same unit type. The group's `orders` field specifies what is being manufactured (e.g., `AUT-1`), and the WIP pipeline tracks how many units are at each stage of completion.

The `units` array in a factory group is the factory inventory -- the factories themselves (e.g., `FCT-1`), not what they produce. The number of factories determines production capacity, while `orders` determines what they build.

## WIP quantities and factory count

The quantity of units at each WIP stage reflects how many units were produced by the group's factories during the turn that batch entered the pipeline. A group with more factories produces more units per turn, resulting in larger WIP quantities at each stage.

For a game-start template, GMs are encouraged to pre-fill the WIP pipeline with reasonable quantities so that production output begins arriving immediately rather than requiring three empty turns to fill the pipeline.

## Pending orders and retooling

Factory groups support a `pending_orders` mechanism for changing what a group produces. When a group retools:

- `pending_orders_unit` and `pending_orders_tech_level` are set to the new target.
- The existing WIP pipeline continues to completion -- units already in progress are not discarded.
- Once the pipeline drains, the group switches to the new orders.

At game start, `pending_orders_unit` and `pending_orders_tech_level` are always `null`. Retooling is a mid-game action and is not part of the colony template JSON.

## Input remainders

Live colony factory groups track `input_remainder_mets` and `input_remainder_nmts`. These store fractional input materials left over after calculating production for a turn. They are initialized to `0` when a colony is created and accumulate across turns to prevent rounding losses.
