---
title: Terminology
---

# Terminology

## Assembly-Required Terminology

Gamehub prefers `assembly_required` over the 1978 manual's phrase "operational unit".

Use these terms in developer-facing docs:

- `assembly_required`: the unit type requires assembly before it can function
- `assembled`: the unit is in its usable state
- `unassembled`: the unit is in storage and requires assembly before it can function
- `stored non-assembly`: the unit is stored, but does not participate in assembly logic

Historical note:

- `operational unit` is retained only when quoting or paraphrasing the 1978 manual

## Inventory Role Terminology

Use these terms when distinguishing what an SC is doing with the units it holds:

- `installed inventory`: units that are part of the operating ship or colony itself
- `cargo inventory`: units, resources, or population being stored, stockpiled, or transported by that ship or colony

These are separate from assembly state. A unit can be cargo and unassembled, or installed and assembled.

## Quantity Field Terminology

In the current quantity-based inventory model:

- `quantity_assembled` means usable assembled units
- `quantity_disassembled` means stored, unassembled units that require assembly before use

This mapping follows the manual's distinction between:

- `Assembled Items`
- `Storage/Unassembled Items`
- `Storage/Non-Assembly Items`
