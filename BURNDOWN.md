# Burndown — Layer 1, Group G: Routes, Authorization, and TurnReportController

## Overview

Group G wires setup reports into the application surface area: **backend routes**, a dedicated **`TurnReportPolicy`**, and the **`TurnReportController`** actions for generating, locking, viewing, and downloading Turn 0 reports.

**Design reference:** `docs/SETUP_REPORT.md` — Group G, tasks #31–33.

**Prerequisite groups:** A (enums, schema migrations), B (model updates), C (new models/factories), D (template ingestion), E (business logic extensions), F (report schema and service) — all complete.

**Out of scope (Group H):** Test fixes for earlier schema changes.
**Out of scope (Group I):** Wayfinder generation, React/Inertia report viewer, GM frontend buttons/forms.

**Scope guardrails:**
- Keep using the repo's existing `Gate::authorize()` controller pattern (see `GameController`, `GameMemberController`).
- Create a **dedicated `TurnReportPolicy`**; do **not** push report-view rules into `GamePolicy`.
- `show` / `download` must read from **snapshot report tables only**, not live colony/planet/gameplay state.
- Use a plain **Blade view** for browser rendering in `show`; React/Inertia page is Group I scope.
- Use **scoped bindings for `Turn` under `Game`** in routes. Since `Empire` is a sibling child of `Game` (not nested under `Turn`), add an explicit `abort_unless($empire->game_id === $game->id, 404)` guard in `show` / `download`.
- This layer is limited to **Turn 0 setup reports**.

**Route shape:**
- `POST /games/{game}/turns/{turn}/reports/generate`
- `POST /games/{game}/turns/{turn}/reports/lock`
- `GET  /games/{game}/turns/{turn}/reports/empires/{empire}`
- `GET  /games/{game}/turns/{turn}/reports/empires/{empire}/download`

---

## Task G1 — Policy: `TurnReportPolicy`

**Status:** [x] Complete

**Design task:** #32

**Files to create:**
- `app/Policies/TurnReportPolicy.php` (use `php artisan make:policy TurnReportPolicy --no-interaction`)
- `tests/Feature/TurnReports/TurnReportPolicyTest.php` (use `php artisan make:test TurnReports/TurnReportPolicyTest --phpunit --no-interaction`)

**Implementation details:**

Create a dedicated policy with four public methods. These are **not** standard CRUD policy methods — they are custom ability names that will be called with `Gate::authorize('generate', [TurnReport::class, $game])`:

```php
namespace App\Policies;

use App\Models\Empire;
use App\Models\Game;
use App\Models\User;

class TurnReportPolicy
{
    public function generate(User $user, Game $game): bool
    public function lock(User $user, Game $game): bool
    public function show(User $user, Game $game, Empire $empire): bool
    public function download(User $user, Game $game, Empire $empire): bool
}
```

Authorization rules:

1. **`generate`** and **`lock`** — GM-only actions:
   - Allow if `$user->isAdmin()` returns `true`
   - Allow if `$user->isGmOf($game)` returns `true`
   - Deny everyone else

2. **`show`** and **`download`** — GM or own-empire player:
   - Allow if `$user->isAdmin()` returns `true`
   - Allow if `$user->isGmOf($game)` returns `true`
   - Allow if `$user->isPlayerOf($game)` returns `true` **and** `$empire->player?->user_id === $user->id`
   - Deny everyone else

3. Extract a private helper to avoid duplication:
   ```php
   private function canViewEmpireReport(User $user, Game $game, Empire $empire): bool
   ```

**Implementation notes:**
- Laravel policy auto-discovery maps `TurnReport` → `TurnReportPolicy` automatically. No manual registration needed.
- The `User` model already has `isAdmin()`, `isGmOf(Game)`, and `isPlayerOf(Game)` methods — use them.
- The `Empire` model has `player()` → `BelongsTo<Player>`, and `Player` has `user_id`. Use `$empire->player?->user_id` for ownership check.
- The policy does **not** check game/turn state (active, locked, etc.) — that is the controller's responsibility.

**Tests:**

Create `tests/Feature/TurnReports/TurnReportPolicyTest.php` with `LazilyRefreshDatabase`, `#[Test]` attributes.

