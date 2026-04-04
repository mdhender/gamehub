---
title: Quick Glossary
---

# Quick Glossary

## Assembly Required

A unit-type property indicating that the unit must be assembled before it can function. This is the preferred developer-facing term in Gamehub documentation.

Examples include space drives, sensors, factories, farms, mines, structure, and missile launchers.

## Assembled

The current state of a unit quantity when it is ready to function.

For units with `assembly_required = true`, assembled is the usable state. For units with `assembly_required = false`, assembled is effectively always true.

## Cargo Inventory

Units, resources, and population being stored, stockpiled, or transported by a ship or colony rather than constituting the operating ship or colony itself.

## Installed Inventory

Units that are part of the operating ship or colony itself.

Examples include a ship's structure, drives, sensors, life support, or a colony's working factories, farms, and mines.

## Non-Assembly Item

A unit or resource that does not require assembly before use.

Examples include consumer goods, food, fuel, gold, metals, non-metals, military supplies, and missiles.

## Operational Unit

A term from the 1978 user manual for what Gamehub documentation now calls an `assembly_required` unit.

We keep the older phrase when quoting historical material, but in current docs we prefer `assembly_required` because it is more explicit about the rule being modeled.

## SC

Short for ship or colony.

An SC is the game entity that owns inventory. Units do not exist independently outside an SC inventory.

## Stored Non-Assembly

A non-assembly item held in inventory. This is distinct from stored unassembled units because no later assembly step is required before use.

## Unassembled

The current state of a unit quantity that has `assembly_required = true` but is not presently assembled.

Unassembled units are in storage and require assembly labor before they can function.
