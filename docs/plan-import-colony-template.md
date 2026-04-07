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

## PR 2 — Factory Groups

### 1. New Tables

#### `colony_template_factory_groups`
- `id`, `colony_template_id` (FK cascade)
- `group_number` integer
- `orders_unit` string — unit type being produced (e.g., `CNGD`, `AUT`)
- `orders_tech_level` integer, default 0
- `pending_orders_unit` string, nullable — unit type the group is retooling to
- `pending_orders_tech_level` integer, nullable
- Unique: `(colony_template_id, group_number)`

#### `colony_template_factory_units`
- `id`, `colony_template_factory_group_id` (FK cascade)
- `unit` string (always `FCT`), `tech_level` integer, `quantity` integer

#### `colony_template_factory_wip`
- `id`, `colony_template_factory_group_id` (FK cascade)
- `quarter` integer (1, 2, or 3) — stage of completion (q1=25%, q2=50%, q3=75%)
- `unit` string, `tech_level` integer default 0, `quantity` integer
- Unique: `(colony_template_factory_group_id, quarter)`

Pipeline flow: new production → q1 → q2 → q3 → delivered to Cargo.

#### Mirror tables for live colonies
- `colony_factory_groups` — same shape, FK to `colony_id`, plus `input_remainder_mets` real default 0, `input_remainder_nmts` real default 0
- `colony_factory_units` — same shape, FK to `colony_factory_group_id`
- `colony_factory_wip` — same shape, FK to `colony_factory_group_id`

### 2. Models

- `ColonyTemplateFactoryGroup` — belongs to `ColonyTemplate`, has many `units`, has many `wip`
- `ColonyTemplateFactoryUnit` — belongs to group, casts `unit` to `UnitCode`
- `ColonyTemplateFactoryWip` — belongs to group, casts `unit` to `UnitCode`
- `ColonyFactoryGroup` — belongs to `Colony`, has many `units`, has many `wip`
- `ColonyFactoryUnit` — same structure
- `ColonyFactoryWip` — same structure

### 3. Relationships on existing models

- `ColonyTemplate` — add `factoryGroups()` hasMany
- `Colony` — add `factoryGroups()` hasMany

### 4. Factories

- `ColonyTemplateFactoryGroupFactory`
- `ColonyTemplateFactoryUnitFactory`
- `ColonyTemplateFactoryWipFactory`
- `ColonyFactoryGroupFactory`
- `ColonyFactoryUnitFactory`
- `ColonyFactoryWipFactory`

### 5. Update `ImportColonyTemplates`

- Add `createFactoryGroups()` method.
- For each entry in `production.factories`:
  - Create a `ColonyTemplateFactoryGroup` with `group_number`, `orders_unit`, `orders_tech_level`. `pending_orders` is null at game start.
  - Create `ColonyTemplateFactoryUnit` rows for each unit in the group (parse tech level from `"FCT-1"` format).
  - Create `ColonyTemplateFactoryWip` rows for q1, q2, q3 from `work-in-progress`.
- Handle CSHP where `production` is an empty array (skip gracefully).

### 6. Update `UploadColonyTemplateRequest`

- Validate optional `production.factories` array.
- Each factory group requires: `group` (integer), `units` (array of `{unit, quantity}`).
- Each factory unit: `unit` must match `FCT-\d+` format, `quantity` required integer.
- Each factory unit requires `orders` (valid unit code, with optional tech level suffix).
- Each factory unit requires `work-in-progress` with `q1`, `q2`, `q3`, each having `unit` and `quantity`.
- WIP unit must match the `orders` unit.
- Reject factories that cannot be manufactured (FUEL, GOLD, METS, NMTS, FOOD, population).

### 7. Update `EmpireCreator`

- Eager-load `factoryGroups.units` and `factoryGroups.wip` on templates.
- After creating inventory and population, loop through each template's factory groups.
- Create `ColonyFactoryGroup` for each (with `input_remainder_mets/nmts` at 0).
- Batch-insert `ColonyFactoryUnit` and `ColonyFactoryWip` rows.

### 8. Tests

#### Migration tests
- Verify table structure, columns, constraints, cascade delete for all 6 tables.

#### Model tests
- Enum casting, relationships, factory validity for all 6 new models.

#### Validation tests (in `UploadColonyTemplateValidationTest`)
- Valid factory group passes.
- Missing `orders` on factory unit fails.
- Missing `work-in-progress` or missing quarters fails.
- WIP unit mismatch with orders fails.
- Invalid unit format (non-FCT) fails.
- Orders targeting non-manufacturable units (FUEL, FOOD, etc.) fails.
- Empty `production` (CSHP) passes.
- Sample data file still passes.

#### Import tests (in `ImportColonyTemplatesTest`)
- COPN creates 7 factory groups with correct orders and WIP values.
- CORB creates 1 factory group.
- CSHP creates 0 factory groups.
- Factory units have correct tech levels and quantities.
- WIP rows have correct quarter, unit, tech_level, and quantity.
- Reimport replaces factory groups.

