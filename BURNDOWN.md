# BURNDOWN

Game generation workflow — tabbed UI refactor.

**Date:** 2026-04-06

**Notes:**
- Tasks are ordered by **dependencies**, not severity.
- Tasks at the same level can be parallelized unless they touch the same file(s).
- Do not check a task off until its acceptance criteria pass.
- Run `vendor/bin/pint --dirty --format agent` after modifying any PHP files.
- Run `bun run build` after modifying frontend files to regenerate Wayfinder actions.

---

## Problem Statement

The `/games/{gameId}/generate` page renders all generation steps linearly (PRNG Seed, Home System Template, Colony Template, Stars, Planets, Deposits, Home Systems, Activate, Empires, Turn Reports), causing excessive scrolling. The Empires and Turn Reports sections are post-activation concerns that belong on the main game page, not the generation workflow.

## Proposed Design

### Generate page (`/games/{gameId}/generate`)

Convert the linear layout into **5 client-side tabs**:

| Tab | Contents |
|---|---|
| **Templates** | PrngSeedSection, HomeSystemTemplateSection, ColonyTemplateSection |
| **Stars** | StarsSection + read-only PRNG seed display |
| **Planets** | PlanetsSection + read-only PRNG seed display |
| **Deposits** | DepositsSection + read-only PRNG seed display |
| **Home Systems** | HomeSystemsSection + ActivateSection |

- All 5 tabs are **always visible**. Tabs for unreachable steps are **disabled** (`opacity-50 cursor-not-allowed`), not hidden.
- The delete-step confirmation dialog is **shared** across all tabs (stays at page root).
- After a successful generation step, redirect to the next logical tab via `?tab=` query param.

### Game show page (`/games/{gameId}`)

Add **Empires** and **Turn Reports** tabs alongside the existing **Members** tab:

| Tab | Visibility | Contents |
|---|---|---|
| **Members** | Always | Existing member management |
| **Empires** | Active games only | EmpiresSection (moved from generate page) |
| **Turn Reports** | Active games only | TurnReportsSection (moved from generate page) |

The existing **Generate** link remains as navigation to the generate page. The **Setup Report** section for players remains outside the tabs, unchanged.

### Tab persistence

- Tab state is stored in `?tab=` query params.
- Client-side tab clicks update the URL via `history.replaceState` (no server round-trip).
- Invalid or unreachable tab values fall back to a safe default.

---

## Tasks

### GEN-01 — Move active-game props from generate page to show page (backend)

**Effort:** M
**Dependencies:** None

**Problem:** The generate page currently serves `members` and `reportTurn` props that belong on the show page. The show page needs these props (plus `homeSystems` for empire assignment) when the game is active.

**Files to modify:**
- `app/Http/Controllers/GameGenerationController.php` — remove `members` and `reportTurn` from the `show()` Inertia props
- `app/Http/Controllers/GameController.php` — add active-game props to `show()`:
  - `empireMembers` — player members with their empire assignments (reimplement `GenerationPagePresenter::membersList()` logic)
  - `empireHomeSystems` — home systems with empire counts (reimplement `GenerationPagePresenter::homeSystemsList()` logic)
  - `reportTurn` — current turn payload (reimplement `GenerationPagePresenter::reportTurnPayload()` logic)
  - Wrap all three in a conditional: only compute when `$game->isActive()`
- `app/Support/GameGeneration/GenerationPagePresenter.php` — no changes yet (cleanup in GEN-08)

**Important:** Use distinct prop names (`empireMembers`, `empireHomeSystems`) to avoid colliding with the existing `members` prop on the show page. The `Game` type on the show page also needs `can_assign_empires` and `can_generate_reports` — add these to the `game` prop in `GameController::show()`.

**Acceptance:**
- [x] `GET /games/{id}/generate` response no longer includes `members` or `reportTurn` props
- [x] `GET /games/{id}` response includes `empireMembers`, `empireHomeSystems`, and `reportTurn` when game is active
- [x] `GET /games/{id}` response does NOT include `empireMembers`, `empireHomeSystems`, or `reportTurn` when game is inactive
- [x] `setupReport` behavior is unchanged
- [x] `php artisan test --compact tests/Feature/GameGenerationControllerTest.php tests/Feature/GameGenerationReportPropsTest.php tests/Feature/GameShowSetupReportTest.php`
- [x] `vendor/bin/pint --dirty --format agent`

