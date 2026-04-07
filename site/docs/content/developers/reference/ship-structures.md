---
title: "Ship and Colony Structures"
---

# Ship and Colony Structures

Mass, structural requirements, and enclosed-volume calculations for ships and colonies.

"Super-structure" is the structural units that comprise the hull or structure of the ship/colony.

---

## Mass and Volume Units

The original manual used "mass" as a unit of weight and unit of volume. That's confusing, so EC uses only volume.

Mass is measured in **mass units** (MU). Mass units are used in hyper-engine and space drive calcuations.

Volume is measured in **volume units** (VU). Volume units reflect the amount of space units take up.

### Unit Mass and Volume Table

| Unit Code | Description       | Mass per Unit    | Volume per Unit  |
|-----------|-------------------|------------------|------------------|
| ANM       | Anti-Missiles     | 4 × TL           | 4 × TL           |
| ASC       | Assault Craft     | 5 × TL           | 5 × TL           |
| ASW       | Assault Weapons   | 20               | 20               |
| AUT       | Automation        | 4 × TL           | 4 × TL           |
| CNGD      | Consumer Goods    | 0.6              | 0.6              |
| ESH       | Energy Shields    | 50 × TL          | 50 × TL          |
| EWP       | Energy Weapons    | 10 × TL          | 10 × TL          |
| FCT       | Factories         | 12 + (2 × TL)    | 12 + (2 × TL)    |
| FOOD      | Food              | 6                | 6                |
| FRM       | Farms             | 6 + TL           | 6 + TL           |
| FUEL      | Fuel              | 1                | 1                |
| GOLD      | Gold              | 1                | 1                |
| HEN       | Hyper Engines     | 45 × TL          | 45 × TL          |
| LFS       | Life Support      | 8 × TL           | 8 × TL           |
| METS      | Metals            | 1                | 1                |
| MIN       | Mines             | 10 + (2 × TL)    | 10 + (2 × TL)    |
| MSL       | Missile Launchers | 25 × TL          | 25 × TL          |
| MSS       | Missiles          | 4 × TL           | 4 × TL           |
| MTBT      | Military Robots   | (2 × TL) + 20    | (2 × TL) + 20    |
| MTSP      | Military Supplies | 0.04             | 0.04             |
| NMTS      | Non-Metals        | 1                | 1                |
| SEN       | Sensors           | 2,998 + (2 × TL) | 2,998 + (2 × TL) |
| SLS       | Light Structure   | 0.05             | 0.05             |
| SPD       | Space Drives      | 25 × TL          | 25 × TL          |
| STU       | Structure         | 0.5              | 0.5              |
| TPT       | Transports        | 4 × TL           | 4 × TL           |

Population volume is based on head count: 100 population = 1 VU. Total population includes cadre units at 2 population each (CNW, SPY). The formula is `ceil(total_population / 100)`.

Natural resource units (FUEL, GOLD, METS, NMTS) are each 1 MU and 1 VU per unit.
Mass for RSCH, RPV, and PWP is not specified in the 1978 manual.

