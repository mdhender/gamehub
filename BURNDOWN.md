# BURNDOWN — Layer 1, Groups H & I

Setup Report: Tests and Frontend

**Source:** `docs/SETUP_REPORT.md` — Build Plan, Tasks #34–37  
**Prerequisite:** Groups A–G are complete (enums, migrations, models, factories, templates, business logic, report schema, service, routes, authorization, controller).

---

## Architectural Decisions

- **Keep `TurnReportController::show()` on Blade** for Layer 1. The existing `resources/views/turn-reports/show.blade.php` is a standalone monospace text report — do not convert it to Inertia.
- **Use plain anchor tags** (`<a href=...>`) for show/download links since they return non-Inertia responses (Blade HTML and JSON attachment).
- **Use Wayfinder** for POST routes (generate, lock) and for constructing typed hrefs.
- **Prefer focused new test files** for new coverage rather than bloating existing tests.
- **No JS component test framework** is needed — TypeScript typecheck + ESLint + PHPUnit covers this slice.

---

## Group H — Tests

### H.1 — Audit existing test suites for schema regressions

**Build plan ref:** Task #34  
**Effort:** S  
**Dependencies:** None

**Goal:** Run all pre-existing test suites that may have been affected by Groups A–E schema changes. Record what passes and what fails. Do not fix anything yet.

**Instructions:**
1. Run each suite listed below independently and record pass/fail.
2. Categorize failures into: (a) broken by schema change, (b) semantically stale but passing, (c) already aligned.
3. Produce a summary of failures mapped to H.2, H.3, or H.4.

**Test commands:**
```bash
php artisan test --compact tests/Feature/EmpireCreatorTest.php
php artisan test --compact tests/Feature/GameGenerationControllerTest.php
php artisan test --compact tests/Feature/UploadColonyTemplateValidationTest.php
php artisan test --compact tests/Feature/GameGenerationControllerActivateTest.php
php artisan test --compact tests/Feature/Models/TemplateTest.php
php artisan test --compact tests/Feature/Models/TurnModelTest.php
php artisan test --compact tests/Feature/Models/GameTurnRelationshipTest.php
php artisan test --compact tests/Feature/Models/ColonyModelTest.php
php artisan test --compact tests/Feature/Models/ColonyPopulationModelTest.php
php artisan test --compact tests/Feature/Models/ColonyTemplateModelTest.php
php artisan test --compact tests/Feature/Models/ColonyTemplatePopulationModelTest.php
php artisan test --compact tests/Feature/Models/ColonyInventoryModelTest.php
php artisan test --compact tests/Feature/Models/ColonyTemplateItemModelTest.php
```

**Acceptance criteria:**
- [ ] All listed suites have been executed
- [ ] A failure/staleness list exists with each failure mapped to H.2, H.3, or H.4
- [ ] No production code is changed in this task

---

### H.2 — Fix template relationship and fixture assumptions in model tests

**Build plan ref:** Task #34  
**Effort:** S  
**Dependencies:** H.1

**Goal:** Fix any model/template tests that still assume the old single-template or integer-column schema.

**Files to potentially modify:**
- `tests/Feature/Models/TemplateTest.php`
- `tests/Feature/Models/ColonyTemplateModelTest.php`
- `tests/Feature/Models/ColonyTemplateItemModelTest.php`
- `tests/Feature/Models/ColonyInventoryModelTest.php`
- `tests/Feature/Models/ColonyModelTest.php`

**Instructions:**
1. Fix any tests that assume `colony_inventory.unit` is an integer — it is now a string cast to `UnitCode` enum.
2. Fix any tests that assume `colony_template_items.unit` is an integer — same change.
3. Fix any tests that assume `colonies.kind` is an integer — it is now a string cast to `ColonyKind` enum.
4. Fix any tests that assume `colony_templates.kind` is an integer — same change.
5. Update `TemplateTest::game_has_one_colony_template` if it fails — the `Game::colonyTemplate()` hasOne relationship still exists alongside `colonyTemplates()` hasMany, so it should still work with a single template. If this test is semantically stale but passing, leave it.
6. If tests use factories, ensure those factories produce enum-compatible values (they should already from Group B work).