---

### GEN-02 — Add Empires and Turn Reports tabs to the game show page (frontend)

**Effort:** M
**Dependencies:** GEN-01

**Problem:** Empires and Turn Reports need to appear as tabs on the show page for active games. The show page currently only has a Members tab and a Generate link.

**Files to modify:**
- `resources/js/pages/games/show.tsx` — extend `type Tab` to `'members' | 'empires' | 'turn-reports'`; add tab buttons for Empires and Turn Reports (only render when `game.is_active`); render `EmpiresSection` and `TurnReportsSection` in the corresponding tab panels; add the necessary type imports and prop declarations for `empireMembers`, `empireHomeSystems`, `reportTurn`
- `resources/js/pages/games/generate/types.ts` — no changes needed (types are already exported and can be imported by `show.tsx`)

**Reuse existing components:** Import `EmpiresSection` from `./generate/EmpiresSection` and `TurnReportsSection` from `./generate/TurnReportsSection`. These components accept `game`, `members`/`homeSystems`/`reportTurn` props — map the show page's `empireMembers` → `members` and `empireHomeSystems` → `homeSystems` when passing props.

**Note:** The `Game` type in `show.tsx` needs to be extended with `can_assign_empires: boolean` and `can_generate_reports: boolean` (or import the `Game` type from `generate/types.ts` and merge). Keep the existing Generate link as navigation. Leave the Setup Report section outside the tabs.

**Acceptance:**
- [x] Inactive game show page: only Members tab visible, no Empires or Turn Reports tabs
- [x] Active game show page: Members, Empires, and Turn Reports tabs all render
- [x] Switching tabs does not trigger a server visit
- [x] Empires tab renders assignments and assignment/reassignment actions
- [x] Turn Reports tab renders generate/lock buttons and report links
- [x] Generate link still navigates to `/games/{id}/generate`
- [x] Setup Report section still appears for players as before
- [x] `bun run build` succeeds

---

### GEN-03 — Convert generate page from linear layout to 5 tabs (frontend)

**Effort:** M
**Dependencies:** GEN-01

**Problem:** The generate page renders all sections in a single scrollable column. It needs 5 client-side tabs.

**Files to modify:**
- `resources/js/pages/games/generate.tsx` — remove `EmpiresSection` and `TurnReportsSection` imports/usages; remove `members` and `reportTurn` from the component props; add `useState` for tab selection with type `'templates' | 'stars' | 'planets' | 'deposits' | 'home-systems'`; render 5 tab buttons following the existing pattern from `show.tsx` lines 152–173; render one panel at a time based on active tab:
  - **Templates:** `PrngSeedSection` + `HomeSystemTemplateSection` + `ColonyTemplateSection`
  - **Stars:** `StarsSection` (keep existing `Deferred` wrapper)
  - **Planets:** `PlanetsSection` (keep existing `Deferred` wrapper)
  - **Deposits:** `DepositsSection`
  - **Home Systems:** `HomeSystemsSection` + `ActivateSection`
- Keep the delete-step confirmation dialog at page root, outside any tab panel
- Keep the `deleteConfig`, `deleteForm`, `handleDeleteConfirm` logic unchanged

**Acceptance:**
- [x] Generate page shows exactly 5 tab buttons
- [x] Only one panel's content is shown at a time
- [x] Templates tab contains PRNG Seed, Home System Template, and Colony Template sections
- [x] Home Systems tab contains both Home Systems and Activate sections
- [x] No Empires or Turn Reports content on the generate page
- [x] Delete confirmation dialog works from Stars, Planets, Deposits, and Home Systems tabs
- [x] `bun run build` succeeds

---

### GEN-04 — Add disabled-tab states and read-only PRNG seed context

**Effort:** S
**Dependencies:** GEN-03

**Problem:** Tabs for unreachable steps should be visible but disabled. Generator tabs (Stars, Planets, Deposits) should display the PRNG seed value as read-only context.