#### EmpireCreator tests (in `EmpireCreatorTest`)
- Factory groups, units, and WIP copied from template to live colony.
- `input_remainder_mets/nmts` initialized to 0.
- CSHP colonies have no factory groups.

---

## PR 3 — Farm Groups

### 1. New Tables

#### `colony_template_farm_groups`
- `id`, `colony_template_id` (FK cascade)
- `group_number` integer
- Unique: `(colony_template_id, group_number)`

#### `colony_template_farm_units`
- `id`, `colony_template_farm_group_id` (FK cascade)
- `unit` string (always `FRM`), `tech_level` integer, `quantity` integer
- `stage` integer, default 0 — harvest progress (0=0%, 1=25%, 2=50%, 3=75%, 4=100%)

#### Mirror tables for live colonies
- `colony_farm_groups` — same shape, FK to `colony_id`
- `colony_farm_units` — same shape, FK to `colony_farm_group_id`

Farm units track their own progress through a five-stage harvest cycle:

```
0% → 25% → 50% → 75% → 100% (harvest, then back to 0%)
```

Each turn, units advance one stage if inputs (fuel + labor) are met. At 100%, FOOD is produced and units return to 0%. On input shortage, units reset to 0% — losing all progress.

At game start, all template farm units are at stage 0.

### 2. Models

- `ColonyTemplateFarmGroup` — belongs to `ColonyTemplate`, has many `units`
- `ColonyTemplateFarmUnit` — belongs to group, casts `unit` to `UnitCode`
- `ColonyFarmGroup` — belongs to `Colony`, has many `units`
- `ColonyFarmUnit` — same structure

### 3. Relationships on existing models

- `ColonyTemplate` — add `farmGroups()` hasMany
- `Colony` — add `farmGroups()` hasMany

### 4. Factories

- `ColonyTemplateFarmGroupFactory`
- `ColonyTemplateFarmUnitFactory`
- `ColonyFarmGroupFactory`
- `ColonyFarmUnitFactory`

### 5. Update `ImportColonyTemplates`

- Add `createFarmGroups()` method.
- For each entry in `production.farms`:
  - Create a `ColonyTemplateFarmGroup` with `group_number`.
  - Create `ColonyTemplateFarmUnit` rows for each unit (parse tech level from `"FRM-1"` format). All start at `stage=0`.
- Handle CSHP where `production` is an empty array (skip gracefully).

### 6. Update `UploadColonyTemplateRequest`

- Validate optional `production.farms` array.
- Each farm group requires: `group` (integer), `units` (array of `{unit, quantity}`).
- Each farm unit: `unit` must match `FRM-\d+` format, `quantity` required integer.
- No orders, WIP, or pending orders for farms.

### 7. Update `EmpireCreator`

- Eager-load `farmGroups.units` on templates.
- Copy farm groups and units to live colony tables with `stage=0`.

### 8. Tests

#### Migration tests
- Verify table structure, columns, constraints, cascade delete for all 4 tables.

#### Model tests
- Enum casting, relationships, factory validity for all 4 new models.

#### Validation tests (in `UploadColonyTemplateValidationTest`)
- Valid farm group passes.
- Invalid unit format (non-FRM) fails.
- Empty `production` (CSHP) passes.
- Sample data file still passes.

#### Import tests (in `ImportColonyTemplatesTest`)
- COPN creates 1 farm group with 1 unit (130,000 FRM-1 at stage 0).
- CORB creates 0 farm groups.
- CSHP creates 0 farm groups.
- Reimport replaces farm groups.

#### EmpireCreator tests (in `EmpireCreatorTest`)
- Farm groups and units copied from template to live colony.
- All units at stage 0.
- CSHP colonies have no farm groups.

---

## PR 4 — Mine Groups

### 1. New Tables

#### `colony_template_mine_groups`
- `id`, `colony_template_id` (FK cascade)
- `group_number` integer
- `deposit_id` integer, nullable (FK, nullable — template may not reference a specific deposit)
- Unique: `(colony_template_id, group_number)`

#### `colony_template_mine_units`
- `id`, `colony_template_mine_group_id` (FK cascade)
- `unit` string (always `MIN`), `tech_level` integer, `quantity` integer

#### Mirror tables for live colonies
- `colony_mine_groups` — same shape, FK to `colony_id`. `deposit_id` (FK) required (not nullable) on live colonies.
- `colony_mine_units` — same shape, FK to `colony_mine_group_id`

Mines have no pipeline, no WIP, no harvest cycle. They produce one-quarter of annual output every turn that inputs (fuel + labor) are met. On shortage, output is reduced proportionally — no reset penalty.

1:1 relationship between mine group and deposit.

### 2. Models

