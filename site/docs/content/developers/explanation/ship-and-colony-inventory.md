---
title: Ship And Colony Inventory
---

# Ship And Colony Inventory

This note explains why inventory in Gamehub is broader than cargo, and why ships and colonies should be treated as the only places where units exist.

## Inventory Belongs To Ships And Colonies

There are a few engine-level subtleties that are easy to miss if we read only the player manual:

- only ships and colonies have inventory
- units do not exist independently outside a ship or colony inventory
- population is conceptually part of what an SC possesses, but the engine and current model track it separately from non-population inventory

In other words, units are always attached to a specific ship or colony context. There is no free-floating unit pool elsewhere in the game state.

## Inventory Is Not The Same Thing As Cargo

Another subtlety is that inventory is not the same thing as cargo.

Ships and colonies are built out of units. Some units are not merely carried by the SC; they constitute the SC itself. For example:

- a ship hull may be represented by structural units
- a ship's propulsion may be represented by space drives
- a colony's productive capacity may be represented by assembled factories, farms, or mines

This means that an SC inventory contains more than one conceptual category of holding.

## Installed Inventory And Cargo Inventory

Gamehub uses these terms to distinguish the two main roles inventory can play:

- `installed inventory`, meaning units that are part of the ship or colony itself as an operating entity
- `cargo inventory`, meaning material, units, and population being stored, stockpiled, or transported by that ship or colony

These are separate from assembly state. A unit may be:

- installed and assembled
- cargo and assembled
- cargo and unassembled

The important point is that "inventory" should not be read as synonymous with "cargo".

## Why This Matters

This distinction matters because it changes how we reason about the same unit code in different contexts.

A space drive might appear:

- as installed inventory, where it is part of the functioning ship
- as cargo inventory, where it is a spare unit being transported or stored

The same is true for other units that may either constitute the SC or be carried by it.

This is why assembly state, inventory role, and unit type should be treated as separate concepts rather than collapsed into one broad idea of "stuff a ship has".