The volume column shows the space each unit **occupies as contents**. For structural units (STU, SLS), this is distinct from the volume they **enclose** — see [Enclosed Volume Calculation](#enclosed-volume-calculation).

---
## Super-structure vs Contents

The structural units (STU, SLS) used for the super-structure of the ship/colony are **not counted** when determining the volume of the contents.
Only the contents count for volume.

The mass of these units are used and **are counted** for hyper-engine and space drive calculations.

For example, a ship with 1,000 STU units used for super-structure and 5 SPD-1 units carries 125 VU (5 × 25 × 1) of content, not 1,125 VU.

---
## Storage Volume Rule

Units in storage count as **half their normal volume** (rounded up) when computing total ship/colony volume.
This applies to all stored items regardless of assembly state.
Their **mass** does not change.

For example, 5 SPD-1 units in storage occupy 63 VU (5 × 25 × ½).

---
## Open Colony Exterior Storage

Open colonies (COPN) may store certain items outside the colony, exempt from enclosed volume.

**Natural resources** (GOLD, FUEL, METS, NMTS) can be dumped outside the colony. These units do not count toward content volume and do not require super-structure.

**Farms** can operate outside the colony if the planet has Habitability > 0. The maximum number of exterior FRM units is `100,000 × Habitability`. Exterior farms on planets in orbits 1–5 are solar powered and do not consume FUEL.

These exemptions apply only to COPN. Enclosed colonies, orbital colonies, and ships must enclose all contents.

---
## Structural Unit Requirements

Each ship/colony type has a VU Factor, which modifies the number of structural units needed to enclose the contents of the ship/colony:

| Kind            | Code | VU Factor |
|-----------------|------|-----------|
| Open Colony     | COPN | 1         |
| Enclosed Colony | CENC | 5         |
| Orbital Colony  | CORB | 10        |
| Ship            | CSHP | 10        |

For example, an open air colony wanting to enclose 100 VU would require 100 STU units (100 × 1) while a ship would require 1,000 STU units to enclose 100 VU (100 × 10).

Light structural units (SLS) may substitute for regular structural units without restriction.

---

## Enclosed Volume Calculation

**Enclosed volume** is the total housing capacity provided by all operational structural units.

```
enclosed_volume = (qty_stu + qty_sls) / vu_factor
```

Round results down.

For example, an open air colony using 100 STU units for "super-structure" would enclose 100 VU (100 / 1) while a ship using 100 STU units for "hull" would enclose 10 VU (100 / 10).

Enclosed volume is sometimes called *carrying capacity*.

---

## Content Volume Calculation

Content volume is the total volume the super-structure must support.
It includes all operational and stored items that are not part of the super-structure, plus population.

```
content_volume = operational_volume + stored_volume
```

Where:
- **operational_volume** = sum of (quantity × volume_per_unit) for all operational non-super-structure units
- **stored_volume** = sum of (quantity × volume_per_unit × 0.5) for all stored items

Population volume is counted in operational volume. The volume is `ceil(total_population / 100)` where total population counts each base unit as 1 and each cadre unit (CNW, SPY) as 2.

---

## Required Structural Units

```
required_stu = content_volume × volume_factor
```

Where `volume_factor` is from the structural unit requirement table above.

---

## Space Available

The difference between enclosed volume and content volume tells the ship/colony how much room remains:

```
space_available = enclosed_volume - content_volume
```

A negative value means the ship/colony is over capacity.

---

## Enclosure Report

The enclosure section of the turn report summarizes the contents (inventory) of the ship/colony.
The inventory is grouped into sections for convenience:

| Section             | Meaning                                                           | Assembled Status                                  | Storage Status     |
|---------------------|-------------------------------------------------------------------|---------------------------------------------------|--------------------|
| Super-structure     | Structural units that define the size of the ship/colony.         | Always assembled.                                 | N/A - full volume. |
| Structure           | Units that are part of the ship/colony systems.                   | If they must be assembled before using, they are. | N/A - full volume. |
| Crew and Passengers | Summary of the population of the ship/colony.                     | N/A                                               | N/A - full volume. |                     
| Operational         | Units that are available for immediate use.                       | If they must be assembled before using, they are. | N/A - full volume. |
| Cargo               | Units that are "boxed up" to reduce space required to store them. | If they can be un-assembled, they are.            | Uses ½ the volume. |


For example, the report for an orbital colony might contain:

```
Inventory
  Super-structure (VU Factor: 10)
    Units_  Quantity___  Volume_____  Mass________  Enclosed Capacity
    STU       1,080,000      540,000       540,000            108,000
    Total             -      540,000       540,000            108,000
  Structure
    Units_  Quantity___  Volume_____  Mass________  Volume Used______
    LFS-1         3,720       29,760        29,760             29,760
    SEN-1             1        3,000         3,000              3,000
    Total             -       32,760        32,760             32,760
  Crew and Passengers
    Units_  Quantity___  Volume_____  Mass________  Volume Used______
    UEM               0            -             -                  -
    USK             100            -             -                  -
    PRO           3,110            -             -                  -
    SLD             510            -             -                  -
    Total         3,720           38            38                 38
  Operational
    Units_  Quantity___  Volume_____  Mass________  Volume Used______
    AUT-1         9,000       36,000        36,000             36,000
    FCT-1         1,000       14,000        14,000             14,000
    Total             -       50,000        50,000             50,000
  Cargo
    Units_  Quantity___  Volume_____  Mass________  Volume Used______
    CNGD          1,307          784           784                392
    FOOD            930        5,580         5,580              2,790
    FUEL          4,221        4,221         4,221              2,111
    METS          4,000        4,000         4,000              2,000
    NMTS         16,000       16,000        16,000              8,000
    Total             -       30,585        30,585             15,293
  Summary
    Total Mass___  Enclosed Capacity  Volume Used__  Remaining Volume
          653,383            108,000         98,091             9,909
```

- **VU Factor**: The volume unit factor for the type of ship/colony
- **Units**: The code for the unit (usually includes the tech-level, too)
- **Quantity**: quantity of units
- **Volume**: total volume of the units
- **Mass**: total mass of the units
- **Enclosed Volume**: total housing capacity provided by structural units
- **Volume Used**: actual volume the units require (units in "storage" use ½ their normal volume)

---

## Worked Example

An orbital colony (CORB, VU Factor = 10) with:
- 1,080,000 STU (volume each = 0.5) → 540,000 VU
- 1 SEN-1 (volume each = 3,000) → 3,000 VU
- 3,720 total population → ceil(3,7200 / 100) → 38 VU
- 1,000 FCT-1 operational (volume each = 12 + 2×1 = 14) → 14,000 VU
- 1,307 CNGD (volume each = 0.5) → 392 VU

The total content volume determines the number of structural units needed:

```
required_stu = content_volume × 10
```

Space available = enclosed_volume − content_volume. A positive value means the colony can accept more cargo or population.