**Files to modify:**
- `resources/js/pages/games/generate.tsx` — add tab enablement logic based on `game` status/capability flags:
  - `templates`: always enabled
  - `stars`: enabled when `game.can_generate_stars` OR stars already exist (i.e., status is past `setup`)
  - `planets`: enabled when `game.can_generate_planets` OR planets already exist (status is past `stars_generated`)
  - `deposits`: enabled when `game.can_generate_deposits` OR deposits already exist (status is past `planets_generated`)
  - `home-systems`: enabled when `game.can_create_home_systems` OR `game.can_activate` OR `game.can_assign_empires`
  
  Disabled tabs: `opacity-50 cursor-not-allowed`, clicking does nothing. Prevent selecting a disabled tab.
- `resources/js/pages/games/generate/StarsSection.tsx` — add a read-only display of `game.prng_seed` (e.g., `<p className="text-sm text-muted-foreground font-mono">Seed: {game.prng_seed}</p>`) above the existing content. The Stars section already has a "Seed override" input field — this read-only display provides context.
- `resources/js/pages/games/generate/PlanetsSection.tsx` — add same read-only seed display
- `resources/js/pages/games/generate/DepositsSection.tsx` — add same read-only seed display

**Acceptance:**
- [x] All 5 generate tabs are always visible
- [x] Unreachable tabs have `opacity-50 cursor-not-allowed` styling and cannot be selected
- [x] Setup-status game: Templates and Stars enabled; Planets, Deposits, Home Systems disabled
- [x] Stars-generated game: Templates, Stars, Planets enabled; Deposits, Home Systems disabled
- [x] Planets-generated game: Templates through Deposits enabled; Home Systems disabled
- [x] Deposits-generated game: all tabs enabled
- [x] Stars, Planets, and Deposits panels each show `game.prng_seed` as read-only text
- [x] `bun run build` succeeds

---

### GEN-05 — Make tab selection query-param-backed on both pages

**Effort:** M
**Dependencies:** GEN-02, GEN-03

**Problem:** Tab state needs to survive page reloads and Inertia redirects. Use `?tab=` query params with `history.replaceState`.

**Files to modify:**
- `resources/js/pages/games/generate.tsx`:
  - On mount, parse `?tab=` from `window.location.search` into `useState` initial value
  - If the parsed tab is invalid or disabled, fall back to the latest reachable tab (or `templates`)
  - On tab click, update local state AND call `window.history.replaceState(null, '', newUrl)` to update `?tab=` without triggering a navigation
- `resources/js/pages/games/show.tsx`:
  - Same pattern: parse `?tab=` on mount, fall back to `members`
  - On tab click, update state and `replaceState`
  - Invalid values like `?tab=empires` on inactive games fall back to `members`

**Acceptance:**
- [x] Reloading the page preserves the selected tab
- [x] Copy/pasting the URL opens the correct tab
- [x] Invalid `?tab=` values do not break the page (falls back to default)
- [x] Disabled generate tabs cannot become the active panel via URL manipulation
- [x] Show page accepts `?tab=empires` and `?tab=turn-reports` on active games
- [x] Client-side tab switching does not cause an Inertia visit
- [x] `bun run build` succeeds

---

### GEN-06 — Redirect successful generation actions to the next logical tab

**Effort:** S
**Dependencies:** GEN-05

**Problem:** After completing a generation step, the user should land on the next tab automatically instead of staying on the current one.

**Files to modify:**
- `app/Http/Controllers/GameGeneration/GenerationStepController.php`:
  - `generateStars()` — change `return back()` to `return redirect()->to(route('games.generate.show', $game) . '?tab=planets')->with('success', ...)`
  - `generatePlanets()` — redirect to `?tab=deposits`
  - `generateDeposits()` — redirect to `?tab=home-systems`
- `app/Http/Controllers/GameGenerationController.php`:
  - `activate()` — change `return back()` to `return redirect()->route('games.show', $game, ['tab' => 'empires'])->with('success', ...)`

**Keep `return back()` for:** template uploads, star edits, planet edits, delete-step, home system creation, empire assignment/reassignment, report actions. These should stay on the current tab (the `?tab=` in the referer URL handles this naturally).