**Test commands:**
```bash
php artisan test --compact tests/Feature/Models/TemplateTest.php
php artisan test --compact tests/Feature/Models/ColonyTemplateModelTest.php
php artisan test --compact tests/Feature/Models/ColonyTemplateItemModelTest.php
php artisan test --compact tests/Feature/Models/ColonyInventoryModelTest.php
php artisan test --compact tests/Feature/Models/ColonyModelTest.php
```

**Acceptance criteria:**
- [ ] All model tests pass with the current schema
- [ ] No test relies on integer values for `unit`, `kind`, or `population_code` columns
- [ ] Factory-produced values are enum-compatible

---

### H.3 — Fix upload-template and generation controller tests

**Build plan ref:** Task #34  
**Effort:** M  
**Dependencies:** H.1

**Goal:** Fix pre-existing template upload and generation controller tests to match the current JSON contract:
- Colony template is an array of colony definitions
- `kind` is a string enum code (e.g., `COPN`)
- `inventory` items use `CODE-TL` format for tech-level units (e.g., `FCT-1`) and plain codes for consumables (e.g., `FUEL`)
- `population` section is required

**Files to potentially modify:**
- `tests/Feature/UploadColonyTemplateValidationTest.php`
- `tests/Feature/GameGenerationControllerTest.php`

**Instructions:**
1. Read `sample-data/beta/colony-template.json` for the current valid template format.
2. Read `app/Http/Requests/UploadColonyTemplateRequest.php` for current validation rules.
3. Update all "valid payload" helpers in these tests to use the current array-based format with population section.
4. Update validation tests so invalid-payload assertions reflect the current rules (array structure, `CODE-TL` format, string kind, population required).
5. For upload controller tests, assert both inventory and population persistence after upload.
6. For replacement tests, assert delete-and-recreate semantics for multiple templates.

**Test commands:**
```bash
php artisan test --compact tests/Feature/UploadColonyTemplateValidationTest.php
php artisan test --compact tests/Feature/GameGenerationControllerTest.php
```

**Acceptance criteria:**
- [ ] Validation tests use the current array-based colony template format with population
- [ ] Controller upload tests assert both inventory and population persistence
- [ ] Replacement tests verify old templates are deleted and new ones created
- [ ] All upload-related tests pass

---

### H.4 — Fix EmpireCreator and activation test regressions

**Build plan ref:** Task #34  
**Effort:** S  
**Dependencies:** H.2, H.3

**Goal:** Ensure EmpireCreator and activation tests pass after the fixture fixes in H.2/H.3. Only fix what is broken.

**Files to potentially modify:**
- `tests/Feature/EmpireCreatorTest.php`
- `tests/Feature/GameGenerationControllerActivateTest.php`

**Instructions:**
1. Run both suites after H.2 and H.3 are complete.
2. If they pass, skip this task — mark all acceptance criteria as done.
3. If they fail:
   - Update helper methods so colony template fixtures include population rows (the `activeGameWithHomeSystem()` and `gameWithHomeSystem()` helpers).
   - Keep Turn 0 assertions aligned with the current `activate()` behavior.
   - Do not refactor beyond fixing the setup helpers.

**Test commands:**
```bash
php artisan test --compact tests/Feature/EmpireCreatorTest.php
php artisan test --compact tests/Feature/GameGenerationControllerActivateTest.php
```

**Acceptance criteria:**
- [ ] `EmpireCreatorTest` passes (all 12 tests)
- [ ] `GameGenerationControllerActivateTest` passes (all 5 tests)
- [ ] No unnecessary fixture churn was introduced

---

### H.5 — Add SetupReportGenerator snapshot lifecycle tests

**Build plan ref:** Task #35  
**Effort:** M  
**Dependencies:** H.4

**Goal:** Cover the highest-value untested behaviors in the report materialization lifecycle: snapshot immutability, historical survival after live data deletion, and rerun content refresh.