Setup helper:
- Create a game with GM user, player user, and an empire belonging to the player
- Create a second player user with a different empire (for cross-player denial tests)
- Create a non-member user

Tests:
- `test_generate_allows_admin` — admin user, assert `Gate::allows('generate', [TurnReport::class, $game])` is `true`.
- `test_generate_allows_gm_of_game` — GM user, assert allowed.
- `test_generate_denies_player_of_game` — player user, assert denied.
- `test_generate_denies_non_member` — non-member user, assert denied.
- `test_lock_allows_gm_and_denies_player` — verify lock follows same rules as generate.
- `test_show_allows_gm_to_view_any_empire_in_game` — GM user with any empire, assert allowed.
- `test_show_allows_player_to_view_their_own_empire` — player user with their own empire, assert allowed.
- `test_show_denies_player_from_viewing_another_players_empire` — player user with a different player's empire, assert denied.
- `test_show_denies_non_member` — non-member user, assert denied.
- `test_download_matches_show_permissions` — verify download follows same rules as show (GM allowed for any, player only own, non-member denied).

**Acceptance criteria:**
- [x] `TurnReportPolicy` exists at `app/Policies/TurnReportPolicy.php`
- [x] `generate` and `lock` are GM/admin only
- [x] `show` and `download` allow GM/admin for any empire
- [x] `show` and `download` allow players only for their own empire
- [x] Non-members are denied for all four abilities
- [x] No report-view logic is added to `GamePolicy`
- [x] Tests pass: `php artisan test --compact --filter=TurnReportPolicyTest`

---

## Task G2 — Routes and controller action: `generate`

**Status:** [ ] Not started

**Design tasks:** #31, #33

**Files to create:**
- `app/Http/Controllers/TurnReportController.php` (use `php artisan make:controller TurnReportController --no-interaction`)
- `tests/Feature/TurnReports/TurnReportControllerGenerateTest.php` (use `php artisan make:test TurnReports/TurnReportControllerGenerateTest --phpunit --no-interaction`)

**Files to modify:**
- `routes/games.php` — add a scoped route group for turn reports

**Route registration:**

Add inside the existing `Route::middleware(['auth', 'verified'])->prefix('games')->name('games.')` group in `routes/games.php`:

```php
Route::prefix('{game}/turns/{turn}/reports')->name('turns.reports.')
    ->scopeBindings()
    ->group(function () {
        Route::post('generate', [TurnReportController::class, 'generate'])->name('generate');
        // lock, show, download routes added in subsequent tasks
    });
```

**Note:** The `scopeBindings()` call ensures `{turn}` is scoped to `{game}` via the `Turn::game()` relationship. All four routes share this group — later tasks G3–G5 add their routes here.

**Controller implementation:**

```php
namespace App\Http\Controllers;

use App\Models\Game;
use App\Models\Turn;
use App\Models\TurnReport;
use App\Services\SetupReportGenerator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class TurnReportController extends Controller
{
    public function generate(Game $game, Turn $turn, SetupReportGenerator $generator): RedirectResponse
    {
        Gate::authorize('generate', [TurnReport::class, $game]);

        if (! $game->isActive()) {
            throw ValidationException::withMessages([
                'game' => 'The game must be active to generate reports.',
            ]);
        }

        if ($turn->number !== 0) {
            throw ValidationException::withMessages([
                'turn' => 'Only Turn 0 setup reports can be generated in this version.',
            ]);
        }

        try {
            $count = $generator->generate($turn);
        } catch (\RuntimeException $e) {
            throw ValidationException::withMessages([
                'turn' => $e->getMessage(),
            ]);
        }

        return back()->with('success', "Generated {$count} setup report(s).");
    }
}
```

**Implementation notes:**
- Keep the action thin — do not duplicate generator logic in the controller.
- Follow the existing `GameGenerationController::activate()` pattern: `Gate::authorize()`, validate, call service, redirect back with flash.
- The `SetupReportGenerator` is injected via Laravel's service container (method injection).
- The `RuntimeException` from the generator (e.g., turn already generating/closed/locked) is converted to a `ValidationException` so the user gets a clean session error, not a 500.

**Tests:**

Create `tests/Feature/TurnReports/TurnReportControllerGenerateTest.php` with `LazilyRefreshDatabase`, `#[Test]` attributes.