**Acceptance:**
- [x] After Stars generation → user lands on Planets tab (`?tab=planets`)
- [x] After Planets generation → user lands on Deposits tab (`?tab=deposits`)
- [x] After Deposits generation → user lands on Home Systems tab (`?tab=home-systems`)
- [x] After game activation → user lands on game show page Empires tab (`?tab=empires`)
- [x] Template uploads, edits, and deletes still return to the current tab
- [x] `php artisan test --compact tests/Feature/GameGenerationControllerTest.php tests/Feature/GameGenerationControllerActivateTest.php`
- [x] `vendor/bin/pint --dirty --format agent`

---

### GEN-07 — Update feature tests for the new page responsibilities

**Effort:** M
**Dependencies:** GEN-01 through GEN-06

**Problem:** Existing tests assert props on the wrong pages now. Redirect assertions need updating for the new `?tab=` behavior.

**Files to modify:**
- `tests/Feature/GameGenerationControllerTest.php`:
  - Remove assertions that `/generate` response includes `members`
  - Remove assertions that `/generate` response includes `reportTurn`
  - Update any redirect assertions that now target `?tab=` URLs
- `tests/Feature/GameGenerationReportPropsTest.php`:
  - Repoint active-game empire/report prop assertions from `GET /games/{id}/generate` to `GET /games/{id}`
  - Update prop names: `members` → `empireMembers`, add `empireHomeSystems`
  - Assert `reportTurn.can_generate`, `reportTurn.can_lock`, `empireMembers[*].empire.has_report`
- `tests/Feature/GameGenerationControllerActivateTest.php`:
  - Update redirect assertion: activation should redirect to `/games/{id}?tab=empires`
- `tests/Feature/GameShowSetupReportTest.php`:
  - Verify `setupReport` behavior is unchanged
  - Optionally add assertions for the new active-game props (`empireMembers`, `empireHomeSystems`, `reportTurn`)

**Acceptance:**
- [x] All generate page tests no longer expect moved props (`members`, `reportTurn`)
- [x] Active-game empire/report props are covered on the show route
- [x] Redirect-to-next-tab behavior is covered
- [x] Setup report tests still pass unchanged
- [x] `php artisan test --compact tests/Feature/GameGenerationControllerTest.php tests/Feature/GameGenerationControllerActivateTest.php tests/Feature/GameGenerationControllerEmpireTest.php tests/Feature/GameGenerationControllerCreateHomeSystemTest.php tests/Feature/GameGenerationControllerDeleteStepTest.php tests/Feature/GameGenerationControllerUpdatePlanetTest.php tests/Feature/GameGenerationControllerUpdateStarTest.php tests/Feature/GameGenerationReportPropsTest.php tests/Feature/GameShowSetupReportTest.php`
- [x] `vendor/bin/pint --dirty --format agent`

---

### GEN-08 — Clean up dead presenter code and unused imports

**Effort:** S
**Dependencies:** GEN-07

**Problem:** After the refactor, `GenerationPagePresenter` still has methods and imports that are no longer used by the generate page.

**Files to modify:**
- `app/Support/GameGeneration/GenerationPagePresenter.php`:
  - Remove `membersList()` method
  - Remove `reportTurnPayload()` method
  - Remove unused imports: `TurnStatus`, `TurnReport`
  - Keep `homeSystemsList()` — still used by the generate page
  - Keep `availableStarsList()` — still used by the generate page

**Acceptance:**
- [x] No unused methods remain in `GenerationPagePresenter`
- [x] No controller references removed methods
- [x] `php artisan test --compact tests/Feature/GameGenerationControllerTest.php tests/Feature/GameGenerationReportPropsTest.php tests/Feature/GameShowSetupReportTest.php`
- [x] `vendor/bin/pint --dirty --format agent`

---

## Execution Order

Tasks should be completed in this order. Tasks at the same indentation level can be parallelized.

```
GEN-01  (backend prop split)

  GEN-02  (show page: Empires + Turn Reports tabs)      ← parallel
  GEN-03  (generate page: 5 tabs)                       ← parallel

GEN-04  (disabled tabs + read-only seed)                 ← after GEN-03

GEN-05  (query-param tab persistence)                    ← after GEN-02 + GEN-03

GEN-06  (redirect to next tab)                           ← after GEN-05

GEN-07  (update feature tests)                           ← after GEN-01 through GEN-06

GEN-08  (cleanup dead code)                              ← after GEN-07
```