**Files to modify:**
- `tests/Feature/Services/SetupReportGeneratorTest.php`

**Instructions:**
Add these focused tests to the existing test file (it already has the heavy setup helpers):

1. **Snapshot immutability after live data changes:**
   - Generate report.
   - Mutate live colony name, inventory quantity, and population quantity.
   - Assert report tables still contain the original snapshot values.

2. **Historical survival after live colony deletion:**
   - Generate report.
   - Delete the live colony (cascade deletes live inventory/population).
   - Assert the report still exists and exposes snapshot data.

3. **Rerun refreshes stale snapshot content:**
   - Generate report, record original snapshot values.
   - Change live colony name and inventory quantity.
   - Re-run generator on the same turn (call `$turn->fresh()` before second run).
   - Assert snapshot values update to the new live values.
   - Assert stale child rows are replaced (count checks), not accumulated.

4. **Multi-colony snapshot with multiple templates:**
   - Create a second colony template (e.g., `ColonyKind::Orbital`).
   - Create empire — it gets two colonies.
   - Generate report.
   - Assert report contains two `turn_report_colonies` rows, each with correct kind and own inventory/population.

**Test commands:**
```bash
php artisan test --compact tests/Feature/Services/SetupReportGeneratorTest.php
```

**Acceptance criteria:**
- [ ] Snapshot immutability test proves report data is stable after live data mutation
- [ ] Historical survival test proves reports survive live colony deletion
- [ ] Rerun test asserts snapshot content refreshes, not just row counts
- [ ] Multi-colony test verifies multiple templates produce distinct report colony entries
- [ ] All `SetupReportGeneratorTest` tests pass

---

### H.6 — Fill remaining controller test gaps

**Build plan ref:** Task #35  
**Effort:** S  
**Dependencies:** None (can run in parallel with H.5)

**Goal:** Add targeted controller-level test coverage for gaps not already handled by the existing extensive suites.

**Files to modify:**
- `tests/Feature/TurnReports/TurnReportControllerGenerateTest.php`
- `tests/Feature/TurnReports/TurnReportControllerLockTest.php`
- `tests/Feature/TurnReports/TurnReportControllerShowTest.php`
- `tests/Feature/TurnReports/TurnReportControllerDownloadTest.php`

**Instructions:**
Add only the missing gaps:

1. **Generate — admin happy path:**
   - Admin user (no game role) can generate reports.
   - Use `User::factory()->create(['is_admin' => true])`.

2. **Lock — admin happy path:**
   - Admin user can lock reports.

3. **Show — turn route scoping:**
   - Show returns 404 when `{turn}` belongs to another game (the existing test only checks empire cross-game, not turn cross-game).

4. **Download — turn route scoping:**
   - Download returns 404 when `{turn}` belongs to another game.

Do not duplicate policy coverage already in `TurnReportPolicyTest.php`.

**Test commands:**
```bash
php artisan test --compact tests/Feature/TurnReports/TurnReportControllerGenerateTest.php
php artisan test --compact tests/Feature/TurnReports/TurnReportControllerLockTest.php
php artisan test --compact tests/Feature/TurnReports/TurnReportControllerShowTest.php
php artisan test --compact tests/Feature/TurnReports/TurnReportControllerDownloadTest.php
```

**Acceptance criteria:**
- [ ] Admin generate/lock paths are covered
- [ ] Show/download turn-scoping 404 tests exist
- [ ] No policy-logic duplication is added
- [ ] All four controller test files pass

---

## Group I — Frontend

### I.1 — Run Wayfinder generation for new report routes

**Build plan ref:** Task #36  
**Effort:** S  
**Dependencies:** None

**Goal:** Generate typed frontend route helpers for the `TurnReportController` actions.

**Instructions:**
1. Run `php artisan wayfinder:generate`.
2. Verify a file is generated at `resources/js/actions/App/Http/Controllers/TurnReportController.ts` (or similar path).
3. Confirm the generated file exposes typed helpers for: `generate`, `lock`, `show`, `download`.
4. Do not hand-edit generated files.

**Test commands:**
```bash
php artisan wayfinder:generate
bun run build
```

