---
title: "Inventory Sections and Colony Template Metadata"
date: 2026-04-06T23:59:00
---

{{< callout type="info" >}}
   Colony inventory switched from a two-column assembled/disassembled model to four named sections — super-structure, structure, operational, and cargo. Colony templates now carry SOL, birth rate, and death rate. Ship templates (CSHP) are importable for the first time. 13 commits, 983 new lines across 40 files, 696 tests green.
{{< /callout >}}

## What Players Will See

Turn reports now group inventory by section rather than listing assembled and disassembled quantities. Each item shows which part of the colony it belongs to — hull structure, installed systems, active production, or cargo hold. Ship colonies (CSHP) can appear in games once GMs start using the updated templates.

None of this is visible in the UI yet — the frontend hasn't been updated to render inventory sections — but the data format changed in both JSON and CSV report exports.

---

## Why the Old Model Was Wrong

The previous schema tracked inventory with two columns: `quantity_assembled` and `quantity_disassembled`. That encoding assumed every item was either "working" or "in storage waiting for assembly." It collapsed two distinct concepts — *where* an item lives and *what state* it's in — into a single axis.

The 1978 manual actually uses three inventory buckets: Assembled Items, Storage/Unassembled Items, and Storage/Non-Assembly Items. Resources like FUEL and METS don't participate in assembly at all, so cramming them into `quantity_assembled` was always a semantic lie.

The new model replaces both columns with `inventory_section` (a string enum) and `quantity` (a single integer). The section tells you where the item sits:

| Section | Meaning | Volume rule |
|---|---|---|
| `super_structure` | Hull structure (STU, SLS) | Full volume |
| `structure` | Installed systems (sensors, drives, life support) | Full volume |
| `operational` | Active production units (factories, farms, mines) | Full volume |
| `cargo` | Stored/transported items (resources, spare parts) | Half volume |

The cargo half-volume rule is the key gameplay distinction — it's why you can carry more cargo than installed equipment in the same hull space.

---

## New Enum: `InventorySection`

A string-backed enum with four cases:

```php
enum InventorySection: string
{
    case SuperStructure = 'super_structure';
    case Structure = 'structure';
    case Operational = 'operational';
    case Cargo = 'cargo';
}
```

Three models cast to it: `ColonyTemplateItem`, `ColonyInventory`, and `TurnReportColonyInventory`. The cast means Eloquent hydrates the raw string into the enum automatically — no manual mapping in application code.

---

## Colony Template Metadata

Colony templates gained three float columns: `sol`, `birth_rate`, and `death_rate`. The `Colony` model already had these fields, but they were never populated from templates — `EmpireCreator` just left them at zero. Now the importer reads `sol`, `birth-rate-pct`, and `death-rate-pct` from the JSON and stores them on the template, and `EmpireCreator` copies them through when seeding colonies.

The practical effect: GMs can set starting SOL and demographic rates per colony type in the template JSON instead of hand-editing every colony after creation.

---

## CSHP (Ship) Templates

`ColonyKind::Ship` (`CSHP`) existed in the enum but was never used in templates. The updated `colony-template.json` includes a CSHP entry with empty population, hull structure (SLS), installed drives and sensors, and cargo fuel.

Two pieces of code needed to handle the empty population case:

- `ImportColonyTemplates` now skips the population insert when the array is empty
- `EmpireCreator` skips `ColonyPopulation::insert` for templates with zero population records

The validation layer (`UploadColonyTemplateRequest`) was updated to accept `"population": []` — previously it required at least one population entry.

---

## Migration Strategy

Two migrations handle the schema changes:

1. **Metadata migration** — adds `sol`, `birth_rate`, `death_rate` (all `real`, default `0.0`) to `colony_templates`
2. **Inventory restructure** — touches all three inventory tables (`colony_template_items`, `colony_inventory`, `turn_report_colony_inventory`): adds `inventory_section` and `quantity`, backfills `quantity = quantity_assembled + quantity_disassembled`, drops the old columns

The backfill sets `inventory_section` to `'operational'` as a safe default for existing rows. That's technically wrong for resources that should be cargo, but no production data exists yet — only test fixtures and sample data, which were updated separately.

---

## Validation Overhaul

`UploadColonyTemplateRequest` was rewritten to validate the four-section inventory format. The old rules accepted `inventory.operational` and `inventory.stored` as flat arrays. The new rules accept `inventory.super-structure`, `inventory.structure`, `inventory.operational`, and `inventory.cargo` — each optional, but at least one item required across all four combined.

Unknown keys under `inventory` now fail validation. A template with `inventory.weapons` gets rejected. The metadata fields `sol`, `birth-rate-pct`, and `death-rate-pct` are required numerics.

---

## Report Pipeline

`SetupReportGenerator` copies live `ColonyInventory` records into `TurnReportColonyInventory` snapshots. It was mapping `quantity_assembled` and `quantity_disassembled` — now it maps `quantity` and `inventory_section`.

`TurnReportJsonExporter` was emitting the old column names in JSON output. It now emits `inventory_section` (as the enum's string value) and `quantity`. The sample report files in `sample-data/beta/5/` were updated to match.

---

## Tests

The burndown touched 14 test files. Every model test, factory, and integration test that referenced `quantity_assembled` or `quantity_disassembled` was updated. New test coverage was added for:

- CSHP template import with empty population
- Inventory section values round-tripping through import, empire creation, and report generation
- Metadata fields (`sol`, `birth_rate`, `death_rate`) flowing from template JSON through to colony records
- Validation rejection of unknown inventory keys and missing metadata
- Template validation passing for all three kinds (COPN, CORB, CSHP)

696 tests, 4154 assertions, all green.

---

## What's Next

The schema and import pipeline now support the full four-section inventory model. The frontend report views haven't been updated to render `inventory_section` — that's next, along with wiring inventory sections into the order parser so assembly and transfer orders know which section they're operating on.