Setup helpers (follow the `GameGenerationControllerActivateTest` pattern):

```php
private function gmUser(Game $game): User
{
    $user = User::factory()->create();
    $game->users()->attach($user, ['role' => 'gm', 'is_active' => true]);
    return $user;
}

private function activeGameWithTurnZero(): Game
{
    // Create active game with Turn 0 (pending) using existing setup helpers
    // or use the SetupReportGeneratorTest::activeGameWithEmpire() pattern
}
```

Tests:
- `test_generate_calls_service_and_redirects_with_success_count` — active game + Turn 0 + GM user. Post to generate route. Assert redirect. Assert session has `success` containing the count.
- `test_generate_is_forbidden_for_non_gm` — authenticated non-member user gets `403`.
- `test_generate_is_forbidden_for_player` — player user gets `403`.
- `test_generate_returns_404_when_turn_belongs_to_another_game` — create a Turn for a different game, use its ID in the route. Assert `404`.
- `test_generate_rejects_inactive_game` — game status is `Setup`. Assert session error on `game`.
- `test_generate_rejects_non_zero_turn` — create Turn with `number => 1`. Assert session error on `turn`.
- `test_generate_surfaces_generator_runtime_errors_as_validation_errors` — mock or set turn status to `Generating` so the service throws `RuntimeException`. Assert session error on `turn`.

**Acceptance criteria:**
- [ ] Route `games.turns.reports.generate` exists as `POST /games/{game}/turns/{turn}/reports/generate`
- [ ] Route uses scoped bindings so `{turn}` is scoped to `{game}`
- [ ] Controller uses `Gate::authorize()` with `TurnReportPolicy::generate`
- [ ] Rejects inactive games with validation error
- [ ] Rejects non-zero turns with validation error
- [ ] Calls `SetupReportGenerator::generate($turn)` on valid input
- [ ] Service `RuntimeException` is converted to validation error, not 500
- [ ] Success redirect includes the generated empire count in flash
- [ ] Tests pass: `php artisan test --compact --filter=TurnReportControllerGenerateTest`

---

## Task G3 — Controller action: `lock`

**Status:** [ ] Not started

**Design tasks:** #31, #33

**Files to create:**
- `tests/Feature/TurnReports/TurnReportControllerLockTest.php` (use `php artisan make:test TurnReports/TurnReportControllerLockTest --phpunit --no-interaction`)

**Files to modify:**
- `routes/games.php` — add lock route to the existing turn-reports route group
- `app/Http/Controllers/TurnReportController.php` — add `lock` method

**Route registration:**

Add to the existing turn-reports scoped route group in `routes/games.php`:

```php
Route::post('lock', [TurnReportController::class, 'lock'])->name('lock');
```

**Controller implementation:**

```php
public function lock(Game $game, Turn $turn): RedirectResponse
{
    Gate::authorize('lock', [TurnReport::class, $game]);

    if (! $game->isActive()) {
        throw ValidationException::withMessages([
            'game' => 'The game must be active to lock reports.',
        ]);
    }

    if ($turn->number !== 0) {
        throw ValidationException::withMessages([
            'turn' => 'Only Turn 0 can be locked in this version.',
        ]);
    }

    $updated = Turn::where('id', $turn->id)
        ->whereNull('reports_locked_at')
        ->whereNotIn('status', [TurnStatus::Closed, TurnStatus::Generating])
        ->update([
            'reports_locked_at' => now(),
            'status' => TurnStatus::Closed,
        ]);

    if ($updated === 0) {
        throw ValidationException::withMessages([
            'turn' => 'Turn cannot be locked (already locked, closed, or currently generating).',
        ]);
    }

    return back()->with('success', 'Turn reports locked.');
}
```

**Implementation notes:**
- Uses the same atomic guarded update pattern as `SetupReportGenerator` to avoid race conditions.
- After locking, `$game->fresh()->canGenerateReports()` should return `false`.
- Do not call the generator here — lock only sets timestamps and status.
- Import `TurnStatus` and `ValidationException` in the controller.

**Tests:**

Create `tests/Feature/TurnReports/TurnReportControllerLockTest.php` with `LazilyRefreshDatabase`, `#[Test]` attributes.

