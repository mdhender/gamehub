---
title: "Population, Pay, and Food"
---

# Population, Pay, and Food

Per-turn calculations for total population, consumer goods (CNGD) payments, and food (FOOD) consumption.

---

## Population Groups

Each colony tracks population by group. Most groups map one-to-one with population: one unit equals one unit of population. **Cadre groups are the exception.** A cadre is composed of two base types, so each cadre unit counts as two population.

| Group | Composition              | Population per Unit |
|-------|--------------------------|---------------------|
| UEM   | Unemployable             | 1                   |
| USK   | Unskilled worker         | 1                   |
| PRO   | Professional             | 1                   |
| SLD   | Soldier                  | 1                   |
| CNW   | 1 USK + 1 PRO            | 2                   |
| SPY   | 1 SLD + 1 PRO            | 2                   |

**Total population** is the sum of each group's quantity multiplied by its population-per-unit value.

---

## CNGD Paid Per Turn

Each population group has a pay rate expressed in consumer goods (CNGD) per unit per turn. The player sets the pay rate for the four base groups (UEM, USK, PRO, SLD). Cadre pay rates are derived:

| Group | Pay Rate           | Derivation                  |
|-------|--------------------|-----------------------------|
| UEM   | Set by player      | Default 0.0000              |
| USK   | Set by player      | Default 0.1250              |
| PRO   | Set by player      | Default 0.3750              |
| SLD   | Set by player      | Default 0.2500              |
| CNW   | USK rate + PRO rate | Default 0.5000             |
| SPY   | SLD rate + PRO rate | Default 0.6250             |

**CNGD paid per turn** for a group equals its **quantity** (not population) multiplied by its **pay rate**. The colony total is the sum across all groups.

```
cngd_paid(group) = quantity × pay_rate
cngd_paid(colony) = SUM(cngd_paid(group)) for all groups
```

---

## FOOD Consumed Per Turn

The standard ration is **0.25 FOOD units per population per turn**. The player sets a **ration percentage** (0%--100% of the standard ration) for each colony.

**FOOD consumed per turn** for a group equals its **population** (not quantity) multiplied by the ration percentage multiplied by the standard ration rate.

```
food_consumed(group) = population × ration% × 0.25
food_consumed(colony) = SUM(food_consumed(group)) for all groups
```

Because cadre groups have two population per unit, a CNW or SPY unit consumes twice the food of a base-type unit at the same ration percentage.

---

## Worked Example

Starting values with default pay rates and 100% rations:

| Group | Quantity  | Population | Pay Rate | CNGD Paid    | Ration % | Food Rate | FOOD Consumed |
|-------|----------:|-----------:|---------:|-------------:|---------:|----------:|--------------:|
| UEM   | 5,900,000 |  5,900,000 |   0.0000 |         0.00 |   100.0% |      0.25 |     1,475,000 |
| USK   | 6,000,000 |  6,000,000 |   0.1250 |   750,000.00 |   100.0% |      0.25 |     1,500,000 |
| PRO   | 1,500,000 |  1,500,000 |   0.3750 |   562,500.00 |   100.0% |      0.25 |       375,000 |
| SLD   | 2,500,000 |  2,500,000 |   0.2500 |   625,000.00 |   100.0% |      0.25 |       625,000 |
| CNW   |    10,000 |     20,000 |   0.5000 |     5,000.00 |   100.0% |      0.25 |         5,000 |
| SPY   |        20 |         40 |   0.6250 |        12.50 |   100.0% |      0.25 |            10 |
| **Total** |       | **15,920,040** |      | **1,942,512.50** |      |           | **3,980,010** |

---

## Starvation

When the effective ration falls below **1/16 (0.0625) FOOD units per population per turn**, starvation occurs. The fraction of the colony population that starves is:

```
S = (M - R) / M
```

Where:
- **M** = 1/16 (minimum food per population unit to avoid starvation)
- **R** = actual food per population unit (must be < M)
- **S** = fraction of colony population that starves
