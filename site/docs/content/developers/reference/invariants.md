---
title: Invariants
---

# Invariants

## Inventory State Invariants

For design and implementation discussion, use the following inventory-state invariants.

### Non-Assembly Units Are Always Assembled

If a unit has `assembly_required = false`, then it is always considered assembled.

Implications:

- non-assembly units should never appear in an unassembled or disassembled bucket
- any quantity of a non-assembly unit belongs on the assembled or usable side of inventory accounting

Examples:

- `CNGD` is always treated as assembled
- `FUEL` is always treated as assembled
- `MSS` is always treated as assembled
- `SPD` may exist in either assembled or unassembled state because it is assembly-required

### Factory Output Depends On Assembly Requirement

Units produced by factories do not all enter inventory in the same state.

- units with `assembly_required = true` are produced into storage as unassembled units
- units with `assembly_required = false` are produced into storage as non-assembly items

This follows from the manual's distinction between:

- `Storage/Unassembled Items`
- `Storage/Non-Assembly Items`
- `Assembled Items`

### Inventory Role Is Separate From Assembly State

`installed inventory` and `cargo inventory` are conceptually separate from assembly state.

Implications:

- an installed unit may be assembled and part of the functioning SC
- a cargo unit may be an unassembled spare
- inventory should not be treated as synonymous with cargo
