---
title: Orders
weight: 10
---

All orders available to players, grouped by category. See [Commands](/docs/players/reference/commands/) for full syntax and examples.

## Production

| Order | Syntax | Description |
|---|---|---|
| Build Change | `build change <id> <group> <target>` | Redirect a factory group to a new product |
| Mining Change | `mining change <id> <group> <deposit>` | Reassign a mining group to a different deposit |
| Transfer | `transfer <src> <dest> <unit> <qty>` | Move units between ships/colonies at the same location |
| Assemble | `assemble <id> <unit> <qty>` | Convert disassembled units into an assembled group |
| Assemble Factory | `assemble <id> factory <unit> <qty> <target>` | Assemble factories and set their build target |
| Assemble Mine | `assemble <id> mine <unit> <qty> <deposit>` | Assemble mines and assign them to a deposit |

## Movement

| Order | Syntax | Description |
|---|---|---|
| Move (in-system) | `move <id> orbit <orbit>` | Move a ship to a different orbit |
| Move (jump) | `move <id> system <x-y-z>` | Jump a ship to another star system |

## Administration

| Order | Syntax | Description |
|---|---|---|
| Draft | `draft <id> <kind> <qty>` | Convert population into specialists |
| Pay | `pay <id> <kind> <rate>` | Set wage rate for a population kind |
| Ration | `ration <id> <percent>` | Set food ration level at a colony |
| Name | `name ship\|colony\|planet <id> <name>` | Assign a name to a ship, colony, or planet |
