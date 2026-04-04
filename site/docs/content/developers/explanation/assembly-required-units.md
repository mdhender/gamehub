---
title: Assembly-Required Units
---

# Assembly-Required Units

This note captures what the 1978 user manual means by "operational", "assembled", and "unassembled" inventory, because those terms affect how we ingest, store, and report inventory.

For developer-facing documentation, we prefer the term `assembly_required` over "operational unit". The older phrase is still useful when quoting the manual, but `assembly_required` is clearer about the rule we are modeling.

## Core Distinction

The key rule appears in the manufacturing chapter of the 1978 manual:

- assembly orders are used to make units operational
- operational units are units that, after being disassembled and put into storage, must be assembled again before they can function

The manual also says that when those units are not operational, they are "in storage".

Taken together, that gives us this interpretation:

- `operational` means the unit is ready to function this turn
- `assembled` is the report-facing name for the same state
- `unassembled` means the unit is in storage and requires assembly before it can function

In other words, for units with `assembly_required = true`, `operational` and `assembled` describe the same usable state.

## Which Units Have `assembly_required = true`

The manual explicitly lists these as operational units. In our terminology, these are the units with `assembly_required = true`:

- space drives
- sensors
- automation units
- life support units
- energy weapons
- energy shields
- mining units
- factory units
- farms
- hyper engines
- structural units
- light structural units
- missile launchers

These units can exist in at least two materially different inventory states:

- assembled and operational
- stored and unassembled

That distinction is not cosmetic. It affects whether the unit can function and whether assembly labor is required.

## Non-Assembly Items

The sample turn report in the manual uses three inventory buckets:

- `Storage/Non-Assembly Items`
- `Storage/Unassembled Items`
- `Assembled Items`

This is an important clue. The rules do not divide inventory into just "stored" and "not stored". They distinguish:

- items that can be stored without any assembly concept
- items that are stored specifically in an unassembled state
- items that are currently assembled and usable

So "stored" is not one single semantic category. Some stored items are simply stockpile resources or other non-assembly goods, while some stored items are units with `assembly_required = true` that have been disassembled.

## Source Notes

These conclusions are based on:

- `gamehub-docs/content/history/user-manual-1978/manufacturing.md`
- `gamehub-docs/content/history/user-manual-1978/appendix-i.md`
- `gamehub-docs/content/history/user-manual-1978/sample-documents.md`

This page is intentionally narrow. It explains why the 1978 terminology is easy to misread and why Gamehub prefers `assembly_required` as the clearer concept.
