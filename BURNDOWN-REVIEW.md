# BURNDOWN Code Review

Review of commits `be1f5f2..051757e` covering Tasks 1–25 of the Game Generation Workflow.

---

## Summary

The implementation is solid overall. The state machine, PRNG pipeline, service-layer architecture, and test coverage are well done. The issues below are a mix of bugs, Laravel best-practice violations, and code smells — none are catastrophic, but several could cause subtle production problems.

---

## Critical Findings

### Finding 1. ~~`game_user_id` is not a real FK — empire lookups use `user_id` as if it were `game_user_id`~~ ✅ RESOLVED

**Resolution:** The `game_user` pivot table was promoted to a first-class `players` table with its own auto-increment `id`. The `empires.game_user_id` column was renamed to `player_id` with a proper FK constraint (`nullable`, `nullOnDelete`) to `players.id`. A new `Player` model was created as the domain entity representing a User's membership in a Game. The `Empire` model now has a `player()` BelongsTo relationship. The `Game` model's `players()` BelongsToMany was renamed to `activePlayers()`, and a new `playerRecords()` HasMany was added. `EmpireCreator` looks up the Player record by `(game_id, user_id)` and stores `player->id`. Three incremental alter migrations on the old pivot were deleted and baked into the create migration. All 146 tests updated and passing.

### Finding 2. ~~Delete cascade does not explicitly delete empires/colonies (relies on DB FK cascade)~~ ✅ RESOLVED

**Files:** `app/Http/Controllers/GameGenerationController.php:512–576`

**Observation:** The `performDelete*` methods delete `homeSystems`, `deposits`, `planets`, `stars` but never explicitly delete `empires`, `colonies`, or `colony_inventory`. This works because:
- `empires.home_system_id` → `cascadeOnDelete()`
- `colonies.empire_id` → `cascadeOnDelete()`
- `colony_inventory.colony_id` → `cascadeOnDelete()`

