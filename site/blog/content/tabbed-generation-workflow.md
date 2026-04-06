---
title: "Tabbed Generation Workflow"
date: 2026-04-06T13:00:00
---

{{< callout type="info" >}}
   The game generation page and the game show page both gained tabbed interfaces. Generation steps are organized into five tabs with disabled states and query-param persistence. Empires and Turn Reports moved to the show page where they belong. Template uploaders now render regardless of game status.
{{< /callout >}}

## The Problem

The `/games/{id}/generate` page rendered every generation step in a single scrollable column — PRNG seed, home system template, colony template, stars, planets, deposits, home systems, activation, empires, and turn reports. Ten sections, one long page. By the time you reached the bottom, you'd forgotten what was at the top.

Worse, the last two sections — Empires and Turn Reports — aren't generation concerns at all. They're post-activation workflow. A GM who's already activated the game and wants to assign empires shouldn't have to scroll through star tables and deposit summaries to get there.

---

## Two Pages, Two Tab Bars

The fix splits the content across two pages, each with its own tab bar.

### Generate Page

The generate page now has five client-side tabs:

| Tab | Contents |
|---|---|
| **Templates** | PRNG Seed |
| **Stars** | Star generation, inline editing, read-only seed display |
| **Planets** | Planet generation, inline editing, read-only seed display |
| **Deposits** | Deposit generation, read-only seed display |
| **Home Systems** | Home system template uploader, home system creation, activation |

All five tabs are always visible. Tabs for steps that aren't reachable yet — you can't generate planets before stars exist — are rendered with `opacity-50 cursor-not-allowed` and don't respond to clicks. The enablement logic checks both the game's capability flags and its current status, so tabs remain accessible for reviewing completed steps.

The delete-step confirmation dialog stays at the page root, shared across all tabs that need it.

### Show Page

The show page already had a Members tab and a Generate link. It now has two additional tabs:

| Tab | Visibility | Contents |
|---|---|---|
| **Members** | Always | Member management (unchanged) |
| **Empires** | Active games only | Colony template uploader, empire assignment |
| **Turn Reports** | Active games only | Report generation, locking, download links |

The Generate link remains as navigation to the generate page. The Setup Report section for players stays outside the tabs, unchanged.

---

## Template Uploaders Moved

Two template uploaders changed homes during this work.

**Home System Template** moved from the Templates tab to the Home Systems tab on the generate page. The home system template defines the planet and deposit layout for player starting systems — it's only relevant when you're actually creating home systems. Having it on the Templates tab alongside the PRNG seed was clutter.

**Colony Template** moved from the generate page to the show page's Empires tab. Colony templates define starting colonies — population, inventory, production capacity. That's an empire setup concern, not a map generation concern. Putting it next to empire assignment makes the GM workflow linear: upload the template, then assign empires.

Both uploaders were also simplified. Previously they were gated behind game status checks — you couldn't upload a home system template after stars were generated, for instance. That restriction was removed. Templates can now be uploaded at any point, regardless of game status. The upload form always renders.

---

## Backend Prop Split

The backend change was straightforward. `GameGenerationController::show()` was serving `members` and `reportTurn` props that belong on the show page. Those props moved to `GameController::show()` under distinct names — `empireMembers`, `empireHomeSystems`, `reportTurn`, and `colonyTemplate` — wrapped in a conditional that only computes them when the game is active. The `game` prop on the show page also gained `can_assign_empires` and `can_generate_reports` flags.

---

## Tab Persistence

Tab state is stored in the `?tab=` query parameter. Clicking a tab updates the URL via `history.replaceState` — no server round-trip, no Inertia visit. Reloading the page or copy-pasting the URL reopens the correct tab.

Invalid or unreachable values fall back to a safe default: `templates` on the generate page, `members` on the show page. Requesting `?tab=empires` on an inactive game silently falls back to Members rather than rendering an empty panel.

---

## Redirect After Generation

After completing a generation step, the server now redirects to the next logical tab instead of returning to the current one:

- Stars generated → `?tab=planets`
- Planets generated → `?tab=deposits`
- Deposits generated → `?tab=home-systems`
- Game activated → show page `?tab=empires`

Template uploads, inline edits, and delete-step actions still return to the current tab — the `?tab=` in the referer URL handles that naturally via `return back()`.

---

## Cleanup

With Empires and Turn Reports no longer on the generate page, `GenerationPagePresenter` had two orphaned methods — `membersList()` and `reportTurnPayload()`. Both were removed along with their unused imports. The presenter still serves `homeSystemsList()` and `availableStarsList()` for the generate page.

---

## Tests

All existing tests were updated to reflect the new page responsibilities. Prop assertions that expected `members` and `reportTurn` on the generate page were removed or repointed to the show page with the new prop names. Redirect assertions were updated for the `?tab=` behavior. The full test suite passes.

---

## What's Next

The generation workflow and GM management pages are now structured for the long haul — tabs keep related content together, templates live where they're used, and the URL always reflects the current state. Next up: wiring the inventory model into the turn-processing pipeline and building the order parser.
