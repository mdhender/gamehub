---
title: How Manufacturing Works
weight: 10
---

Your colonies can manufacture almost anything — weapons, ships, consumer goods, structural units — as long as you have factories, workers, and raw materials. This page explains how the production system works so you can plan your economy effectively.

## Factory Groups

Factories don't operate individually. They are organized into **factory groups**, and every factory in a group works on the same product. You might have one group building consumer goods, another building structural units, and a third building missiles.

A single group can contain factory units of different tech levels. For example, a group might have 50,000 FCT-1 and 10,000 FCT-2 units all producing the same thing. Higher tech-level factories are more productive.

Factories can build any unit in the game **except** natural resources (fuel, gold, metals, non-metals), food, and population. If you need those, you'll need mines, farms, and good living conditions.

## The Production Pipeline

Manufacturing is not instant. Every unit takes **one full year (four turns)** to produce. Your factories don't just spit out finished goods — they push partially completed units through a three-stage pipeline:

```text
New work → Q1 (25%) → Q2 (50%) → Q3 (75%) → Finished
```

Each turn, everything in the pipeline advances one stage:

1. **Q3** units are finished and delivered to your colony's cargo.
2. **Q2** advances to Q3.
3. **Q1** advances to Q2.
4. New production enters **Q1**.

This means that when you first set up a factory group, you won't see any output for three turns. On the fourth turn, the first batch rolls off the line — and from then on, you get a new batch every turn.

## What Your Factories Need

To keep the pipeline moving, your factory group needs three things every turn:

### Raw Materials

Factories consume metals and non-metals from your cargo. The amount depends on what you're building and the tech level of the product. Simple items like consumer goods use a fraction of a unit each. Complex items like hyper engines consume enormous quantities.

### Labor

Every factory unit needs workers — both professionals and unskilled workers. The good news is that bigger factory groups are more efficient. A group of 50,000+ factories needs only 1 professional and 3 unskilled workers per factory unit, while a tiny group of 4 or fewer needs 6 professionals and 18 unskilled per unit.

Automation units can substitute for unskilled workers.

### Fuel

Factories consume fuel to operate. However, fuel is allocated to mines and farms first. In a fuel shortage, your factories are the first to suffer.

## What Happens During a Shortage

If your colony runs short on any input — materials, labor, or fuel — your factories lose capacity. They don't stop entirely, but they slow down. A shortage affects **all** factory groups on the colony, not just one.

The pipeline stalls proportionally. If your factories can only operate at half capacity, it takes two turns to move a full batch from one stage to the next. This extends delivery times and throws off your production schedule.

## Where the Output Goes

Finished goods are delivered to your colony's **cargo** section. In cargo, items are packed up and take only half their normal volume. This is important for planning your colony's capacity — the output won't take as much space as you might expect.

There is one subtlety worth knowing: when raw materials are pulled from cargo to feed the factories, they go from half volume (in cargo) to full volume (being consumed). This can briefly push your colony over its enclosed capacity.

## Retooling

If you want a factory group to start building something different, you issue a **build change order**. The group doesn't switch instantly — it has to finish everything already in the pipeline first.

During retooling:
- No new work enters Q1.
- Existing units in Q1, Q2, and Q3 continue advancing and delivering as normal.
- Once the pipeline is completely empty, the group switches to the new product.

In the best case, retooling takes three turns (one turn per pipeline stage to drain). But if a shortage hits while your pipeline is draining, everything stalls until the shortage clears. Plan your retooling carefully.

{{< callout type="info" >}}
If your pipeline is already empty — say, a prolonged materials shortage drained it naturally — a retool order takes effect immediately. No waiting.
{{< /callout >}}

## Planning Tips

- **Start factory groups early.** The three-turn startup delay means you need to think ahead.
- **Keep raw materials stocked.** A shortage doesn't just slow production — it can delay retooling too.
- **Group sizes matter.** Larger groups need fewer workers per factory, so consolidating into fewer, bigger groups is more labor-efficient.
- **Watch your cargo capacity.** Finished goods pile up in cargo. If your colony runs out of space, the pipeline backs up and nothing can be delivered.
