# Plan: Import Updated Colony Template

The template has been updated.

* A new kind, CSHP, is now valid.
* Inventory has been refactored into four sections.

---

## PR 1 — Metadata + CSHP + Inventory Sections

### 1. Migration: Add columns to `colony_templates`

Add to `colony_templates`:
- `sol` real, default `0.0`
- `birth_rate` real, default `0.0`
- `death_rate` real, default `0.0`

### 2. Migration: Add `inventory_section` to `colony_template_items`

- `inventory_section` string, default `'operational'`
- Values: `super_structure`, `structure`, `operational`, `cargo`

### 3. Migration: Add `inventory_section` to `colony_inventory`

- Same column as above.
- Backfill existing rows to `'operational'`.

### 4. New Enum: `InventorySection`

```php
enum InventorySection: string
{
    case SuperStructure = 'super_structure';
    case Structure = 'structure';
    case Operational = 'operational';
    case Cargo = 'cargo';
}
```

### 5. Update `ColonyKind` enum

Verify that the JSON uses `COPN`, `CORB`, or `CSHP`).

### 6. Model Updates

- **`ColonyTemplate`**: Add `sol`, `birth_rate`, `death_rate` to fillable/casts.
- **`ColonyTemplateItem`**: Add `inventory_section` to fillable, cast to `InventorySection`.
- **`ColonyInventory`**: Add `inventory_section` to fillable, cast to `InventorySection`.

### 7. Update `ImportColonyTemplates`

- Read `sol`, `birth-rate-pct`, `death-rate-pct` from JSON into template.
- Make population import optional (CSHP has none).
- Replace `createInventory()` to iterate all 4 sections (`super-structure` → `super_structure`, etc.), storing `inventory_section` on each item.
- Remove references to old `stored` key.

### 8. Update `UploadColonyTemplateRequest`

- Accept `CSHP` kind.
- Validate 4 inventory sections instead of `operational` + `stored`.
- Remove `stored` references.
- Validate `super-structure`, `structure`, `operational`, `cargo` keys; reject unknown keys.

### 9. Update `EmpireCreator`

- Copy `sol`, `birth_rate`, `death_rate` from template to `Colony`.
- Copy `inventory_section` from template items to `ColonyInventory`.
- Handle entries with an empty population slice.

### 10. Tests

- **Validation**: CSHP with inventory-only passes; new fields validated; 4 inventory sections validated.
- **Importer**: Sample JSON creates 3 templates with correct metadata, sections, and population.
- **EmpireCreator**: Metadata + sections copied to live colonies.

---

## PR 2 — Production (Farms + Factories)

### 1. New Tables

#### `colony_template_production_groups`
- `id`, `colony_template_id` (FK cascade), `type` (string: `farm`|`factory`), `group_number` (integer)
- Unique: `(colony_template_id, type, group_number)`

#### `colony_template_production_units`
- `id`, `colony_template_production_group_id` (FK cascade)
- `unit` string, `tech_level` integer default 0, `quantity` integer
- Factory-only (nullable): `orders_unit`, `orders_tech_level`, `wip_q1_unit`, `wip_q1_tech_level`, `wip_q1_quantity`, same for q2/q3

#### Mirror tables for live colonies
- `colony_production_groups` + `colony_production_units` (same shape, FK to `colony_id`)

### 2. New Enum: `ProductionGroupType`

```php
enum ProductionGroupType: string
{
    case Farm = 'farm';
    case Factory = 'factory';
}
```

### 3. Models

- `ColonyTemplateProductionGroup` + `ColonyTemplateProductionUnit`
- `ColonyProductionGroup` + `ColonyProductionUnit`

### 4. Update `ImportColonyTemplates`

- Add `createProduction()` method.
- Import farm groups as `type=farm`, factory groups as `type=factory`.
- Parse orders and WIP for factory units.

### 5. Update `UploadColonyTemplateRequest`

- Validate `production` (optional).
- Farms: group number, units with valid FRM-* format.
- Factories: group number, units with FCT-* format, required orders + WIP (q1/q2/q3).

### 6. Update `EmpireCreator`

- Eager-load `productionGroups.units`.
- Copy production groups + units to live colony tables.

### 7. Tests

- Validation: valid farm/factory passes; missing orders fails; invalid WIP fails.
- Importer: COPN creates 1 farm group + 7 factory groups; CORB creates 1 factory group.
- EmpireCreator: production groups + units copied to live colonies.

---

## Files Affected (Summary)

### New Files
- `app/Enums/InventorySection.php`
- `app/Enums/ProductionGroupType.php` (PR 2)
- Migration: add sol/birth_rate/death_rate to colony_templates
- Migration: add inventory_section to colony_template_items
- Migration: add inventory_section to colony_inventory
- Migration: create production tables (PR 2)
- `app/Models/ColonyTemplateProductionGroup.php` (PR 2)
- `app/Models/ColonyTemplateProductionUnit.php` (PR 2)
- `app/Models/ColonyProductionGroup.php` (PR 2)
- `app/Models/ColonyProductionUnit.php` (PR 2)

### Modified Files
- `sample-data/beta/colony-template.json` (done)
- `app/Enums/ColonyKind.php`
- `app/Models/ColonyTemplate.php`
- `app/Models/ColonyTemplateItem.php`
- `app/Models/ColonyInventory.php`
- `app/Models/Colony.php` (PR 2 — production relation)
- `app/Actions/GameGeneration/ImportColonyTemplates.php`
- `app/Http/Requests/UploadColonyTemplateRequest.php`
- `app/Services/EmpireCreator.php`
- Factories for updated models
