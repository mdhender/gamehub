---
title: How Farming Works
weight: 20
---

Your colonies need food to survive. Farms are how you produce it. Unlike factories, which build units through a complex multi-quarter pipeline, farms follow a simpler annual rhythm — but they come with their own risks.

## Farm Groups

Farm units are organized into **farm groups**. A group can contain farm units of different tech levels, all working together. When you assemble new farm units, you assign them to a group (or create a new one).

Farms can only produce one thing: **FOOD**.

## The Growing Cycle

Farming is seasonal. When farm units are assembled and added to a group, they start at 0% and advance one stage each turn:

```text
0% → 25% → 50% → 75% → 100% (harvest!)
```

After harvest, the units go back to 0% and start the cycle again. This means your first harvest comes **four turns** after assembly, and then every four turns after that.

All the food from a harvest arrives at once. There is no trickle of output between harvests — you get nothing for three turns, then a large delivery. Plan your food reserves accordingly.

## What Farms Need

Each farm unit requires **fuel** and **labor** every turn to keep progressing toward harvest:

- **3 unskilled workers** (or equivalent automation units) per farm unit
- **1 professional** per farm unit
- **Fuel** — the amount depends on tech level

The good news is that fuel is allocated to farms (and mines) **before** factories. In a fuel shortage, your farms keep running while your factories slow down.

## When Things Go Wrong

If your farms don't get enough fuel or labor on any turn, the consequences are severe: all progress is lost. Units that were at 50% or 75% don't just pause — they **reset to 0%** and start the full four-turn cycle over.

If the shortage continues the next turn, they reset again. Your farms sit idle, burning through your food stockpile, until you restore their inputs.

This makes farm shortages devastating. A single turn without enough fuel or workers costs you an entire year of food production from the affected units. Protect your farms.

{{< callout type="warning" >}}
A shortage doesn't just delay your harvest — it erases all progress. A farm group at 75% that misses one turn of fuel is back to 0%, four full turns from its next harvest.
{{< /callout >}}

## Scattered Harvests

If a shortage hits only some turns, your farm group can end up with units at different stages. Some units might be at 75% while others — the ones that got reset — are back at 25%. Each subset will harvest on a different turn.

This isn't necessarily bad. Staggered harvests mean more frequent (smaller) food deliveries rather than one large annual batch. But it's not something you can easily control, and it makes food planning harder.

## Farm Types

Not all farms work everywhere. The type you need depends on where your colony is:

- **TL-1 farms** are basic open-air farms. They only work on the surface of habitable planets, and the number you can have is capped by the planet's habitability rating (habitability x 100,000 units). They're cheap and fuel-efficient.

- **TL-2 through TL-5** are hydroponic farms using natural sunlight. They work on orbiting colonies or surface colonies within the fifth orbit. No planet needed — just proximity to a star.

- **TL-6 through TL-10** are hydroponic farms with artificial lighting. They work anywhere — ships, orbital colonies, deep-space colonies. But they burn significantly more fuel.

If you're building an orbital colony or a ship, you'll need higher-tech farms. If you're on a habitable world, TL-1 farms are the most efficient choice.

## Planning Tips

- **Stockpile food before expansion.** New farms take four turns to produce their first harvest. If you're growing your population or building new colonies, make sure you have enough food to bridge the gap.
- **Guard your fuel supply.** A fuel shortage doesn't just slow your farms — it wipes their progress and costs you a full year of output.
- **Automation helps.** Each farm unit needs 3 unskilled workers. Automation units can substitute, freeing up your population for other work.
- **Watch your calendar.** All farms assembled at the same time will harvest at the same time, creating feast-and-famine cycles. Staggering your farm assembly across turns can smooth out your food supply.