Reuse the `gmUser()` and `activeGameWithTurnZero()` helper pattern from G2.

Tests:
- `test_lock_sets_reports_locked_at_and_closes_turn` — active game + completed Turn 0 + GM user. Post to lock route. Assert `reports_locked_at` is set (not null). Assert `status` is `closed`.
- `test_lock_is_forbidden_for_non_gm` — player user gets `403`.
- `test_lock_is_forbidden_for_non_member` — non-member user gets `403`.
- `test_lock_returns_404_when_turn_belongs_to_another_game` — assert `404`.
- `test_lock_rejects_inactive_game` — assert session error on `game`.
- `test_lock_rejects_non_zero_turn` — assert session error on `turn`.
- `test_lock_rejects_already_closed_turn` — set status to `Closed`, assert session error on `turn`.
- `test_lock_rejects_already_locked_turn` — set `reports_locked_at`, assert session error on `turn`.
- `test_lock_rejects_generating_turn` — set status to `Generating`, assert session error on `turn`.
- `test_lock_disables_future_report_generation` — after lock, assert `$game->fresh()->canGenerateReports()` is `false`.

**Acceptance criteria:**
- [ ] Route `games.turns.reports.lock` exists as `POST /games/{game}/turns/{turn}/reports/lock`
- [ ] Controller uses `Gate::authorize()` with `TurnReportPolicy::lock`
- [ ] Lock sets `reports_locked_at` to a non-null datetime and `status` to `closed`
- [ ] Rejects inactive games with validation error
- [ ] Rejects non-zero turns with validation error
- [ ] Rejects already locked, closed, or generating turns with validation error
- [ ] Uses atomic guarded update (not load-then-check)
- [ ] `Game::canGenerateReports()` returns `false` after successful lock
- [ ] Success redirect includes flash message
- [ ] Tests pass: `php artisan test --compact --filter=TurnReportControllerLockTest`

---

## Task G4 — Controller action: `show` with Blade view

**Status:** [ ] Not started

**Design tasks:** #31, #33

**Files to create:**
- `resources/views/turn-reports/show.blade.php`
- `tests/Feature/TurnReports/TurnReportControllerShowTest.php` (use `php artisan make:test TurnReports/TurnReportControllerShowTest --phpunit --no-interaction`)

**Files to modify:**
- `routes/games.php` — add show route to the existing turn-reports route group
- `app/Http/Controllers/TurnReportController.php` — add `show` method

**Route registration:**

Add to the existing turn-reports scoped route group in `routes/games.php`:

```php
Route::get('empires/{empire}', [TurnReportController::class, 'show'])->name('show');
```

**Controller implementation:**

```php
public function show(Game $game, Turn $turn, Empire $empire)
{
    abort_unless($empire->game_id === $game->id, 404);

    Gate::authorize('show', [TurnReport::class, $game, $empire]);

    $report = $this->loadReport($game, $turn, $empire);

    return view('turn-reports.show', [
        'game' => $game,
        'turn' => $turn,
        'empire' => $empire,
        'report' => $report,
    ]);
}

private function loadReport(Game $game, Turn $turn, Empire $empire): TurnReport
{
    return TurnReport::query()
        ->where('game_id', $game->id)
        ->where('turn_id', $turn->id)
        ->where('empire_id', $empire->id)
        ->with([
            'colonies' => fn ($q) => $q->orderBy('id'),
            'colonies.inventory' => fn ($q) => $q->orderBy('id'),
            'colonies.population' => fn ($q) => $q->orderBy('id'),
            'surveys' => fn ($q) => $q->orderBy('id'),
            'surveys.deposits' => fn ($q) => $q->orderBy('deposit_no'),
        ])
        ->firstOrFail();
}
```

**Blade view implementation:**

Create `resources/views/turn-reports/show.blade.php` — a minimal, readable text-style report rendered in the browser. Structure it after the original `turn-report.txt` section layout:

- Game header: game name, turn number, empire name, generated timestamp
- For each colony snapshot:
  - Colony header: name, kind, tech level, location (star coords, orbit, surface/orbital)
  - Colony vitals: rations, SOL, birth rate, death rate
  - Population section: table of population classes with quantity, pay rate, rebel quantity
  - Inventory/Storage section: table of unit codes with tech level, assembled, disassembled quantities
