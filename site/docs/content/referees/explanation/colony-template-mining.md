---
title: Mine Production and Deposit Assignment
---

# Mine Production and Deposit Assignment

Mines extract raw materials from planetary deposits. Unlike factories and farms, mines have no pipeline stages and no growth cycle. Each mine group produces one quarter of its annual output every turn.

## How mines produce

A mine group is linked to exactly one deposit on the colony's planet. Every turn, the group calculates output from the number of mine units, their tech level, and the deposit's yield. Output is one quarter of the annual rate, delivered directly to Cargo. There is no work-in-progress pipeline and no multi-stage cycle.

## One group, one deposit

Each mine group has a 1:1 relationship with a planetary deposit. A deposit represents a specific resource vein (fuel, gold, metals, non-metals) on the planet. The group's mine units extract from that deposit and cannot be reassigned to a different one.

## Why mines are absent from the template JSON

Colony templates describe a colony's starting configuration before it is placed on a specific planet. Deposits are a property of the planet, not the template. Since the GM does not know which planet a colony will land on when writing the template, mine groups cannot be specified in the JSON.

The `production.mines` key is allowed in the template JSON but is expected to be empty or absent. The importer skips missing mines gracefully.

## Deposit assignment during empire creation

When `EmpireCreator` places a colony on its homeworld planet, it assigns deposits to mine groups:

1. Query all deposits on the homeworld planet, ordered by ID.
2. Match each template mine group to a deposit by position (first group gets first deposit, second group gets second deposit, and so on).
3. If the planet has fewer deposits than the template requires mine groups, empire creation fails with an error.
4. Create live `ColonyMineGroup` records with the assigned `deposit_id`.
5. Copy mine units from the template to the live mine group.

The template's `deposit_id` is nullable because it cannot be known at template time. The live colony's `deposit_id` is required because a mine group without a deposit has nothing to extract.

## Input shortages

When a mine group lacks sufficient inputs (fuel, labor), its output is reduced proportionally. If a group has 50% of the required inputs, it produces 50% of normal output. Unlike farms, there is no reset penalty -- the mine does not lose accumulated progress because there is no accumulation to lose. A shortage costs exactly one turn of reduced output.