**Verdict:** This is correct and the DB-level cascade is reliable. However, a clarifying comment should be added, and the existing tests should verify empire/colony cleanup (the delete step tests currently don't assert empires/colonies are removed).

**Resolution:** Added a three-line comment block above each `performDelete*` call in `GenerationStepController` documenting the FK cascade chain (`home_systems → empires → colonies → colony_inventory`). Added a dedicated test `delete_stars_cascades_to_empires_and_colonies` in `GameGenerationControllerDeleteStepTest` that creates a full empire+colony fixture, deletes the stars step, and asserts both `Empire` and `Colony` records are missing.

### Finding 3. `prng_state` is not in `$fillable` — direct assignment works but it's inconsistent

**File:** `app/Models/Game.php:15`

**Problem:** The `Game` model marks `['name', 'is_active', 'prng_seed', 'status', 'min_home_system_distance']` as fillable but not `prng_state`. The services set `$game->prng_state` directly (which bypasses fillable), so this works. But it's an inconsistency — every other mutable column is in `$fillable`.

---

## Medium Findings

### Finding 4. ~~Deprecated `$dates` property used on two models~~ ✅ RESOLVED

**Files:** `app/Models/HomeSystem.php:20`, `app/Models/GenerationStep.php:20`

**Problem:** `protected $dates` was deprecated in Laravel 10 and removed in later versions. These models should use the `casts()` method instead:
```php
protected function casts(): array
{
    return ['created_at' => 'datetime'];
}
```

**Resolution:** Replaced `protected $dates = ['created_at']` with a `protected function casts(): array` method returning `['created_at' => 'datetime']` on both `HomeSystem` and `GenerationStep`.

### Finding 5. Controller `show()` method is excessively large — 177 lines

**File:** `app/Http/Controllers/GameGenerationController.php:32–178`

**Problem:** The `show` method performs ~10 separate queries, builds ~10 data structures, and assembles a massive Inertia payload. This is hard to test, hard to maintain, and violates the "methods under 10 lines" guideline. The star/planet list queries also load every row for display — at scale (100 stars × 11 planets = 1100 rows), this is a lot of data passed to the frontend on every page load.

**Recommendation:** Extract data preparation into private methods or a dedicated resource/DTO class. Consider using Inertia deferred/optional props for the star list and planet list so they don't block initial page render.

### Finding 6. ~~`GameGenerationController` is a 667-line mega-controller~~ ✅ RESOLVED

**Resolution:** Split into 7 single-responsibility controllers under `app/Http/Controllers/GameGeneration/`:
- `GameGenerationController` — `show`, `download`, `activate` (3 methods)
- `GameGeneration/GenerationStepController` — `generateStars`, `generatePlanets`, `generateDeposits`, `deleteStep`
- `GameGeneration/HomeSystemController` — `createRandom`, `createManual`
- `GameGeneration/EmpireController` — `store`, `reassign`
- `GameGeneration/TemplateController` — `uploadHomeSystem`, `uploadColony`
- `GameGeneration/StarController` — `update`
- `GameGeneration/PlanetController` — `update`

Route names are unchanged. Wayfinder regenerated. Frontend imports updated to reference the new controller modules.

### Finding 7. ~~Template upload does manual JSON validation instead of using Form Request rules~~ ✅ RESOLVED

**Files:** `app/Http/Controllers/GameGenerationController.php:578–666`, `app/Http/Requests/UploadHomeSystemTemplateRequest.php`, `app/Http/Requests/UploadColonyTemplateRequest.php`

**Resolution:** Both Form Requests now implement `after()` hooks that handle JSON parse error detection and structural validation (non-empty `planets` array, exactly one homeworld for home system template; non-empty `inventory` array for colony template). The controller's manual `if` validation blocks were removed.

### Finding 8. ~~Inline validation in controller actions instead of Form Requests~~ ✅ RESOLVED

**Files:** `app/Http/Controllers/GameGenerationController.php:238–239, 326–328, 357–359, 397–398, 432–436, 473–477`

**Resolution:** Created dedicated Form Request classes for all five actions: `UpdateStarRequest`, `UpdatePlanetRequest`, `CreateHomeSystemManualRequest`, `CreateEmpireRequest`, and `ReassignEmpireRequest`. Inline `$request->validate()` calls replaced with `$request->validated()`. Unused `PlanetType` and `Rule` imports removed from the controller.

### Finding 9. ~~Hardcoded capacity constant (25 empires per home system) scattered across codebase~~ ✅ RESOLVED

**Files:** `app/Services/EmpireCreator.php:43,50,82`, `app/Http/Controllers/GameGenerationController.php:107`, `resources/js/pages/games/generate.tsx:129`

**Problem:** The number `25` appears as a magic number in three separate files. If this value ever changes, it must be updated in all locations.

**Recommendation:** Define as a constant on the `HomeSystem` model (e.g., `HomeSystem::MAX_EMPIRES_PER_HOME_SYSTEM = 25`) and reference it everywhere. Pass it to the frontend via the Inertia payload.

**Resolution:** Defined `HomeSystem::MAX_EMPIRES_PER_HOME_SYSTEM = 25` and `HomeSystem::MAX_EMPIRES_PER_GAME = 250` as typed `public const int` on the `HomeSystem` model. All three magic-number references in `EmpireCreator` were replaced with the constant. The Inertia payload already passed `capacity` per home system item; that value is now populated from `HomeSystem::MAX_EMPIRES_PER_HOME_SYSTEM` in the controller, so the frontend reads the constant indirectly without needing a separate prop.

### Finding 10. ~~`HomeSystemCreator::applyTemplate()` creates planets/deposits one-by-one~~ ✅ RESOLVED

**File:** `app/Services/HomeSystemCreator.php:128–176`

**Resolution:** `applyTemplate()` now batch-inserts all planets via `Planet::insert()`, queries them back keyed by orbit to obtain IDs, then batch-inserts all deposits via `Deposit::insert()`. Reduces 15–30 individual INSERTs to 2–3 queries.

### Finding 11. ~~`EmpireCreator::createColony()` also creates inventory one-by-one~~ ✅ RESOLVED

**File:** `app/Services/EmpireCreator.php:112–119`

**Resolution:** Colony inventory items are now batch-inserted via `ColonyInventory::insert()` after the colony record is created.

---

## Low Findings

### Finding 12. ~~`GameRng::fromState()` creates a throwaway RNG instance~~ ✅ RESOLVED

**Resolution:** The public constructor was made private and replaced with two named static factories: `GameRng::fromSeed(string $seed)` and `GameRng::fromState(string $serialized)`. `fromState()` no longer hashes a seed or constructs a throwaway engine. Call sites in `StarGenerator` and `GameRngTest` were updated to use `GameRng::fromSeed()`.

### Finding 13. `Star` model missing `homeSystem` relationship

**File:** `app/Models/Star.php`

**Problem:** `HomeSystem` belongsTo `Star`, but `Star` doesn't define a `hasOne HomeSystem` inverse. This isn't currently needed but is an incomplete relationship mapping.

### Finding 14. ~~`Empire` model missing `gameUser` relationship~~ ✅ RESOLVED

**Resolution:** The `Empire` model now has a `player()` BelongsTo relationship to the `Player` model, with a proper FK on `player_id`. Resolved as part of finding #1.

### Finding 15. ~~Frontend page component is 1246 lines~~ ✅ RESOLVED

**Resolution:** `generate.tsx` was split into 9 focused sub-components under `resources/js/pages/games/generate/`: `types.ts` (shared types), `PrngSeedSection`, `HomeSystemTemplateSection`, `ColonyTemplateSection`, `StarsSection`, `PlanetsSection`, `DepositsSection`, `HomeSystemsSection`, `ActivateSection` (owns its own dialog), and `EmpiresSection` (owns `EmpiresTable`). The shared delete-step dialog state remains in the orchestrating `generate.tsx`, which is now ~160 lines.

### Finding 16. ~~`generationSteps` relationship is loaded eagerly via `$game->generationSteps` in `show()`~~ ✅ RESOLVED

**Resolution:** Added an explicit `$game->load('generationSteps')` call at the top of `show()`, immediately after the Gate authorization. The `$game->generationSteps` reference in the Inertia payload now uses the pre-loaded relation.

### Finding 17. ~~Missing `game_user` pivot `id` column~~ ✅ RESOLVED

**Resolution:** The `game_user` pivot was promoted to a first-class `players` table with its own auto-increment `id` column. Resolved as part of finding #1.

### Finding 18. ~~`activate` action doesn't use a DB lock~~ ✅ RESOLVED

**Resolution:** The `activate` action now wraps its read-check-save sequence in a `DB::transaction()` with `Game::lockForUpdate()->findOrFail($game->id)`, consistent with all other state-changing actions in the controller.

---

## Positive Observations

- **PRNG pipeline** is well-designed: deterministic, state-chained, with step records for rollback.
- **Service layer separation** is clean: generators and creators each own their domain logic.
- **Database locking** (`lockForUpdate`) is correctly applied in all generator services.
- **Test coverage** is excellent: 11 test files with ~2500 lines covering happy paths, error paths, and edge cases.
- **Factories** are well-structured with useful states like `withDefaultTemplates()`.
- **Migration FK cascades** are correctly configured for the full hierarchy.
- **Frontend** correctly uses Wayfinder for route generation, Inertia forms with loading states, and confirmation dialogs.

---

## Remediation Plan

Ordered by priority (highest first). Each task is independent unless noted.

| #  | Task                                                                                                                                         | Severity | Effort | Files                                                                                                    |
|----|----------------------------------------------------------------------------------------------------------------------------------------------|----------|--------|----------------------------------------------------------------------------------------------------------|
| 1  | ~~Resolve `game_user_id` semantics~~ ✅ Promoted `game_user` pivot to `players` table; `empires.player_id` now has proper FK                  | Critical | M      | Done                                                                                                     |
| 2  | ~~Replace `$dates` with `casts()` on `HomeSystem` and `GenerationStep`~~ ✅ Done                                                              | Medium   | S      | `HomeSystem.php`, `GenerationStep.php`                                                                   |
| 3  | ~~Extract a constant for home system capacity (25) and empire cap (250)~~ ✅ Done                                                             | Medium   | S      | `HomeSystem.php`, `EmpireCreator.php`, `GameGenerationController.php`, `generate.tsx`                    |
| 4  | ~~Add clarifying comments on cascade deletes; add test assertions for empire/colony cleanup in delete step tests~~ ✅ Done                    | Medium   | S      | `GameGenerationController.php`, `GameGenerationControllerDeleteStepTest.php`                             |
| 5  | ~~Move JSON structure validation from controller into Form Request `after()` hooks; add JSON parse error handling~~ ✅ Done                  | Medium   | S      | `UploadHomeSystemTemplateRequest.php`, `UploadColonyTemplateRequest.php`, `GameGenerationController.php` |
| 6  | ~~Create Form Requests for `updateStar`, `updatePlanet`, `createHomeSystemManual`, `createEmpire`, `reassignEmpire`~~ ✅ Done                 | Medium   | M      | New Form Request files, `GameGenerationController.php`                                                   |
| 7  | ~~Batch-insert planets and deposits in `HomeSystemCreator::applyTemplate()` and colony inventory in `EmpireCreator::createColony()`~~ ✅ Done | Medium   | S      | `HomeSystemCreator.php`, `EmpireCreator.php`                                                             |
| 8  | ~~Add `lockForUpdate` to the `activate` action for concurrency consistency~~ ✅ Done                                                          | Low      | S      | `GameGenerationController.php`                                                                           |
| 9  | ~~Fix `GameRng::fromState()` to avoid throwaway constructor work~~ ✅ Done                                                                    | Low      | S      | `GameRng.php`, `StarGenerator.php`, `GameRngTest.php`                                                   |
| 10 | ~~Eager-load `generationSteps` in the `show()` method~~ ✅ Done                                                                               | Low      | S      | `GameGenerationController.php`                                                                           |
| 11 | ~~Split `GameGenerationController` into smaller controllers~~ ✅ Done                                                                        | Low      | L      | `GameGeneration/` subdirectory (6 new controllers), `routes/games.php`, `generate.tsx`                  |
| 12 | ~~Extract frontend sections into sub-components~~ ✅ Done                                                                                    | Low      | M      | `generate/` subdirectory (9 new files), `generate.tsx`                                                  |
| 13 | Extract `show()` data preparation into private methods or use Inertia deferred props for star/planet lists                                   | Low      | M      | `GameGenerationController.php`, `generate.tsx`                                                           |
| 14 | Investigate OOM (out-of-memory) issue when running the full test suite                                                                       | Medium   | M      | `phpunit.xml`, test files                                                                                |

**Effort key:** S = < 1 hour, M = 1–3 hours, L = 3+ hours