- For each survey:
  - Planet header: orbit, type, habitability, location
  - Deposits table: deposit number, resource, yield %, quantity remaining

Use a `<pre>` or monospaced `font-family: monospace` styling for readability. Keep it simple — no Tailwind required, this is a plain Blade view that Group I may replace with an Inertia page later.

**Implementation notes:**
- The `loadReport()` private method is shared between `show` and `download` (Task G5).
- Guard sibling-resource mismatch with `abort_unless($empire->game_id === $game->id, 404)` **before** authorization to avoid leaking empire existence across games.
- Report data comes exclusively from snapshot tables (`turn_reports`, `turn_report_colonies`, etc.) — no live `Colony`, `Planet`, or `Deposit` queries.
- A missing `TurnReport` row returns `404` via `firstOrFail()`.
- Do **not** create a React/Inertia page — that is Group I scope.

**Tests:**

Create `tests/Feature/TurnReports/TurnReportControllerShowTest.php` with `LazilyRefreshDatabase`, `#[Test]` attributes.

Setup: Create report data using factories (`TurnReportFactory`, `TurnReportColonyFactory`, etc.) so tests are independent of the `SetupReportGenerator` service. This proves the view reads from snapshot tables, not live data.

Tests:
- `test_show_allows_gm_to_view_any_empire_report` — GM user, GET show route for any empire. Assert `200`. Assert response contains the colony snapshot name.
- `test_show_allows_player_to_view_their_own_empire_report` — player user, GET show route for their own empire. Assert `200`.
- `test_show_forbids_player_from_viewing_another_empire_report` — player user, GET show route for a different player's empire. Assert `403`.
- `test_show_forbids_non_member` — non-member user. Assert `403`.
- `test_show_returns_404_when_empire_belongs_to_another_game` — empire from a different game. Assert `404`.
- `test_show_returns_404_when_report_does_not_exist` — valid game/turn/empire but no `TurnReport` row. Assert `404`.
- `test_show_renders_snapshot_data` — create a `TurnReport` with colony, inventory, population, survey, and deposit snapshots via factories. Assert the response body contains key values from the snapshots (colony name, unit code value, population code value, deposit resource).

**Acceptance criteria:**
- [ ] Route `games.turns.reports.show` exists as `GET /games/{game}/turns/{turn}/reports/empires/{empire}`
- [ ] Controller returns `404` if the empire does not belong to the game
- [ ] Controller authorizes via `TurnReportPolicy::show` (GM any empire, player own only)
- [ ] Report is loaded from snapshot tables by `(game_id, turn_id, empire_id)`
- [ ] Missing reports return `404`
- [ ] Browser response renders a readable text-style report in Blade
- [ ] Report content comes from snapshot rows, not live gameplay tables
- [ ] Tests pass: `php artisan test --compact --filter=TurnReportControllerShowTest`

---

## Task G5 — Controller action: `download`

**Status:** [ ] Not started

**Design tasks:** #31, #33

**Files to create:**
- `tests/Feature/TurnReports/TurnReportControllerDownloadTest.php` (use `php artisan make:test TurnReports/TurnReportControllerDownloadTest --phpunit --no-interaction`)

**Files to modify:**
- `routes/games.php` — add download route to the existing turn-reports route group
- `app/Http/Controllers/TurnReportController.php` — add `download` method

**Route registration:**

Add to the existing turn-reports scoped route group in `routes/games.php`:

```php
Route::get('empires/{empire}/download', [TurnReportController::class, 'download'])->name('download');
```

**Controller implementation:**

