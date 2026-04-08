# Colony Template JSON Reference

This document describes every field in the colony template JSON file used by the game importer.

A colony template file is a JSON array of template objects. Each object defines the starting configuration for one colony type.

## Top-level fields

| Field | Type | Required | Description |
|---|---|---|---|
| `kind` | string | yes | Colony type code. One of `COPN` (open surface), `CENC` (enclosed), `CORB` (orbital), `CSHP` (ship). |
| `tech-level` | integer | yes | Starting technology level of the colony. |
| `sol` | float | yes | Standard of living multiplier. |
| `birth-rate-pct` | float | yes | Birth rate as a decimal fraction (e.g., `0.0625` = 6.25%). |
| `death-rate-pct` | float | yes | Death rate as a decimal fraction. |
| `population` | array | yes | Population entries. May be empty (e.g., `CSHP`). |
| `inventory` | object | yes | Colony inventory split into four sections. |
| `production` | array or object | yes | Production groups. An empty array `[]` means no production. An object contains `factories`, `farms`, and/or `mines` keys. |

## population[]

Each entry describes one population class present in the colony.

| Field | Type | Required | Description |
|---|---|---|---|
| `population_code` | string | yes | Population class code: `UEM`, `USK`, `PRO`, `SLD`, `CNW`, `SPY`, `PLC`, `SAG`, `TRN`. |
| `quantity` | integer | yes | Number of individuals in this class. |
| `pay_rate` | float | yes | Pay rate as a decimal fraction. |

## inventory

The inventory object has four required sections. Each section is an array of `{unit, quantity}` entries.

### Sections

| Section | Description |
|---|---|
| `super-structure` | Hull or structural frame units (e.g., `STU`, `SLS`). |
| `structure` | Installed structural components (e.g., `SEN-1`, `LFS-1`, `SPD-1`). |
| `operational` | Units assigned to production or operations (e.g., `FCT-1`, `FRM-1`, `MIN-1`). |
| `cargo` | Stored goods and materials available for use or trade. |

### Inventory entry

| Field | Type | Required | Description |
|---|---|---|---|
| `unit` | string | yes | Unit code, optionally with a tech-level suffix. See [Unit code format](#unit-code-format). |
| `quantity` | integer | yes | Number of units. Must be >= 0. |

## production

The `production` field is either an empty array `[]` (no production) or an object with optional keys:

- `factories` -- factory production groups
- `farms` -- farm harvest groups
- `mines` -- mine extraction groups (optional; may be absent)

### production.factories[]

Each entry defines one factory production group. All factories in a group produce the same unit.

| Field | Type | Required | Description |
|---|---|---|---|
| `group` | integer | yes | Group number, unique within the template. |
| `orders` | string | yes | Unit code being manufactured. Must be a manufacturable unit (see [Manufacturable units](#manufacturable-units)). |
| `units` | array | yes | Factory inventory for this group. Each entry has `unit` (must match `FCT-\d+` format) and `quantity` (integer >= 0). |
| `work-in-progress` | object | yes | WIP pipeline with three quarter slots. |

#### work-in-progress

The `work-in-progress` object has three required keys representing the 3-quarter manufacturing pipeline.

| Field | Type | Required | Description |
|---|---|---|---|
| `q1` | object | yes | Quarter 1 (25% complete). Contains `unit` and `quantity`. |
| `q2` | object | yes | Quarter 2 (50% complete). Contains `unit` and `quantity`. |
| `q3` | object | yes | Quarter 3 (75% complete). Contains `unit` and `quantity`. |

Each quarter object:

| Field | Type | Required | Description |
|---|---|---|---|
| `unit` | string | yes | Unit code being produced. Must match the group's `orders` base code. |
| `quantity` | integer | yes | Number of units at this stage of completion. |

`orders` and `work-in-progress` are group-level fields. Every factory in the group contributes to the same production order and shares the same WIP pipeline.

### production.farms[]

Each entry defines one farm harvest group.

| Field | Type | Required | Description |
|---|---|---|---|
| `group` | integer | yes | Group number, unique within the template. |
| `units` | array | yes | Farm unit entries, each at a specific growth stage. |

#### Farm unit entry

| Field | Type | Required | Description |
|---|---|---|---|
| `unit` | string | yes | Unit code. Must match `FRM-\d+` format. |
| `quantity` | integer | no | Number of farm units at this stage. Defaults to `0` if omitted. |
| `stage` | integer | yes | Growth stage: `1` (0%), `2` (25%), `3` (50%), or `4` (75%). |

`stage` is a per-unit field. Farm groups do not have orders or WIP -- each unit entry tracks its own growth stage independently.

The importer fills in any missing stages (1--4) with quantity `0`, using the unit code and tech level from the first entry in the group.

### production.mines[]

Mine groups are optional and may be absent from the JSON entirely. The sample data does not include mines. When present, each entry defines one mine extraction group.

| Field | Type | Required | Description |
|---|---|---|---|
| `group` | integer | yes | Group number, unique within the template. |
| `units` | array | yes | Mine unit entries. |

#### Mine unit entry

| Field | Type | Required | Description |
|---|---|---|---|
| `unit` | string | yes | Unit code. Must match `MIN-\d+` format. |
| `quantity` | integer | yes | Number of mine units. Must be >= 0. |

Template mine groups do not include a `deposit_id`. Deposit assignment happens during empire creation, when `EmpireCreator` assigns deposits from the homeworld planet to each live mine group.

## Unit code format

Units use one of two formats:

- **Code only** -- consumable or raw material units with no tech level: `CNGD`, `FOOD`, `FUEL`, `GOLD`, `METS`, `NMTS`, `MTSP`, `RSCH`, `STU`, `SLS`.
- **Code-TL** -- tech-level units with a hyphen and integer suffix: `FCT-1`, `FRM-1`, `MIN-1`, `AUT-1`, `SEN-1`, `LFS-1`, `SPD-1`, `TPT-1`.

### Manufacturable units

Factory `orders` and WIP `unit` fields accept manufacturable units only. The following are **not** manufacturable and will fail validation:

- Raw materials: `FUEL`, `FOOD`, `GOLD`, `METS`, `NMTS`
- Population codes: `UEM`, `USK`, `PRO`, `SLD`, `CNW`, `SPY`, `PLC`, `SAG`, `TRN`

Manufacturable consumables (no tech-level suffix): `CNGD`, `MTSP`, `RSCH`, `SLS`, `STU`.

Manufacturable tech-level units (with suffix): `AUT-1`, `ESH-1`, `EWP-1`, `FCT-1`, `FRM-1`, `HEN-1`, `LAB-1`, `LFS-1`, `MIN-1`, `MSL-1`, `PWP-1`, `SEN-1`, `SPD-1`, `ANM-1`, `ASC-1`, `ASW-1`, `MSS-1`, `TPT-1`, `MTBT-1`, `RPV-1`.

## Colony kind behavior

| Kind | Code | Population | Production | Notes |
|---|---|---|---|---|
| Open Surface | `COPN` | yes | factories, farms, mines | Full colony with all production types. |
| Enclosed | `CENC` | yes | factories, farms, mines | Enclosed colony, same capabilities as COPN. |
| Orbital | `CORB` | yes | factories | Orbital bases typically have factories only. |
| Ship | `CSHP` | optional | typically none | Colony ships. `production` is usually `[]`. |
