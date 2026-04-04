---
title: Set Up a New Game
weight: 1000
---

Use the web interface to build a star cluster, prepare home systems, and assign empires to players.

## Prerequisites

- An admin has created the game and added you as a GM.
- You have a home system template file (JSON) for the starting system layout.
- You have a colony template file (JSON) for the starting colony configuration.

## Steps

### 1. Add members

Open the game and go to the **Members** tab. For each player, select their account from the list and add them. GMs can be promoted from that same tab.

Members can be added or removed at any point, including after the game is active.

### 2. Upload templates

Navigate to the game's **Generate** page. In the **Home System Template** section, upload your home system JSON file. Then upload your colony template in the **Colony Template** section.

Both templates can be replaced until the game is activated.

### 3. Generate the star cluster

In the **Stars** section, click **Generate Stars**. To use a specific seed instead of the game's default, enter it before generating.

After stars are created, click **Generate Planets**, then **Generate Deposits**. Each step must complete before the next becomes available. If you need to start over, delete a step to cascade-remove everything that depends on it.

### 4. Create home systems

In the **Home Systems** section, add a home system for each player slot you want available:

- **Create Random Home System** — picks an eligible star automatically, respecting the minimum distance between home systems.
- **Create Manual Home System** — lets you select a specific star from the dropdown.

Repeat until you have enough home systems for all your players. Each home system holds up to 25 empires, and the game supports up to 250 empires total.

### 5. Activate the game

Once at least one home system exists, the **Activate** section becomes available. Review the summary (stars, planets, deposits, home systems, total capacity) and click **Activate Game**.

Activation is permanent. Templates lock and the cluster cannot be regenerated. Home systems can still be added after activation.

### 6. Assign empires to players

After activation, the **Empires** section appears. For each member without an empire, click **Assign Empire**. Select a home system from the list, or leave it blank to assign one automatically from the available capacity.

Empires can be assigned to newly added members at any time, as long as a home system with remaining capacity exists. If all home systems are full, create a new one first. To move a player to a different home system, use **Reassign** on the same row.
