# BURNDOWN Code Review

Review of commits `be1f5f2..051757e` covering Tasks 1–25 of the Game Generation Workflow.

---

## Summary

The implementation is solid overall. The state machine, PRNG pipeline, service-layer architecture, and test coverage are well done. The issues below are a mix of bugs, Laravel best-practice violations, and code smells — none are catastrophic, but several could cause subtle production problems.

---

## Critical Findings

### 1. ~~`game_user_id` is not a real FK — empire lookups use `user_id` as if it were `game_user_id`~~ ✅ RESOLVED

**Resolution:** The `game_user` pivot table was promoted to a first-class `players` table with its own auto-increment `id`. The `empires.game_user_id` column was renamed to `player_id` with a proper FK constraint (`nullable`, `nullOnDelete`) to `players.id`. A new `Player` model was created as the domain entity representing a User's membership in a Game. The `Empire` model now has a `player()` BelongsTo relationship. The `Game` model's `players()` BelongsToMany was renamed to `activePlayers()`, and a new `playerRecords()` HasMany was added. `EmpireCreator` looks up the Player record by `(game_id, user_id)` and stores `player->id`. Three incremental alter migrations on the old pivot were deleted and baked into the create migration. All 146 tests updated and passing.

### 2. Delete cascade does not explicitly delete empires/colonies (relies on DB FK cascade)

**Files:** `app/Http/Controllers/GameGenerationController.php:512–576`

**Observation:** The `performDelete*` methods delete `homeSystems`, `deposits`, `planets`, `stars` but never explicitly delete `empires`, `colonies`, or `colony_inventory`. This works because:
- `empires.home_system_id` → `cascadeOnDelete()`
- `colonies.empire_id` → `cascadeOnDelete()`
- `colony_inventory.colony_id` → `cascadeOnDelete()`

