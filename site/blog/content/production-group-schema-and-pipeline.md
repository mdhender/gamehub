---
title: "Production Group Schema and Pipeline"
date: 2026-04-07T17:59:00
---

{{< callout type="info" >}}
   Factory, farm, and mine groups are fully wired — schema, models, importers, validation, empire creation, and documentation. 33 commits, 6954 new lines across 75 files, 884 tests green.
{{< /callout >}}

## What Got Built

The previous blog post designed the production system on paper — three subsystems, their quirks, and a PR split plan. This session implemented all three. Each production type followed the same pipeline: migration → models → factories → importer update → validation rules → `EmpireCreator` integration → tests. Factory groups shipped first, then farm groups, then mine groups.

By the end, colony templates can define production groups in JSON, the importer stores them, validation rejects malformed ones, and `EmpireCreator` copies them onto live colonies at game start.

---

## Factory Groups (COL-13 through COL-21)

Six new tables, six new models. The schema mirrors the design from the documentation pass — `colony_template_factory_groups` and `colony_factory_groups` for the group-level data (orders, pending orders, input remainders), `_units` for individual factory units, and `_wip` for the three-quarter production pipeline.

`ImportColonyTemplates` reads factory group JSON and inserts groups, units, and WIP rows. `UploadColonyTemplateRequest` validates the nested structure — group-level fields like `orders-unit` and `orders-tech-level`, unit arrays, and optional WIP entries. `EmpireCreator` copies all three layers (groups, units, WIP) from template to live colony.

The model tests verify relationships in both directions — a factory group has many units, a unit belongs to a group, WIP belongs to a unit. 9 commits, 14 new model and test files.

---

## Farm Groups (COL-22 through COL-30)

Four tables, four models. Simpler than factories because farms have no WIP table — the `stage` column lives directly on the unit row, tracking each unit's position in the 0–4 harvest cycle.

The importer and validation follow the same pattern. `EmpireCreator` copies farm groups and units, preserving the stage value from the template (typically 0 for fresh farms).

One difference from factories: farm units don't carry orders at the group level. Each farm group has a `unit` and `tech_level` describing what it grows, but no pending-orders mechanic — farms don't retool.

---

## Mine Groups (COL-31 through COL-39)

Four tables, four models. The simplest subsystem — no pipeline, no stages. Mine groups produce proportionally every turn. The interesting piece was `deposit_id`: nullable on templates (the deposit isn't assigned until empire creation picks a home system) but required on live colonies.

`EmpireCreator` was updated to assign deposits during colony seeding. Each mine group on a template gets mapped to a deposit in the colony's system. The assignment logic matches mine groups to available deposits based on the template's deposit configuration.

---

## Validation Coverage

`UploadColonyTemplateRequest` gained validation rules for all three production types. The test file alone accounts for 624 new lines — every combination of valid structures, missing required fields, wrong types, and malformed nesting gets a dedicated test case.

The validation is strict: unknown keys under production group objects are rejected, unit arrays must be non-empty, and numeric fields like `tech_level` and `quantity` must be positive integers.

---

## Documentation

Four Hugo docs shipped to `site/docs/content/`:

- **Colony Template Reference** — the full JSON structure reference for GMs writing templates
- **How Manufacturing Works** — factory pipeline, retooling, shortage effects
- **How Farming Works** — harvest cycle, reset penalty, scattered harvests
- **How Mining Works** — steady quarterly output, proportional shortage, deposit assignment

A documentation smoke test (`ColonyTemplateDocumentationSmokeTest`) verifies all four files exist and contain expected headings. The docs were initially placed in `docs/` and moved to `site/docs/content/` in a follow-up commit to match the Hugo site structure.

---

## By the Numbers

| Metric | Value |
|--------|-------|
| Commits | 33 |
| Files changed | 75 |
| Lines added | 6,954 |
| New models | 14 |
| New tables | 14 |
| Migrations | 3 |
| Test files touched | 27 |
| Tests passing | 884 (4,523 assertions) |

---

## What's Next

The production schema is in place but the turn engine doesn't use it yet. Next up: the turn processing pipeline that actually runs factories through their WIP quarters, advances farm units through harvest stages, and calculates mine output. That's where the shortage mechanics — input remainders, stage resets, proportional reduction — go from documented rules to working code.