```php
public function download(Game $game, Turn $turn, Empire $empire): \Symfony\Component\HttpFoundation\Response
{
    abort_unless($empire->game_id === $game->id, 404);

    Gate::authorize('download', [TurnReport::class, $game, $empire]);

    $report = $this->loadReport($game, $turn, $empire);

    $data = [
        'game' => [
            'id' => $game->id,
            'name' => $game->name,
        ],
        'turn' => [
            'id' => $turn->id,
            'number' => $turn->number,
            'status' => $turn->status->value,
            'reports_locked_at' => $turn->reports_locked_at?->toIso8601String(),
        ],
        'empire' => [
            'id' => $empire->id,
            'name' => $empire->name,
        ],
        'generated_at' => $report->generated_at->toIso8601String(),
        'colonies' => $report->colonies->map(fn ($colony) => [
            'id' => $colony->id,
            'source_colony_id' => $colony->source_colony_id,
            'name' => $colony->name,
            'kind' => $colony->kind->value,
            'tech_level' => $colony->tech_level,
            'planet_id' => $colony->planet_id,
            'orbit' => $colony->orbit,
            'star_x' => $colony->star_x,
            'star_y' => $colony->star_y,
            'star_z' => $colony->star_z,
            'star_sequence' => $colony->star_sequence,
            'is_on_surface' => $colony->is_on_surface,
            'rations' => $colony->rations,
            'sol' => $colony->sol,
            'birth_rate' => $colony->birth_rate,
            'death_rate' => $colony->death_rate,
            'inventory' => $colony->inventory->map(fn ($item) => [
                'unit_code' => $item->unit_code->value,
                'tech_level' => $item->tech_level,
                'quantity_assembled' => $item->quantity_assembled,
                'quantity_disassembled' => $item->quantity_disassembled,
            ])->values(),
            'population' => $colony->population->map(fn ($pop) => [
                'population_code' => $pop->population_code->value,
                'quantity' => $pop->quantity,
                'pay_rate' => $pop->pay_rate,
                'rebel_quantity' => $pop->rebel_quantity,
            ])->values(),
        ])->values(),
        'surveys' => $report->surveys->map(fn ($survey) => [
            'id' => $survey->id,
            'planet_id' => $survey->planet_id,
            'orbit' => $survey->orbit,
            'star_x' => $survey->star_x,
            'star_y' => $survey->star_y,
            'star_z' => $survey->star_z,
            'star_sequence' => $survey->star_sequence,
            'planet_type' => $survey->planet_type->value,
            'habitability' => $survey->habitability,
            'deposits' => $survey->deposits->map(fn ($dep) => [
                'deposit_no' => $dep->deposit_no,
                'resource' => $dep->resource->value,
                'yield_pct' => $dep->yield_pct,
                'quantity_remaining' => $dep->quantity_remaining,
            ])->values(),
        ])->values(),
    ];

    $filename = "report-{$game->id}-turn-{$turn->number}-empire-{$empire->id}.json";
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

    return response($json, 200, [
        'Content-Type' => 'application/json',
        'Content-Disposition' => 'attachment; filename="' . $filename . '"',
    ]);
}
```

**Implementation notes:**
- Follow the existing download pattern in `GameGenerationController::download()` — `json_encode` with `JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE`, `Content-Disposition: attachment`.
- Reuse the `loadReport()` private method added in Task G4 for the same snapshot query.
- Enum values are serialized with `->value` in the JSON payload for clean output.
- Field names match the snapshot table columns to minimize transformation overhead.
- Missing report returns `404` via `firstOrFail()`.

**Tests:**

Create `tests/Feature/TurnReports/TurnReportControllerDownloadTest.php` with `LazilyRefreshDatabase`, `#[Test]` attributes.

Setup: Use factories to create report data, same as Task G4 tests.

Tests:
- `test_download_allows_gm_to_download_any_empire_report` — GM user, GET download route. Assert `200`. Assert `Content-Type` is `application/json`. Assert `Content-Disposition` contains `attachment`.
- `test_download_allows_player_to_download_their_own_empire_report` — player user with their own empire. Assert `200`.
- `test_download_forbids_player_from_downloading_another_empire_report` — player user with a different player's empire. Assert `403`.
- `test_download_forbids_non_member` — non-member user. Assert `403`.
- `test_download_returns_404_when_empire_belongs_to_another_game` — assert `404`.
- `test_download_returns_404_when_report_does_not_exist` — assert `404`.
- `test_download_returns_json_attachment_with_expected_filename` — assert `Content-Disposition` header contains `report-{game_id}-turn-0-empire-{empire_id}.json`.
- `test_download_payload_contains_snapshot_data` — create a full report with colony, inventory, population, survey, and deposits via factories. Decode JSON response. Assert `colonies` array is not empty. Assert first colony has `name`, `kind`, nested `inventory`, and nested `population`. Assert `surveys` array is not empty. Assert first survey has `deposits` array.