**Verdict:** This is correct and the DB-level cascade is reliable. However, a clarifying comment should be added, and the existing tests should verify empire/colony cleanup (the delete step tests currently don't assert empires/colonies are removed).

### 3. `prng_state` is not in `$fillable` — direct assignment works but it's inconsistent

**File:** `app/Models/Game.php:15`

**Problem:** The `Game` model marks `['name', 'is_active', 'prng_seed', 'status', 'min_home_system_distance']` as fillable but not `prng_state`. The services set `$game->prng_state` directly (which bypasses fillable), so this works. But it's an inconsistency — every other mutable column is in `$fillable`.

---

## Medium Findings

### 4. Deprecated `$dates` property used on two models

**Files:** `app/Models/HomeSystem.php:20`, `app/Models/GenerationStep.php:20`

**Problem:** `protected $dates` was deprecated in Laravel 10 and removed in later versions. These models should use the `casts()` method instead:
```php
protected function casts(): array
{
    return ['created_at' => 'datetime'];
}
```

### 5. Controller `show()` method is excessively large — 177 lines

**File:** `app/Http/Controllers/GameGenerationController.php:32–178`

**Problem:** The `show` method performs ~10 separate queries, builds ~10 data structures, and assembles a massive Inertia payload. This is hard to test, hard to maintain, and violates the "methods under 10 lines" guideline. The star/planet list queries also load every row for display — at scale (100 stars × 11 planets = 1100 rows), this is a lot of data passed to the frontend on every page load.

**Recommendation:** Extract data preparation into private methods or a dedicated resource/DTO class. Consider using Inertia deferred/optional props for the star list and planet list so they don't block initial page render.

### 6. `GameGenerationController` is a 667-line mega-controller

**File:** `app/Http/Controllers/GameGenerationController.php`

**Problem:** 14 public action methods in one controller. While the routes are logically grouped under `{game}/generate`, the controller violates single-responsibility. Template upload, star/planet/deposit generation, home system creation, empire assignment, downloading, and step deletion are all distinct concerns.

**Recommendation:** Consider splitting into:
- `GameGenerationController` — show, download
- `GenerationStepController` — generateStars, generatePlanets, generateDeposits, deleteStep
- `HomeSystemController` — createRandom, createManual
- `EmpireController` — createEmpire, reassignEmpire
- `TemplateController` — uploadHomeSystemTemplate, uploadColonyTemplate
- `StarController` / `PlanetController` — updateStar, updatePlanet

### 7. Template upload does manual JSON validation instead of using Form Request rules

**Files:** `app/Http/Controllers/GameGenerationController.php:578–666`, `app/Http/Requests/UploadHomeSystemTemplateRequest.php`, `app/Http/Requests/UploadColonyTemplateRequest.php`

**Problem:** The Form Requests only validate that a file was uploaded with the right type/size. All structural validation (planets array, homeworld count, inventory) is done manually in the controller with `json_decode` + `if` checks. This means:
- The Form Requests are nearly empty shells.
- Validation logic that belongs in the request class is in the controller.
- The `json_decode` call doesn't check for JSON parse errors.

**Recommendation:** Move JSON structure validation into the Form Request classes using `after()` hooks, or extract to a dedicated validator/service.

### 8. Inline validation in controller actions instead of Form Requests

**Files:** `app/Http/Controllers/GameGenerationController.php:238–239, 326–328, 357–359, 397–398, 432–436, 473–477`

**Problem:** Multiple actions use `$request->validate()` inline. Laravel best practice is to use dedicated Form Request classes for all validated endpoints. The `updateStar`, `updatePlanet`, `createHomeSystemManual`, `createEmpire`, and `reassignEmpire` actions should each have their own Form Request.

### 9. Hardcoded capacity constant (25 empires per home system) scattered across codebase

**Files:** `app/Services/EmpireCreator.php:43,50,82`, `app/Http/Controllers/GameGenerationController.php:107`, `resources/js/pages/games/generate.tsx:129`

**Problem:** The number `25` appears as a magic number in three separate files. If this value ever changes, it must be updated in all locations.

**Recommendation:** Define as a constant on the `HomeSystem` model (e.g., `HomeSystem::MAX_EMPIRES_PER_HOME_SYSTEM = 25`) and reference it everywhere. Pass it to the frontend via the Inertia payload.

### 10. `HomeSystemCreator::applyTemplate()` creates planets/deposits one-by-one

**File:** `app/Services/HomeSystemCreator.php:128–176`

**Problem:** Uses `Planet::create()` and `Deposit::create()` inside loops (N+1 inserts). For a typical home system template with 7–9 planets and multiple deposits each, this results in 15–30 individual INSERT queries within a transaction.

**Recommendation:** Batch-insert planets, then batch-insert deposits (similar to how `StarGenerator`, `PlanetGenerator`, and `DepositGenerator` use `::insert()`). The only complication is needing the planet IDs for deposit FK references — this can be solved by inserting planets first, then querying them back.

### 11. `EmpireCreator::createColony()` also creates inventory one-by-one

**File:** `app/Services/EmpireCreator.php:112–119`

**Problem:** Same issue as #10 — `ColonyInventory::create()` inside a loop.

---

## Low Findings

### 12. `GameRng::fromState()` creates a throwaway RNG instance

**File:** `app/Services/GameRng.php:28–34`

**Problem:** `fromState` calls `new self('unused')` which hashes a seed and creates a Xoshiro engine, only to immediately discard it. This is wasteful.

**Recommendation:** Use a static factory that bypasses the constructor, or use a private constructor + named constructors pattern.

### 13. `Star` model missing `homeSystem` relationship

**File:** `app/Models/Star.php`

**Problem:** `HomeSystem` belongsTo `Star`, but `Star` doesn't define a `hasOne HomeSystem` inverse. This isn't currently needed but is an incomplete relationship mapping.

### 14. ~~`Empire` model missing `gameUser` relationship~~ ✅ RESOLVED

**Resolution:** The `Empire` model now has a `player()` BelongsTo relationship to the `Player` model, with a proper FK on `player_id`. Resolved as part of finding #1.

### 15. Frontend page component is 1246 lines

**File:** `resources/js/pages/games/generate.tsx`

**Problem:** The entire generate page is a single component file. `EmpiresTable` is extracted but inline in the same file. Each section (Stars, Planets, Deposits, Home Systems, Activate, Empires) could be its own component.

### 16. `generationSteps` relationship is loaded eagerly via `$game->generationSteps` in `show()`

**File:** `app/Http/Controllers/GameGenerationController.php:164`

**Problem:** `$game->generationSteps` triggers a lazy load. While `preventLazyLoading()` may not be enabled, this should use explicit eager loading to be consistent with the rest of the method.

### 17. ~~Missing `game_user` pivot `id` column~~ ✅ RESOLVED

**Resolution:** The `game_user` pivot was promoted to a first-class `players` table with its own auto-increment `id` column. Resolved as part of finding #1.

### 18. `activate` action doesn't use a DB lock

**File:** `app/Http/Controllers/GameGenerationController.php:279–293`

**Problem:** The activate action reads the game status, checks `canActivate()`, and then saves — without acquiring a lock. Two concurrent activate requests could both succeed. While the result is idempotent (setting `active` twice is harmless), it's inconsistent with the concurrency pattern used by all other state-changing actions.

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
| 2  | Replace `$dates` with `casts()` on `HomeSystem` and `GenerationStep`                                                                         | Medium   | S      | `HomeSystem.php`, `GenerationStep.php`                                                                   |
| 3  | Extract a constant for home system capacity (25) and empire cap (250)                                                                        | Medium   | S      | `HomeSystem.php`, `EmpireCreator.php`, `GameGenerationController.php`, `generate.tsx`                    |
| 4  | Add clarifying comments on cascade deletes; add test assertions for empire/colony cleanup in delete step tests                               | Medium   | S      | `GameGenerationController.php`, `GameGenerationControllerDeleteStepTest.php`                             |
| 5  | Move JSON structure validation from controller into Form Request `after()` hooks; add JSON parse error handling                              | Medium   | S      | `UploadHomeSystemTemplateRequest.php`, `UploadColonyTemplateRequest.php`, `GameGenerationController.php` |
| 6  | Create Form Requests for `updateStar`, `updatePlanet`, `createHomeSystemManual`, `createEmpire`, `reassignEmpire`                            | Medium   | M      | New Form Request files, `GameGenerationController.php`                                                   |
| 7  | Batch-insert planets and deposits in `HomeSystemCreator::applyTemplate()` and colony inventory in `EmpireCreator::createColony()`            | Medium   | S      | `HomeSystemCreator.php`, `EmpireCreator.php`                                                             |
| 8  | Add `lockForUpdate` to the `activate` action for concurrency consistency                                                                     | Low      | S      | `GameGenerationController.php`                                                                           |
| 9  | Fix `GameRng::fromState()` to avoid throwaway constructor work                                                                               | Low      | S      | `GameRng.php`                                                                                            |
| 10 | Eager-load `generationSteps` in the `show()` method                                                                                          | Low      | S      | `GameGenerationController.php`                                                                           |
| 11 | Split `GameGenerationController` into smaller controllers (optional, do if touching these routes for other work)                             | Low      | L      | Routes, controller files                                                                                 |
| 12 | Extract frontend sections into sub-components (optional, do if modifying generate page)                                                      | Low      | M      | `generate.tsx`                                                                                           |
| 13 | Extract `show()` data preparation into private methods or use Inertia deferred props for star/planet lists                                   | Low      | M      | `GameGenerationController.php`, `generate.tsx`                                                           |
| 14 | Investigate OOM (out-of-memory) issue when running the full test suite                                                                       | Medium   | M      | `phpunit.xml`, test files                                                                                |

**Effort key:** S = < 1 hour, M = 1–3 hours, L = 3+ hours