**Acceptance criteria:**
- [ ] `TurnReportController` Wayfinder file exists with typed helpers
- [ ] Helpers cover `generate`, `lock`, `show`, `download` actions
- [ ] TypeScript compiles without errors after generation

---

### I.2 — Expose report state props to the generate page

**Build plan ref:** Task #37  
**Effort:** M  
**Dependencies:** I.1

**Goal:** Add backend props so the generate page has the data needed for a GM Turn Reports section.

**Files to modify:**
- `app/Http/Controllers/GameGenerationController.php`
- `resources/js/pages/games/generate/types.ts`

**Files to create:**
- `tests/Feature/GameGenerationReportPropsTest.php`

**Instructions:**

1. **Add `reportTurn` prop** to `GameGenerationController::show()`:
   ```php
   'reportTurn' => $this->reportTurnPayload($game),
   ```

2. **Implement `reportTurnPayload()` private method:**
   - If the game has no current turn, return `null`.
   - Otherwise return:
     ```php
     [
         'id' => $currentTurn->id,
         'number' => $currentTurn->number,
         'status' => $currentTurn->status->value,
         'reports_locked_at' => $currentTurn->reports_locked_at?->toIso8601String(),
         'can_generate' => $game->canGenerateReports(),
         'can_lock' => $game->isActive()
             && $currentTurn->status === TurnStatus::Completed
             && $currentTurn->reports_locked_at === null,
     ]
     ```

3. **Enrich `members` payload** — add `has_report` to the empire object:
   - Query `turn_reports` for the current turn, keyed by `empire_id`.
   - When an empire exists in the member data, include `'has_report' => $reportsByEmpireId->has($empire->id)`.

4. **Update `types.ts`** — add these types:
   ```typescript
   export type ReportTurn = {
       id: number;
       number: number;
       status: string;
       reports_locked_at: string | null;
       can_generate: boolean;
       can_lock: boolean;
   };
   ```
   And extend `MemberItem.empire` to include `has_report: boolean`.

5. **Write focused prop tests** in `tests/Feature/GameGenerationReportPropsTest.php`:
   - Generate page returns `reportTurn: null` when game has no turn.
   - Active game with pending Turn 0 returns `reportTurn` with `can_generate: true`, `can_lock: false`.
   - Active game with completed Turn 0 returns `can_generate: true`, `can_lock: true`.
   - Active game with closed Turn 0 returns `can_generate: false`, `can_lock: false`.
   - Member with empire and generated report has `has_report: true`.
   - Member with empire but no report has `has_report: false`.

**Test commands:**
```bash
php artisan test --compact tests/Feature/GameGenerationReportPropsTest.php
bun run build
```

**Acceptance criteria:**
- [ ] `reportTurn` prop is returned from the generate page endpoint
- [ ] `members[*].empire.has_report` is available when an empire exists
- [ ] `types.ts` is updated with `ReportTurn` type and `has_report` on `MemberItem.empire`
- [ ] Prop tests pass
- [ ] TypeScript compiles without errors

---

### I.3 — Build the GM Turn Reports section component

**Build plan ref:** Task #37  
**Effort:** M  
**Dependencies:** I.2

**Goal:** Add a Turn Reports section to the generate page with Generate Reports / Lock Reports buttons and per-empire report links.

**Files to create:**
- `resources/js/pages/games/generate/TurnReportsSection.tsx`

**Files to modify:**
- `resources/js/pages/games/generate.tsx` (add the new section after `EmpiresSection`)

**Instructions:**

1. **Create `TurnReportsSection.tsx`** following the same patterns as `EmpiresSection.tsx`:
   - Accept props: `game: Game`, `reportTurn: ReportTurn | null`, `members: MemberItem[]`
   - If `reportTurn` is `null`, render "Not yet available." (same pattern as other sections)
   - Show turn status and lock state in a summary line

2. **Generate Reports button:**
   - Use `useForm({})` for the POST
   - Post to `TurnReportController.generate.url({ game, turn: reportTurn })` via Wayfinder
   - Disable when `!reportTurn.can_generate` or form is processing
   - Show validation errors from `game` and `turn` error keys

