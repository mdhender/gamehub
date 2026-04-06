# BURNDOWN

Code quality audit — dependency-ordered task plan.

**Audit date:** 2026-04-05  
**Baseline:** 622 tests passing (3960 assertions), Pint clean, no deprecation warnings.

**Notes:**
- Tasks are ordered by **dependencies**, not severity.
- Tasks in the same phase are parallelizable unless they touch the same file(s).
- Prefer forward-fix migrations over editing historical migrations.
- Do not check a task off until its acceptance criteria pass.
- Run `vendor/bin/pint --dirty --format agent` after modifying any PHP files.

---

## Phase 1 — Route Binding Foundations

These tasks fix broken route model binding and enable scoped bindings so later work can rely on correct routing.

### BD-01 — Add `empires()` relationship to Turn model and `turnReports()` to Empire model

**Severity:** Critical — scoped route binding crashes with "Call to undefined method App\Models\Turn::empires()"  
**Effort:** S  
**Dependencies:** None

**Problem:** Routes at `routes/games.php:27-34` use `->scopeBindings()` on `{game}/turns/{turn}/reports`. The `show` and `download` routes use `->withoutScopedBindings()` and manually check `$empire->game_id === $game->id` in the controller. The `Turn` model lacks an `empires()` relationship, which breaks Laravel's scoped binding resolution.

**Files to modify:**
- `app/Models/Turn.php` — add `empires()` relationship. Since `turn_reports` has both `turn_id` and `empire_id`, this is a `HasManyThrough` via `TurnReport`:
  ```php
  public function empires(): HasManyThrough
  {
      return $this->hasManyThrough(Empire::class, TurnReport::class, 'turn_id', 'id', 'id', 'empire_id');
  }
  ```
- `app/Models/Empire.php` — add `turnReports()` relationship. The `turn_reports` table has `empire_id` FK:
  ```php
  public function turnReports(): HasMany
  {
      return $this->hasMany(TurnReport::class);
  }
  ```
- `routes/games.php:32-33` — remove `->withoutScopedBindings()` from `show` and `download` routes so the group-level `->scopeBindings()` applies naturally
- `app/Http/Controllers/TurnReportController.php:82,98` — remove manual `abort_unless($empire->game_id === $game->id, 404)` from `show()` and `download()` — scoped binding handles this now

**Acceptance:**
- [x] `Turn::empires()` relationship exists and returns `HasManyThrough`
- [x] `Empire::turnReports()` relationship exists and returns `HasMany`
- [x] A valid `{empire}` from another game returns 404 via binding, not controller abort
- [x] `php artisan test --compact tests/Feature/TurnReports/`

---

### BD-02 — Enable scoped bindings on generation sub-routes

**Severity:** Important  
**Effort:** S  
**Dependencies:** None (parallel with BD-01)

**Problem:** Routes at `routes/games.php:36-52` for `{game}/generate/stars/{star}`, `{game}/generate/planets/{planet}`, and `{game}/generate/empires/{empire}` lack `->scopeBindings()`. Controllers manually verify `$star->game_id !== $game->id` etc. instead of using Laravel's built-in scoped bindings.

**Files to modify:**
- `routes/games.php:36` — add `->scopeBindings()` to the `{game}/generate` route group
- `app/Http/Controllers/GameGeneration/StarController.php` — remove manual `abort(404)` for `$star->game_id !== $game->id`
- `app/Http/Controllers/GameGeneration/PlanetController.php` — remove manual `abort(404)` for `$planet->game_id !== $game->id`
- `app/Http/Controllers/GameGeneration/EmpireController.php` — remove manual `abort(404)` for `$empire->game_id !== $game->id` in `reassign()`

