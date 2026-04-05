---
title: "Turn Report Format and Inventory Categories"
date: 2026-04-05T16:00:00
---

{{< callout type="info" >}}
   The sample turn reports gained a new inventory format — five semantic categories replacing the old boolean-filter sections — and a purpose-built orbital colony that stress-tested every enclosure rule in the book. The developer docs now formalize the inventory category rules. 14 commits across the session.
{{< /callout >}}

## Ship and Colony Structure Reference

A new reference page (`developers/reference/ship-structures`) documents the mass, volume, and structural requirements for every unit in the game. VU Factors for each colony and ship type, the enclosed volume formula, the storage half-volume rule, and the content volume calculation are all in one place. The page was built iteratively by working through carrying capacity examples against the sample reports until every number reconciled.

---

## The Orbital Colony

Colony 14 is a small orbital station (CORB, VU Factor 10) with two jobs: produce light structural units and host sensors for system probes. Building it from the CORB template exposed every enclosure rule that matters.

**Staffing.** The original template population of 15.9M was impossibly large for an orbital colony — LFS-1 life support alone would have consumed 127M VU. The station was stripped down to a 3,720-person production crew: 3,000 PRO, 500 SLD, 100 CNW, 10 SPY. The 1,000 FCT-1 factory group sits in the 500–4,999 labor bracket (3 PRO + 9 USK per factory), so the 9,000 USK slots were filled with AUT-1 automation robots instead of human workers. AUT-1 units take up 4 VU each but require no life support, food, consumer goods, or fuel.

**Resource budgeting.** Each FCT-1 ingests 5 MU per quarter. SLS costs 0.01 METS + 0.04 NMTS per unit, so 1,000 factories at capacity need 4,000 METS and 16,000 NMTS per turn plus 500 FUEL. Life support burns 1 FUEL per LFS-1 per turn (3,720 FUEL), and the single SEN-1 adds 0.05 FUEL. Total FUEL budget: 4,221 per quarter. FOOD (930) and CNGD (1,307) are transported from the surface colony.

**Super-structure.** The final enclosure is 1,080,000 STU providing 108,000 VU of enclosed capacity against 98,091 VU of content — roughly 10% headroom.

---

## Cadre Decomposition in the Employed Labor Table

Cadre units (CNW, SPY) decompose into base labor types: each CNW provides 1 USK + 1 PRO, each SPY provides 1 PRO + 1 SLD. The old Employed Labor table showed Espionage and Construction as standalone rows, making it unclear where the extra workers came from.

The new format annotates the source cadre inline:

```
  Employed Labor
    Area______________   USK_______  PRO_______  SLD_______  Total________
    Manufacturing                 0       3,000           0          3,000
    Military                      0           0         500            500
    Construction (CNW)          100         100           0            200
    Espionage    (SPY)            0          10          10             20
    Total                       100       3,110         510          3,720
```

The parentheticals tell players both *what work is being done* and *which census group supplies the workers*. A Total column was added for quick verification.

---

## Inventory Categories

The biggest format change was replacing the boolean-filter inventory sections. The old report grouped items by database predicates:

```
Inventory (in_storage == false, assembly_required == true, assembled == true)
Inventory (in_storage == true, assembly_required == false)
```

Players don't think in database predicates. The new format uses five semantic categories:

- **Super-structure** — structural units forming the hull or enclosure. Assembled, full volume. Not counted as contents.
- **Structure** — operating infrastructure (LFS, SEN). Assembled, full volume.
- **Crew and Passengers** — population after cadre decomposition. Full volume.
- **Operational** — active equipment not in storage (factories, farms, mines, automation). Assembled, full volume.
- **Cargo** — stored items. Not assembled if assembly is required. Half volume.

Each category has a single, unambiguous volume rule. The rules were documented in `developers/explanation/ship-and-colony-inventory` alongside the existing installed-vs-cargo model.

The column formerly called "Enclosed Volume" was split into two names: **Enclosed Capacity** on the super-structure table (maximum volume the structure can hold) and **Volume Used** on content tables (volume the items consume). The old name covered two different concepts.

---

## Cadre Pay Rates

A smaller change: the template loader now computes CNW and SPY pay rates from their base types on import. CNW pay = average of PRO + USK rates. SPY pay = average of PRO + SLD rates. These are engine-derived values that players cannot set, so ignoring any `pay_rate` in the template file prevents data entry errors.

---

## What's Next

The two sample reports (open colony and orbital colony) are now format-aligned and number-verified. The next step is a player-facing "How to Read Your Turn Report" guide — cadre math in particular will trip up new players. The report format also needs a sixth inventory category for items stored outside an open colony (natural resources dumped on the surface, exterior farms), but that's deferred until the exterior storage rules are finalized.
