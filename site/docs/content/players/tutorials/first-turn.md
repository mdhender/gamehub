---
title: Your First Turn
weight: 10
---

In this tutorial we will write and submit our first orders. By the end, we will have named our homeworld and sent the referee a valid order file.

## What you start with

When the referee registers your empire, you receive:

- A **login link** — a URL with a magic token that takes you to your account.
- A **turn 0 report** — a document describing your starting position: one homeworld colony, its starting population, factories, mines, and deposits.
- Your **empire number** — a positive integer that identifies you in the game.

Open your turn report and find two numbers you'll need: your **colony ID** and your **planet ID**. They appear in the header of your homeworld section and look something like:

```text
Colony 7 — orbit 3 of system 4-6-19
Planet 5
```

In this example, the colony ID is `7` and the planet ID is `5`. Yours will be different. Write them down.

## Step 1: Create your orders file

Orders are a plain text file — any text editor works. Create a new file named after your empire and the turn number, for example:

```text
empire-1-turn-0.txt
```

At the top, add a comment block that identifies you and the turn. Lines starting with `//` are comments and are ignored by the parser.

```text
// Empire: 1
// Turn: 0
```

Save the file. We'll add orders to it in the next steps.

## Step 2: Name your homeworld

Your homeworld planet and colony start with no names. Let's give them names now.

Add these two orders to your file, substituting your planet ID and colony ID:

```text
name planet 5 "New Terra"
name colony 7 "Home"
```

Names must be enclosed in double quotes and may be up to 24 characters.

Your file should now look like this:

```text
// Empire: 1
// Turn: 0

name planet 5 "New Terra"
name colony 7 "Home"
```

{{< callout type="info" >}}
You can choose any names you like. The names appear in your turn reports from this turn forward.
{{< /callout >}}

## Step 3: Set wages and rations

Your colony population needs wages and food to remain productive. The turn report shows your current wage rates and ration level, but on turn 0 they are at defaults. Let's set them explicitly so you know what to expect.

Add pay and ration orders for your homeworld colony:

```text
pay 7 unskilled-worker 0.125
pay 7 professional 0.375
ration 7 100%
```

The `pay` order sets the gold-per-turn wage for a population kind. The `ration` order sets the food ration as a percentage of a full ration; 100% means everyone is fully fed.

Your complete file now looks like this:

```text
// Empire: 1
// Turn: 0

name planet 5 "New Terra"
name colony 7 "Home"

pay 7 unskilled-worker 0.125
pay 7 professional 0.375
ration 7 100%
```

{{< callout type="info" >}}
Rations below 25% cause starvation. On your first turn, 100% is safe. You can adjust later if you need to conserve food.
{{< /callout >}}

## Step 4: Submit your orders

Send the file to the referee using whatever method they specified — email, a web upload form, or a shared folder. The filename must make it clear which empire and turn it belongs to.

The referee will validate your file before running the turn. If there are errors, they will contact you with the diagnostic output so you can correct and resubmit.

{{< callout type="warning" >}}
Submit before the deadline. Orders received after the turn runs are not applied. Check with your referee for the schedule.
{{< /callout >}}

## What happens next

After the referee runs turn 0, you will receive a turn 1 report. It will show the results of your orders: your homeworld now has names, and the wages and rations you set are in effect.

You are now playing EC.

---

**Next steps:**
- [Commands reference](/docs/players/reference/commands/) — all orders available in v0
- [Writing orders](/docs/players/tutorials/writing-orders/) — how to format and structure a full order file