- `ColonyTemplateMineGroup` — belongs to `ColonyTemplate`, has many `units`
- `ColonyTemplateMineUnit` — belongs to group, casts `unit` to `UnitCode`
- `ColonyMineGroup` — belongs to `Colony`, has many `units`, belongs to `Deposit`
- `ColonyMineUnit` — same structure

### 3. Relationships on existing models

- `ColonyTemplate` — add `mineGroups()` hasMany
- `Colony` — add `mineGroups()` hasMany

### 4. Factories

- `ColonyTemplateMineGroupFactory`
- `ColonyTemplateMineUnitFactory`
- `ColonyMineGroupFactory`
- `ColonyMineUnitFactory`

### 5. Update `ImportColonyTemplates`

- Add `createMineGroups()` method.
- The sample template JSON does not currently include mine groups. If `production.mines` is present, import them; otherwise skip.
- Each mine group: create `ColonyTemplateMineGroup` with `group_number`, `deposit_id` null.
- Create `ColonyTemplateMineUnit` rows for each unit (parse tech level from `"MIN-1"` format).

### 6. Update `UploadColonyTemplateRequest`

- Validate optional `production.mines` array.
- Each mine group requires: `group` (integer), `units` (array of `{unit, quantity}`).
- Each mine unit: `unit` must match `MIN-\d+` format, `quantity` required integer.
- No orders, WIP, or stages for mines.

### 7. Update `EmpireCreator`

- Eager-load `mineGroups.units` on templates.
- Copy mine groups and units to live colony tables.
- Assign `deposit_id` during empire creation (mine groups are assigned to deposits on the home system's planet).

### 8. Tests

#### Migration tests
- Verify table structure, columns, constraints, cascade delete for all 4 tables.

#### Model tests
- Enum casting, relationships, factory validity for all 4 new models.

#### Validation tests (in `UploadColonyTemplateValidationTest`)
- Valid mine group passes.
- Invalid unit format (non-MIN) fails.
- Empty `production` (CSHP) passes.

#### Import tests (in `ImportColonyTemplatesTest`)
- Templates with mine groups create correct groups and units.
- Templates without mine groups create none.
- Reimport replaces mine groups.

#### EmpireCreator tests (in `EmpireCreatorTest`)
- Mine groups and units copied from template to live colony.
- `deposit_id` assigned during creation.
- CSHP and CORB colonies have no mine groups.

---

## Files Affected (Summary)

### PR 1 (done)
- `app/Enums/InventorySection.php`
- Migration: add sol/birth_rate/death_rate to colony_templates
- Migration: add inventory_section to colony_template_items
- Migration: add inventory_section to colony_inventory

### PR 2

#### New Files
- Migration: create factory group tables (6 tables)
- `app/Models/ColonyTemplateFactoryGroup.php`
- `app/Models/ColonyTemplateFactoryUnit.php`
- `app/Models/ColonyTemplateFactoryWip.php`
- `app/Models/ColonyFactoryGroup.php`
- `app/Models/ColonyFactoryUnit.php`
- `app/Models/ColonyFactoryWip.php`
- Factories for all 6 models

#### Modified Files
- `app/Models/ColonyTemplate.php` — add `factoryGroups()` relation
- `app/Models/Colony.php` — add `factoryGroups()` relation
- `app/Actions/GameGeneration/ImportColonyTemplates.php` — add `createFactoryGroups()`
- `app/Http/Requests/UploadColonyTemplateRequest.php` — validate factory groups
- `app/Services/EmpireCreator.php` — copy factory groups to live colonies

### PR 3

#### New Files
- Migration: create farm group tables (4 tables)
- `app/Models/ColonyTemplateFarmGroup.php`
- `app/Models/ColonyTemplateFarmUnit.php`
- `app/Models/ColonyFarmGroup.php`
- `app/Models/ColonyFarmUnit.php`
- Factories for all 4 models

#### Modified Files
- `app/Models/ColonyTemplate.php` — add `farmGroups()` relation
- `app/Models/Colony.php` — add `farmGroups()` relation
- `app/Actions/GameGeneration/ImportColonyTemplates.php` — add `createFarmGroups()`
- `app/Http/Requests/UploadColonyTemplateRequest.php` — validate farm groups
- `app/Services/EmpireCreator.php` — copy farm groups to live colonies

### PR 4

#### New Files
- Migration: create mine group tables (4 tables)
- `app/Models/ColonyTemplateMineGroup.php`
- `app/Models/ColonyTemplateMineUnit.php`
- `app/Models/ColonyMineGroup.php`
- `app/Models/ColonyMineUnit.php`
- Factories for all 4 models

#### Modified Files
- `app/Models/ColonyTemplate.php` — add `mineGroups()` relation
- `app/Models/Colony.php` — add `mineGroups()` relation
- `app/Actions/GameGeneration/ImportColonyTemplates.php` — add `createMineGroups()`
- `app/Http/Requests/UploadColonyTemplateRequest.php` — validate mine groups
- `app/Services/EmpireCreator.php` — copy mine groups to live colonies