**Acceptance criteria:**
- [ ] Route `games.turns.reports.download` exists as `GET /games/{game}/turns/{turn}/reports/empires/{empire}/download`
- [ ] Authorization matches `show` (GM any empire, player own only, non-member denied)
- [ ] Controller returns `404` for cross-game empire mismatches and missing reports
- [ ] Response is a JSON attachment with `Content-Type: application/json` and `Content-Disposition: attachment`
- [ ] Filename exactly matches `report-{game_id}-turn-{number}-empire-{empire_id}.json`
- [ ] JSON payload contains structured snapshot data: colonies (with nested inventory/population), surveys (with nested deposits)
- [ ] All enum fields are serialized as string values
- [ ] Tests pass: `php artisan test --compact --filter=TurnReportControllerDownloadTest`

---

## Task G6 — Formatting and full test suite verification

**Status:** [ ] Not started

**Steps:**

1. Run Pint on all modified/created PHP files:
   ```bash
   vendor/bin/pint --dirty --format agent
   ```

2. Run all Group G tests:
   ```bash
   php artisan test --compact --filter=TurnReportPolicyTest
   php artisan test --compact --filter=TurnReportControllerGenerateTest
   php artisan test --compact --filter=TurnReportControllerLockTest
   php artisan test --compact --filter=TurnReportControllerShowTest
   php artisan test --compact --filter=TurnReportControllerDownloadTest
   ```

3. Run related pre-existing report tests for regression coverage:
   ```bash
   php artisan test --compact --filter=SetupReportGeneratorTest
   php artisan test --compact --filter=TurnReportSchemaTest
   php artisan test --compact --filter=TurnReportModelTest
   php artisan test --compact --filter=TurnReportFactoryTest
   ```

4. Run the full test suite:
   ```bash
   php artisan test --compact
   ```

5. Final route verification:
   ```bash
   php artisan route:list --name=turns.reports
   ```
   Confirm all four routes exist with correct methods, URIs, names, and scoped bindings.

**Acceptance criteria:**
- [ ] No Pint violations
- [ ] All Group G tests pass
- [ ] Existing report tests (Groups F, schema, models, factories) still pass
- [ ] Full test suite passes (no regressions from Groups A–F)
- [ ] Route list shows four report routes under the games group

---

## Group G Acceptance Criteria

Group G is complete when **all** of the following are true:

- [ ] **1. Routes:** Four backend routes exist under the existing `games` route group: `generate` (POST), `lock` (POST), `show` (GET), `download` (GET).

- [ ] **2. Scoped bindings:** `Turn` route model binding is scoped under `Game` via `scopeBindings()`. `Empire` game membership is verified with an explicit `abort_unless` guard.

- [ ] **3. Dedicated policy:** A `TurnReportPolicy` controls report access. No report-view logic is added to `GamePolicy`.

- [ ] **4. Generate action:** GM/admin-only. Validates game is active and turn is 0. Calls `SetupReportGenerator::generate()`. Redirects with success count. Service `RuntimeException` is surfaced as a validation error, not a 500.

- [ ] **5. Lock action:** GM/admin-only. Validates game is active and turn is 0. Atomically sets `reports_locked_at` and `status = closed`. Rejects already locked/closed/generating turns. `Game::canGenerateReports()` returns `false` after lock.

- [ ] **6. Show action:** GM can view any empire's report. Players can view only their own empire's report. Non-members are denied. Renders a browser-readable text report from a Blade view. Report data comes from snapshot tables, not live game state.

- [ ] **7. Download action:** Same authorization as `show`. Returns a JSON attachment with filename `report-{game_id}-turn-{number}-empire-{empire_id}.json`. Payload contains structured snapshot data with colonies (nested inventory/population), surveys (nested deposits), and enum values serialized as strings.

- [ ] **8. 404 handling:** Missing or mismatched resources return `404` — cross-game empire mismatches, missing reports, scoped binding violations.

- [ ] **9. Test style:** All controller tests follow the existing feature-test style: `LazilyRefreshDatabase`, `#[Test]` attributes, direct HTTP requests, session/status assertions.

- [ ] **10. Verification:** Pint clean. All Group G tests pass. All Group F tests pass. Full test suite passes with no regressions.
