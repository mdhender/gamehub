---
title: Unit Codes
---

Every item in the game — from factories to fuel — is identified by a short unit code.
These codes appear in turn reports, order syntax, and inventory listings.
Units with tech levels use the format `CODE-TL` (e.g., `FCT-3`); consumables use the plain code (e.g., `FUEL`).

## Assembly-Required Units

The 1978 manual calls these "operational units". In Gamehub docs, we prefer `assembly_required` because it states the rule more clearly.

These units must be assembled before they can function. They often form part of the installed inventory of a ship or colony, though they may also exist as unassembled cargo.

### Production Units

| Code | Unit | Mass | Fuel / Turn | Output | Crew |
|---|---|---|---|---|---|
| `FCT` | Factories | 12 + (2 × TL) | 0.5 × TL | 20 × TL MU | 3 unskilled + 1 professional per unit |
| `FRM` | Farms | 6 + TL | 0.5 × TL (TL 1–5), 1 × TL (TL 6–10) | 100 food (TL 1), 20 × TL food (TL 2+) | 3 unskilled + 1 professional per unit |
| `MIN` | Mines | 10 + (2 × TL) | 0.5 × TL | 100 × TL MU | 3 unskilled + 1 professional per unit |
| `LAB` | Laboratories | — | — | Research points | — |

Farms at TL 1 are standard open-colony farms limited by the planet's habitability. TL 2–5 are hydroponic (usable in orbit or surface within orbit 5). TL 6–10 use artificial sunlight and work anywhere, including ships. Mines can only operate on surface colonies.

### Ship Systems

| Code | Unit | Mass | Fuel / Turn | Notes |
|---|---|---|---|---|
| `AUT` | Automation | 4 × TL | 0 | Each unit replaces TL unskilled workers in production |
| `HEN` | Hyper Engines | 45 × TL | 40 × distance jumped | Jump range: 1 light-year × TL; moves 1,000 MU × TL |
| `LFS` | Life Supports | 8 × TL | 1 × TL | Supports TL² population units |
| `PWP` | Power Plants | — | — | Powers other assembly-required units |
| `SEN` | Sensors | 2,998 + (2 × TL) | TL / 20 | Detects ships/colonies; conducts TL probes per turn |
| `SLS` | Light Structure | 0.05 | 0 | Built only in orbital colonies; substitutes for STU |
| `SPD` | Space Drives | 25 × TL | TL² (combat only) | Thrust: TL² × 1,000; required on all ships |
| `STU` | Structure | 0.5 | 0 | Required for ship and enclosed colony hulls |

## Weapons

| Code | Unit | Mass | Fuel / Turn | Description |
|---|---|---|---|---|
| `ANM` | Anti-Missiles | 4 × TL | 0 | Intercept incoming missiles; effectiveness scales with TL |
| `ASC` | Assault Craft | 5 × TL | 0.1 | Land/space vehicles for colony and ship boarding |
| `ASW` | Assault Weapons | 20 | 0 | Surface combat weapons used by soldiers |
| `ESH` | Energy Shields | 50 × TL | 10 × TL | Deflect energy beams; absorption scales with TL |
| `EWP` | Energy Weapons | 10 × TL | 4 × TL per combat round | Beam weapons; cannot fire surface-to-surface |
| `MSL` | Missile Launchers | 25 × TL | 0 | Assembly-required launcher for missiles and anti-missiles; accuracy scales with TL |
| `MSS` | Missiles | 4 × TL | 0 | Ammunition consumed by missile launchers; does not require assembly |
| `MTBT` | Military Robots | (2 × TL) + 20 | 0 | Replace TL × 2 soldier units; cannot serve in spy units |

## Vehicles

| Code | Unit | Mass | Fuel / Turn | Notes |
|---|---|---|---|---|
| `RPV` | Robot Probes | — | — | Unmanned probes for remote exploration |
| `TPT` | Transports | 4 × TL | TL² / 10 (proportional to load) | Transfer capacity: TL² × 200 MU per turn; 1 professional per 10 transports |

## Consumables

Resources that are produced, stored, and spent. They do not need assembly.

| Code | Unit | Mass | Description |
|---|---|---|---|
| `CNGD` | Consumer Goods | 0.6 | Pay population; affects morale |
| `FOOD` | Food | 6 | Feeds population (0.25 units per population unit per turn) |
| `FUEL` | Fuel | — | Powers ships, engines, and production units |
| `GOLD` | Gold | — | Currency for wages and trade |
| `METS` | Metals | — | Raw material extracted by mines; used in manufacturing |
| `MTSP` | Military Supplies | 0.04 | Ammunition and medical supplies consumed in combat |
| `NMTS` | Non-Metals | — | Raw material extracted by mines; used in manufacturing |
| `RSCH` | Research | — | Produced by laboratories; spent to advance tech levels |

## Population Classes

Population units use a separate set of codes. Each unit represents 100 people.

| Code | Class | Pay (consumer goods / unit / turn) | Death Rate / Turn | Notes |
|---|---|---|---|---|
| `UEM` | Unemployable | 0 | 0.0625% | Children, elderly, casualties; source of all birth increases |
| `USK` | Unskilled | 0.125 | 0.0625% | General labor; can be drafted into soldiers or trained |
| `PRO` | Professional | 0.375 | 0.0625% | Skilled workers for factories, farms, mines, labs |
| `SLD` | Soldier | 0.25 | 0.0625% | Military; draft limit equals current soldier count |
| `SPY` | Spy | 0.375 + 0.25 | 0.0625% | 200 people + 1 PRO + 1 SLD; limited by PRO + SLD count |
| `CNW` | Construction Worker | 0.375 + 0.125 | 0.0625% | 200 people + 1 PRO + 1 USK; execute assembly orders |
| `TRN` | Trainee | — | 0.0625% | 5% graduate to PRO each turn; 1 PRO per 100 trainees |
| `PLC` | Police | — | — | Maintain order; suppress unrest |
| `SAG` | Special Agent | — | — | Counter-intelligence operations |

## Colony Types

| Code | Type | Description |
|---|---|---|
| `CENC` | Enclosed Colony | Sealed habitat; requires life support and structure |
| `COPN` | Open Surface Colony | Built on habitable planet surfaces; no life support needed |
| `CORB` | Orbital Colony | Station in orbit; requires life support and structure |