3. **Lock Reports button:**
   - Use a separate `useForm({})` for the POST
   - Post to `TurnReportController.lock.url({ game, turn: reportTurn })` via Wayfinder
   - Disable when `!reportTurn.can_lock` or form is processing
   - Use destructive variant since locking is irreversible

4. **Empire report table:**
   - Show only members who have empires
   - Columns: Player, Empire, Report Status, Actions
   - Report Status: "Generated" badge when `has_report`, "Pending" otherwise
   - Actions column when `has_report`:
     - "View report" — plain `<a>` link to `TurnReportController.show.url({ game, turn: reportTurn, empire: member.empire })`, opens in new tab (`target="_blank"`)
     - "Download JSON" — plain `<a>` link to `TurnReportController.download.url({ game, turn: reportTurn, empire: member.empire })`, download attribute
   - Reason for plain anchors: `show` returns Blade HTML, `download` returns JSON attachment — neither is an Inertia response

5. **Wire into `generate.tsx`:**
   - Import and render `TurnReportsSection` after `EmpiresSection`
   - Pass `reportTurn` and `members` props
   - Add `reportTurn` to the destructured props with type `ReportTurn | null`

**Test commands:**
```bash
bun run build
```

**Manual smoke test:**
1. As GM, visit `/games/{id}/generate` for an active game with Turn 0
2. Confirm Turn Reports section appears after Empires section
3. Click "Generate Reports" — verify redirect with success flash
4. Confirm "View report" and "Download JSON" links appear for empires with reports
5. Click "Lock Reports" — verify redirect with success flash
6. Confirm buttons disable after lock

**Acceptance criteria:**
- [ ] `TurnReportsSection.tsx` exists and follows existing section component patterns
- [ ] `generate.tsx` renders the new section after `EmpiresSection`
- [ ] Generate Reports button posts to correct Wayfinder route with error display
- [ ] Lock Reports button posts to correct Wayfinder route with destructive styling
- [ ] Empire table shows report status and links for empires with reports
- [ ] Report links use plain `<a>` tags (not `<Link>`) since targets are non-Inertia
- [ ] TypeScript compiles without errors
- [ ] ESLint passes

---

### I.4 — Add player-side setup report access on the game show page

**Build plan ref:** Task #37  
**Effort:** M  
**Dependencies:** I.1

**Goal:** Give players a non-GM entry point to view and download their own setup report from the game show page.

**Files to modify:**
- `app/Http/Controllers/GameController.php`
- `resources/js/pages/games/show.tsx`

**Files to create:**
- `tests/Feature/GameShowSetupReportTest.php`

**Instructions:**

1. **Add `setupReport` prop to `GameController::show()`:**
   ```php
   'setupReport' => $this->setupReportPayload($game, $request->user()),
   ```

2. **Implement `setupReportPayload()` private method:**
   - Find the authenticated user's player record for this game.
   - Find the empire associated with that player record.
   - If no empire exists, return `null`.
   - Load the game's current turn.
   - If no current turn exists, return `null`.
   - Check if a `turn_reports` row exists for this `(turn_id, empire_id)`.
   - Return:
     ```php
     [
         'turn_id' => $currentTurn->id,
         'turn_number' => $currentTurn->number,
         'empire_id' => $empire->id,
         'empire_name' => $empire->name,
         'available' => $hasReport,
     ]
     ```
   - GMs/admins viewing the page but without their own empire should get `null`.

3. **Update `games/show.tsx`:**
   - Add a "Setup Report" section that renders only when `setupReport` is not null.
   - If `setupReport.available === false`: show "Setup report has not been generated yet."
   - If `setupReport.available === true`: show:
     - "View setup report" — plain `<a>` to `TurnReportController.show.url(...)`, new tab
     - "Download JSON" — plain `<a>` to `TurnReportController.download.url(...)`, download
   - Position this section above or below the Members section.
   - Use Wayfinder imports for URL construction but plain anchor tags for the links.