**Note:** Keep any manual checks for request-body IDs that are NOT route params (e.g., `player_id`, `home_system_id` in form payloads — those can't be scoped by routing).

**Acceptance:**
- [x] Cross-game nested resource IDs return 404 via binding
- [x] `php artisan test --compact tests/Feature/GameGenerationControllerUpdateStarTest.php tests/Feature/GameGenerationControllerUpdatePlanetTest.php tests/Feature/GameGenerationControllerEmpireTest.php`

---

## Phase 2 — Request and Security Hardening

These tasks harden validation and authorization. They do not depend on Phase 1 and can run in parallel with each other.

### BD-03 — Validate JSON structure in template upload FormRequests

**Severity:** Critical — `Undefined array key "kind"` errors at `TemplateController.php:70`  
**Effort:** M  
**Dependencies:** None

**Problem:** `UploadColonyTemplateRequest` and `UploadHomeSystemTemplateRequest` validate the file upload (MIME type, extension) but NOT the decoded JSON structure. When the JSON is missing expected keys like `kind`, `tech-level`, `population`, `inventory`, the controller crashes with undefined array key errors.

**Files to modify:**
- `app/Http/Requests/UploadColonyTemplateRequest.php` — add validation rules for the decoded JSON structure:
  - Top-level must be an array of objects
  - Each entry must have `kind` (string, in valid enum values), `tech-level` (integer), `population` (required array), `inventory` (required object with `operational`/`stored` arrays)
  - Each population entry must have `population_code`, `quantity`, `pay_rate`
  - Each inventory item must have `unit`, `quantity`
- `app/Http/Requests/UploadHomeSystemTemplateRequest.php` — add validation rules for decoded JSON:
  - Must have `planets` array
  - Each planet must have `orbit`, `type`, `habitability`
  - Each deposit must have `resource`, `yield_pct`, `quantity_remaining`
  - Exactly one planet must have `homeworld: true`
- `app/Http/Controllers/GameGeneration/TemplateController.php:27,63` — use the validated/parsed payload from the FormRequest instead of raw `json_decode(file_get_contents(...))`

**Acceptance:**
- [x] Malformed JSON structure returns 422 validation errors, not PHP undefined array key errors
- [x] `php artisan test --compact tests/Feature/UploadColonyTemplateValidationTest.php tests/Feature/GameGenerationControllerTest.php`

---

### BD-04 — Add `authorize()` to game/generation FormRequests and create `GenerateStarsRequest`

**Severity:** Important  
**Effort:** M  
**Dependencies:** None

**Problem:** 11 FormRequests lack `authorize()` methods, relying solely on controller-level `Gate::authorize()`. `GenerationStepController::generateStars()` uses inline `$request->validate()` at line 30 instead of a FormRequest. `CreateEmpireRequest` is missing `exists:players,id` validation.

**Files to modify:**
- Add `authorize()` methods to these FormRequests (check `GamePolicy` for existing gate definitions):
  - `app/Http/Requests/StoreGameRequest.php` — admin only
  - `app/Http/Requests/UpdateGameRequest.php` — `user()->can('update', $this->route('game'))`
  - `app/Http/Requests/StoreGameMemberRequest.php` — can update game
  - `app/Http/Requests/CreateEmpireRequest.php` — can update game; also add `exists:players,id` rule scoped to current game
  - `app/Http/Requests/ReassignEmpireRequest.php` — can update game
  - `app/Http/Requests/UpdateStarRequest.php` — can update game
  - `app/Http/Requests/UpdatePlanetRequest.php` — can update game
  - `app/Http/Requests/CreateHomeSystemManualRequest.php` — can update game
  - `app/Http/Requests/UploadHomeSystemTemplateRequest.php` — can update game
  - `app/Http/Requests/UploadColonyTemplateRequest.php` — can update game
- Create `app/Http/Requests/GenerateStarsRequest.php` with seed validation rules
- `app/Http/Controllers/GameGeneration/GenerationStepController.php:28-35` — replace inline validation with `GenerateStarsRequest` type-hint

**Acceptance:**
- [x] Unauthorized requests fail with 403 before controller logic
- [x] Unknown `player_id` in CreateEmpireRequest rejected by validation, not `findOrFail()`
- [x] `php artisan test --compact tests/Feature/GameGenerationControllerTest.php tests/Feature/GameGenerationControllerEmpireTest.php tests/Feature/GameGenerationControllerUpdateStarTest.php tests/Feature/GameGenerationControllerUpdatePlanetTest.php`

---

### BD-05 — Require verified email for profile edit/update and add `authorize()` to admin/settings FormRequests

**Severity:** Critical (security)  
**Effort:** S  
**Dependencies:** None

**Problem:** In `routes/settings.php:7-12`, profile edit/update only require `auth` middleware, but profile destroy and security routes require `['auth', 'verified']`. An unverified user can modify their profile. Admin FormRequests also lack `authorize()`.

**Files to modify:**
- `routes/settings.php:7-12` — move profile edit and update routes into the `['auth', 'verified']` middleware group (lines 14-24), or add `verified` to the first group
- `app/Http/Requests/Admin/SendInvitationRequest.php` — add `authorize()` requiring admin
- `app/Http/Requests/Admin/HandleUpdateRequest.php` — verify it has proper `authorize()` (it may already)

**Acceptance:**
- [x] Unverified users cannot access `profile.edit` or `profile.update` routes
- [x] `php artisan test --compact tests/Feature/Settings/ProfileUpdateTest.php`

---

### BD-06 — Add rate limiting to admin and generation mutation routes

**Severity:** Critical (security)  
**Effort:** M  
**Dependencies:** None

**Problem:** POST/PATCH/DELETE routes in `routes/admin.php:11-16` (password reset, invitations) and `routes/games.php:30-31,39-51` (generate, lock, activate, template uploads, star/planet/deposit generation, home-systems, empires, delete-step) have no `throttle` middleware. Heavy DB operations and email-sending actions are unprotected.

**Files to modify:**
- `routes/admin.php` — add `throttle` middleware to mutation routes (POST/PATCH/DELETE)
- `routes/games.php` — add `throttle` middleware to mutation routes in the generation and turn-report groups
- Optionally define named rate limiters in `app/Providers/AppServiceProvider.php` or use built-in `throttle:x,y`

**Acceptance:**
- [x] Repeated rapid requests eventually return 429
- [x] Normal single requests still succeed
- [x] `php artisan test --compact tests/Feature/Admin/SendPasswordResetLinkTest.php tests/Feature/GameGenerationControllerActivateTest.php`

---

## Phase 3 — Model and Schema Completeness

These tasks fix model gaps and database schema issues. Migration tasks should NOT run in parallel with each other.

### BD-07 — Create PlayerFactory and add HasFactory to Player model

**Severity:** Critical — only model without factory support  
**Effort:** S  
**Dependencies:** None

**Problem:** `app/Models/Player.php` does not `use HasFactory` and has no `PlayerFactory` in `database/factories/`. Every other model has both. Also missing `@return array<string, string>` PHPDoc on `casts()`.

**Files to create:**
- `database/factories/PlayerFactory.php` — factory should create a valid `Game` and `User` association, set `role` to `GameRole::Player` and `is_active` to `true`

**Files to modify:**
- `app/Models/Player.php` — add `use HasFactory;` with `/** @use HasFactory<PlayerFactory> */` annotation; add `@return` PHPDoc on `casts()`

**Acceptance:**
- [x] `Player::factory()->create()` works
- [x] `php artisan test --compact --filter=Player`

---

### BD-08 — Fix historical migration: replace Eloquent model with DB facade

**Severity:** Critical — `Game::all()` in migration will break if model changes  
**Effort:** S  
**Dependencies:** None (but do NOT run in parallel with BD-09, BD-10, BD-18)

**Problem:** `database/migrations/2026_03_30_170742_add_prng_columns_to_games_table.php` uses `App\Models\Game` (line 4, 22) with `Game::all()`. If the model adds new scopes, accessors, or casts later, this migration will break. Also mixes DDL (Schema::table) and DML (Game update) in the same `up()`.

**Files to modify:**
- `database/migrations/2026_03_30_170742_add_prng_columns_to_games_table.php` — replace `use App\Models\Game; Game::all()` with `DB::table('games')->get()` and update column references accordingly

**Also check:** `database/migrations/2026_04_03_174100_add_handle_to_users_table.php` — verify it uses `DB::table()` not Eloquent models (it uses `DB::table` already per audit, but confirm).

**Acceptance:**
- [x] No `use App\Models\*` imports in any migration file
- [x] `php artisan migrate:fresh --env=testing` succeeds
- [x] `php artisan test --compact`

---

### BD-09 — Add missing FK indexes on rebuilt SQLite tables and games.status index

**Severity:** Important — performance at scale  
**Effort:** M  
**Dependencies:** None (but do NOT run in parallel with BD-08, BD-10, BD-18)

**Problem:** SQLite rebuild migrations created tables via raw SQL without indexes on FK columns. These columns are frequently used in WHERE/JOIN clauses.

**Files to create:**
- New migration: `php artisan make:migration add_missing_indexes --no-interaction`

**Indexes to add:**
- `colonies.empire_id`
- `colonies.star_id`
- `colonies.planet_id`
- `colony_inventory.colony_id`
- `colony_template_items.colony_template_id`
- `colony_templates.game_id`
- `games.status`

**Acceptance:**
- [x] `php artisan migrate:fresh --env=testing` succeeds
- [x] Verify indexes exist using `PRAGMA index_list(colonies)` etc. in a test or tinker
- [x] `php artisan test --compact`

---

### BD-10 — Investigate and potentially add missing FK constraints and indexes to turn-report sub-tables

**Severity:** Important  
**Effort:** M  
**Dependencies:** None (but do NOT run in parallel with BD-08, BD-09, BD-18)

**Problem:** `turn_report_colonies.source_colony_id`, `turn_report_colonies.planet_id`, and `turn_report_surveys.planet_id` are plain `integer()->nullable()` columns (created at `2026_04_03_142010` and `2026_04_03_142638`) without FK constraints or indexes.

**⚠️ IMPORTANT — Investigate before adding FK constraints:**  
Turn reports are **historical snapshots** — they must survive even if the referenced game entity (colony, planet, ship, etc.) is deleted during gameplay. Adding FK constraints with `cascadeOnDelete()` would destroy historical report data when a source entity is removed. Before writing any migration:

1. Check whether colonies, planets, or other referenced entities can be deleted during normal gameplay (not just `migrate:fresh`). Search controllers, services, and commands for `->delete()`, `->forceDelete()`, `destroy()`, or `DB::table(...)->delete()` on `colonies`, `planets`, and related tables.
2. If entities **can** be deleted during gameplay, FK constraints on turn-report tables are **intentionally omitted** — do NOT add them. Only add **indexes** (without FK constraints) for query performance.
3. If entities are **never** deleted during gameplay (only via fresh migration), then `nullOnDelete()` FK constraints are safe.
4. **Present findings to the developer and ask for confirmation** before creating any migration. Do not proceed without approval.

**Indexes are safe to add regardless** — they improve query performance without affecting deletion behavior. At minimum, add indexes on `turn_report_colonies.source_colony_id`, `turn_report_colonies.planet_id`, `turn_report_colonies.turn_report_id`, and `turn_report_surveys.planet_id`.

**Files to create:**
- New migration for indexes (always safe)
- New SQLite rebuild migration(s) for FK constraints **only if developer approves** after investigation

**Note:** SQLite requires a full table rebuild to add FK constraints. Follow the existing pattern in `2026_04_04_184259_add_star_id_and_ship_kind_to_colonies.php` using `PRAGMA defer_foreign_keys = ON` and raw SQL.

**Investigation outcome:** Colonies, planets, and stars are never deleted during active gameplay — only during setup-phase rollbacks and full game deletion. FK constraints would be safe (`nullOnDelete()`) but require complex SQLite table rebuilds for minimal practical benefit. **Developer confirmed: indexes only, no FK constraints.**

**Acceptance:**
- [x] Investigation results documented: can referenced entities be deleted during gameplay?
- [x] Developer has confirmed whether FK constraints should be added or only indexes
- [x] Indexes added for query performance on all nullable reference columns
- [x] `php artisan migrate:fresh --env=testing` succeeds
- [x] Turn report generation and download tests still pass
- [x] `php artisan test --compact tests/Feature/TurnReports/ tests/Feature/Services/SetupReportGeneratorTest.php`

---

### BD-11 — Add missing policies for game-owned models

**Severity:** Important  
**Effort:** M  
**Dependencies:** None

**Problem:** Only `GamePolicy`, `TurnReportPolicy`, and `UserPolicy` exist. Models that are directly accessed via routes (`Empire`, `Star`, `Planet`) lack policies. Controllers use inline authorization checks instead.

**Files to create (use `php artisan make:policy`):**
- `app/Policies/EmpirePolicy.php`
- `app/Policies/PlayerPolicy.php`
- `app/Policies/StarPolicy.php`
- `app/Policies/PlanetPolicy.php`
- `app/Policies/HomeSystemPolicy.php`
- `app/Policies/InvitationPolicy.php`

**Authorization rules (reference `app/Policies/GamePolicy.php` and `app/Policies/TurnReportPolicy.php` for patterns):**
- Game-owned models (Empire, Star, Planet, HomeSystem): admin or GM of the game can mutate; active game members can view
- Player: admin or GM of the game can manage
- Invitation: admin only

**Files to create:**
- `tests/Feature/Policies/` — focused policy tests covering admin, GM, active player, and unrelated user cases

**Acceptance:**
- [x] Each new policy has at least `view` and `update` methods
- [x] Policy tests pass for all authorization scenarios
- [x] `php artisan test --compact --filter=Policy`

---

### BD-12 — Remove phantom `is_gm` cast from User model

**Severity:** Important  
**Effort:** S  
**Dependencies:** None

**Problem:** `app/Models/User.php:42` casts `'is_gm' => 'boolean'` but there is no `is_gm` column in the `users` table. This is a virtual attribute loaded via `withExists(['games as is_gm' => ...])` in `HandleInertiaRequests` middleware. The cast is misleading and could mask bugs.

**Files to modify:**
- `app/Models/User.php:42` — remove `'is_gm' => 'boolean'` from `casts()`

**Verify:** Check `app/Http/Controllers/Admin/UserController.php` and `app/Http/Middleware/HandleInertiaRequests.php` to confirm `is_gm` is loaded via `withExists`/`loadExists` which already returns a boolean — no cast needed.

**Acceptance:**
- [x] `is_gm` still works correctly as a boolean in templates/responses
- [x] `php artisan test --compact tests/Feature/Admin/ tests/Feature/HandleInertiaRequestsTest.php`

---

### BD-13 — Add missing `@use HasFactory` annotations to TurnReport-family models

**Severity:** Minor  
**Effort:** S  
**Dependencies:** None

**Problem:** Six TurnReport-family models use bare `use HasFactory;` without the `/** @use HasFactory<XFactory> */` generic annotation. Every other model in the codebase has this annotation.

**Files to modify:**
- `app/Models/TurnReport.php` — add `/** @use HasFactory<TurnReportFactory> */`
- `app/Models/TurnReportColony.php` — add `/** @use HasFactory<TurnReportColonyFactory> */`
- `app/Models/TurnReportColonyInventory.php` — add `/** @use HasFactory<TurnReportColonyInventoryFactory> */`
- `app/Models/TurnReportColonyPopulation.php` — add `/** @use HasFactory<TurnReportColonyPopulationFactory> */`
- `app/Models/TurnReportSurvey.php` — add `/** @use HasFactory<TurnReportSurveyFactory> */`
- `app/Models/TurnReportSurveyDeposit.php` — add `/** @use HasFactory<TurnReportSurveyDepositFactory> */`

**Note:** Also add the corresponding `use Database\Factories\XFactory;` import if not already present. Check the factory class names in `database/factories/` to use the correct names.

**Acceptance:**
- [x] All 6 models have the annotation matching their factory
- [x] `php artisan test --compact tests/Feature/TurnReports/ tests/Feature/Reports/`

---

## Phase 4 — Controller Slimming and Cleanup

These tasks extract business logic from fat controllers. They depend on Phase 2 (validation hardening) being complete so refactors happen on stable behavior.

### BD-14 — Extract template upload business logic from TemplateController

**Severity:** Important  
**Effort:** M  
**Dependencies:** BD-03

**Problem:** `app/Http/Controllers/GameGeneration/TemplateController.php` has two fat methods: `uploadHomeSystem()` (lines 17-50, 34 lines of template parsing) and `uploadColony()` (lines 53-128, 75 lines including pay-rate calculations, population parsing, and inventory parsing).

**Files to create:**
- Action/service class(es), e.g. `app/Actions/GameGeneration/ImportHomeSystemTemplate.php` and `app/Actions/GameGeneration/ImportColonyTemplates.php`

**Files to modify:**
- `app/Http/Controllers/GameGeneration/TemplateController.php` — controller should only: authorize, reject active games, hand off validated payload to action, return redirect with flash

**Change:** Move colony template persistence logic (DB transaction, colonyTemplates deletion, pay-rate calculation for ConstructionWorker/Spy, item parsing with CODE-TL format) into the action class. Move home system template persistence (planet creation, deposit creation) into its action class.

**Acceptance:**
- [ ] Controller methods are under 15 lines each
- [ ] Business logic lives in action classes
- [ ] `php artisan test --compact tests/Feature/UploadColonyTemplateValidationTest.php tests/Feature/GameGenerationControllerTest.php`

---

### BD-15 — Extract TurnReportController::download() export logic

**Severity:** Important  
**Effort:** M  
**Dependencies:** BD-01

**Problem:** `app/Http/Controllers/TurnReportController.php:96-205` has `download()` with 90+ lines of inline JSON payload assembly including business logic (cadre detection, pay calculation, food consumption math at lines ~142-159).

**Files to create:**
- Exporter class, e.g. `app/Support/TurnReports/TurnReportJsonExporter.php`

**Files to modify:**
- `app/Http/Controllers/TurnReportController.php` — controller `download()` should only: authorize, load report, delegate to exporter, return response

**Acceptance:**
- [ ] Controller `download()` is under 15 lines
- [ ] Export logic and business calculations live in the exporter class
- [ ] `php artisan test --compact tests/Feature/TurnReports/TurnReportControllerDownloadTest.php`

---

### BD-16 — Slim GameGenerationController by extracting payload builders

**Severity:** Important  
**Effort:** L  
**Dependencies:** None

**Problem:** `app/Http/Controllers/GameGenerationController.php` is 339 lines with 12 private helper methods used by `show()` (assembles 12 props) and `download()` (46 lines of cluster JSON export).

**Files to create:**
- Presenter/read-model class(es), e.g. `app/Support/GameGeneration/GenerationPagePresenter.php` and/or `app/Support/GameGeneration/ClusterExporter.php`

**Files to modify:**
- `app/Http/Controllers/GameGenerationController.php` — move private helpers (`starsSummary`, `planetsSummary`, `depositsSummary`, `starList`, `planetList`, `homeSystemsList`, `availableStarsList`, `membersList`, `reportTurnPayload`, etc.) into presenter class(es)

**Acceptance:**
- [ ] Controller is under 60 lines
- [ ] `php artisan test --compact tests/Feature/GameGenerationControllerTest.php tests/Feature/GameGenerationControllerDownloadTest.php tests/Feature/GameGenerationControllerCreateHomeSystemTest.php tests/Feature/GameGenerationReportPropsTest.php`

---

### BD-17 — Normalize route names to consistent dot-notation

**Severity:** Minor  
**Effort:** M  
**Dependencies:** BD-02, BD-06 (to avoid merge conflicts on route files)

**Problem:** Route names are inconsistent — some use kebab-case segments, others use dot-notation:
- `admin.users.update-handle` (kebab)
- `admin.users.send-password-reset` (kebab)
- `games.generate.update-star` (kebab)
- `games.generate.update-planet` (kebab)
- `games.generate.delete-step` (kebab)
- `games.generate.empires.create` (should be `store` per resource convention — POST = store, GET = create)

**Files to modify:**
- `routes/admin.php:11-12` — rename to dot-notation (e.g., `admin.users.handle.update`, `admin.users.password-reset.send`)
- `routes/games.php:43,45,49,51` — rename to dot-notation (e.g., `games.generate.stars.update`, `games.generate.planets.update`, `games.generate.empires.store`, `games.generate.steps.destroy`)
- Search all test files and frontend code for old route names and update them: `grep -r "update-handle\|update-star\|update-planet\|delete-step\|empires.create" tests/ resources/`

**Acceptance:**
- [ ] `php artisan route:list` shows only new canonical names
- [ ] `php artisan test --compact tests/Feature/Admin/HandleUpdateTest.php tests/Feature/GameGenerationControllerUpdateStarTest.php tests/Feature/GameGenerationControllerUpdatePlanetTest.php tests/Feature/GameGenerationControllerDeleteStepTest.php tests/Feature/GameGenerationControllerEmpireTest.php`
- [ ] `bun run build` succeeds (Wayfinder regenerated)

---

### BD-18 — Remove redundant `Schema::disableForeignKeyConstraints()` from SQLite rebuild migrations

**Severity:** Minor  
**Effort:** S  
**Dependencies:** None (but do NOT run in parallel with BD-08, BD-09, BD-10)

**Problem:** Four SQLite rebuild migrations wrap their `up()` in `Schema::disableForeignKeyConstraints()` / `enableForeignKeyConstraints()` AND also use `PRAGMA defer_foreign_keys = ON`. The `Schema::disable...` call is a no-op inside a transaction (which migrations run in), making it redundant.

**Files to modify:**
- `database/migrations/2026_04_02_192424_rebuild_colonies_for_string_kind_and_setup_report_columns.php`
- `database/migrations/2026_04_02_185842_rebuild_colony_inventory_colony_template_items_and_colony_templates_for_string_codes.php`
- `database/migrations/2026_04_04_184259_add_star_id_and_ship_kind_to_colonies.php`
- `database/migrations/2026_04_04_184420_drop_is_on_surface_from_turn_report_colonies.php`

**Change:** Remove `Schema::disableForeignKeyConstraints()` and `Schema::enableForeignKeyConstraints()` calls. Keep `DB::statement('PRAGMA defer_foreign_keys = ON')` and any explanatory comments.

**Acceptance:**
- [ ] `php artisan migrate:fresh --env=testing` succeeds
- [ ] `php artisan test --compact`

---

### BD-19 — Remove default `inspire` console command

**Severity:** Minor  
**Effort:** S  
**Dependencies:** None

**Problem:** `routes/console.php:3-8` still contains the Laravel starter `inspire` command. The app does not use it.

**Files to modify:**
- `routes/console.php` — remove the `inspire` Artisan command closure; keep the file with just the `<?php` tag and `use` statements if other commands exist, or leave it minimal

**Acceptance:**
- [ ] `php artisan list` no longer includes `inspire`
- [ ] `php artisan test --compact`

---

## Execution Order

Tasks should be completed in this order. Tasks at the same indentation level can be parallelized unless noted.

```
Phase 1 (route foundations):
  BD-01  (Turn empires relationship)
  BD-02  (scoped bindings on generate routes)      ← parallel with BD-01

Phase 2 (security hardening):                      ← after Phase 1 route files are stable
  BD-03  (template JSON validation)                ← parallel
  BD-04  (FormRequest authorize + GenerateStarsRequest) ← parallel
  BD-05  (verified middleware + admin authorize)    ← parallel
  BD-06  (rate limiting)                           ← parallel

Phase 3 (model/schema completeness):               ← parallel with Phase 2
  BD-07  (PlayerFactory)                           ← parallel
  BD-08  (fix Eloquent in migration)               ← migration: serialize
  BD-09  (add FK indexes)                          ← migration: serialize after BD-08
  BD-10  (turn-report FK constraints)              ← migration: serialize after BD-09
  BD-11  (missing policies)                        ← parallel
  BD-12  (remove is_gm cast)                       ← parallel
  BD-13  (HasFactory annotations)                  ← parallel

Phase 4 (controller slimming + cleanup):            ← after Phases 2-3
  BD-14  (extract TemplateController logic)        ← after BD-03
  BD-15  (extract download export logic)           ← after BD-01
  BD-16  (slim GameGenerationController)           ← parallel
  BD-17  (normalize route names)                   ← after BD-02, BD-06
  BD-18  (remove redundant Schema calls)           ← migration: serialize
  BD-19  (remove inspire command)                  ← parallel
```