4. **Write focused tests** in `tests/Feature/GameShowSetupReportTest.php`:
   - Player with own empire and existing report gets `setupReport.available === true`.
   - Player with own empire but no report gets `setupReport.available === false`.
   - Player without empire gets `setupReport === null`.
   - GM viewer (no player empire) gets `setupReport === null`.
   - Non-active game returns `setupReport === null`.

**Test commands:**
```bash
php artisan test --compact tests/Feature/GameShowSetupReportTest.php
bun run build
```

**Acceptance criteria:**
- [ ] `GameController::show()` returns a `setupReport` prop
- [ ] Prop is `null` for GMs/admins without their own empire, and for players without empires
- [ ] Prop has `available: true` when a turn report exists for the player's empire
- [ ] `games/show.tsx` renders a setup report card when `setupReport` is present
- [ ] Links use plain anchors to Blade report view and JSON download
- [ ] Focused prop tests pass
- [ ] TypeScript compiles without errors

---

### I.5 — Final integration verification

**Build plan ref:** Wrap-up for Tasks #34–37  
**Effort:** S  
**Dependencies:** H.1–H.6, I.1–I.4

**Goal:** Run the full verification suite to confirm everything works end-to-end.

**Instructions:**
1. Run all targeted PHPUnit suites.
2. Run TypeScript and ESLint checks.
3. Run Pint on modified PHP files.
4. Perform manual smoke tests.

**Test commands:**
```bash
# All report-related PHP tests
php artisan test --compact tests/Feature/Reports
php artisan test --compact tests/Feature/Services/SetupReportGeneratorTest.php
php artisan test --compact tests/Feature/TurnReports
php artisan test --compact tests/Feature/Models/TurnModelTest.php
php artisan test --compact tests/Feature/Models/GameTurnRelationshipTest.php

# All legacy suites that were fixed
php artisan test --compact tests/Feature/EmpireCreatorTest.php
php artisan test --compact tests/Feature/GameGenerationControllerTest.php
php artisan test --compact tests/Feature/UploadColonyTemplateValidationTest.php
php artisan test --compact tests/Feature/GameGenerationControllerActivateTest.php
php artisan test --compact tests/Feature/Models/TemplateTest.php

# New prop tests
php artisan test --compact tests/Feature/GameGenerationReportPropsTest.php
php artisan test --compact tests/Feature/GameShowSetupReportTest.php

# Frontend checks
bun run build

# PHP formatting
vendor/bin/pint --dirty --format agent
```

**Manual smoke — GM flow:**
1. Visit game generate page for an active game with Turn 0
2. Assign an empire to a player
3. Click "Generate Reports" → success flash
4. Open Blade text report via "View report" link → renders correctly
5. Download JSON via "Download JSON" link → correct filename and structure
6. Click "Lock Reports" → success flash, buttons disable

**Manual smoke — Player flow:**
1. Log in as a player with an assigned empire
2. Visit `/games/{id}` → see "Setup Report" card
3. Click "View setup report" → Blade report opens in new tab
4. Click "Download JSON" → JSON file downloads
5. Confirm another player's report is not accessible (403)

**Acceptance criteria:**
- [ ] All targeted PHPUnit suites pass
- [ ] TypeScript compiles without errors
- [ ] ESLint passes
- [ ] Pint reports no formatting issues
- [ ] GM end-to-end report workflow verified
- [ ] Player self-service report access verified

---

## Execution Order

Tasks should be completed in this order. Tasks at the same level can be parallelized.

```
H.1  (audit)
 ├── H.2  (model test fixes)
 ├── H.3  (upload/generation test fixes)
 │    └── H.4  (empire/activate regression fixes)
 │         └── H.5  (generator lifecycle tests)
 └── H.6  (controller test gaps)      ← parallel with H.5
I.1  (wayfinder generate)             ← parallel with H.*
 ├── I.2  (generate page props)
 │    └── I.3  (TurnReportsSection)
 └── I.4  (player show page)          ← parallel with I.2/I.3
I.5  (final verification)             ← after all H.* and I.*
```
