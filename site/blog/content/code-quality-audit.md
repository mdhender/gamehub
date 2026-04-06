---
title: "Code Quality Audit: 19 Tasks in One Session"
date: 2026-04-05T21:00:00
---

{{< callout type="info" >}}
   A full code quality audit tackled 19 dependency-ordered tasks across route binding, security hardening, schema completeness, controller extraction, and cleanup. 66 files changed, 674 tests passing, zero regressions.
{{< /callout >}}

The codebase had accumulated the kind of debt that accrues naturally during rapid feature development — missing relationships, inline authorization, fat controllers, and inconsistent naming. Rather than chip away at it piecemeal, the entire backlog was inventoried, dependency-ordered into four phases, and burned down in a single session. 19 tasks, 20 commits.

---

## Phase 1 — Route Binding Foundations

Two tasks fixed the routing layer so that scoped model binding actually works.

`Turn` was missing an `empires()` relationship, which meant any route using `{game}/turns/{turn}/reports/{empire}` with `scopeBindings()` would crash. A `HasManyThrough` via `TurnReport` solved it. The `show` and `download` routes had been using `withoutScopedBindings()` plus manual `abort_unless($empire->game_id === $game->id)` checks as a workaround — both removed.

The generation sub-routes (`{game}/generate/stars/{star}`, planets, empires) had the same problem: no `scopeBindings()` on the group, manual abort checks in every controller. Adding the directive to the route group and removing the inline checks brought them in line with the rest of the app.

---

## Phase 2 — Security Hardening

Four tasks tightened validation, authorization, and rate limiting.

**Template JSON validation.** `UploadHomeSystemTemplateRequest` and `UploadColonyTemplateRequest` validated the file upload but never inspected the decoded JSON. Missing keys like `kind` or `orbit` produced raw PHP "undefined array key" errors. Both requests now validate the full nested structure — planets, deposits, population entries, inventory items — and expose a `templateData()` accessor so the controller never touches `json_decode` directly.

**FormRequest authorization.** 11 FormRequests relied on controller-level `Gate::authorize()` instead of implementing `authorize()`. Each now checks the appropriate policy (`can('update', $this->route('game'))` for game-owned resources, admin-only for invitations). `GenerationStepController::generateStars()` was using inline `$request->validate()` — replaced with a proper `GenerateStarsRequest`. `CreateEmpireRequest` gained an `exists:players,id` rule scoped to the current game.

**Verified email enforcement.** Profile edit and update routes only required `auth` middleware while profile destroy and security routes required `['auth', 'verified']`. An unverified user could modify their profile. The routes were moved into the verified middleware group.

**Rate limiting.** POST/PATCH/DELETE routes in admin and game generation had no `throttle` middleware. Named rate limiters were defined — `admin-mutations` at 10/min, `game-mutations` at 30/min — and applied to all mutation routes. GET routes remain unthrottled.

---

## Phase 3 — Model and Schema Completeness

Seven tasks filled gaps in models, factories, policies, migrations, and indexes.

**PlayerFactory.** `Player` was the only model without `HasFactory` or a factory class. Now it has both, creating a valid Game/User association with `GameRole::Player`.

**Migration hygiene.** One historical migration used `Game::all()` — if the model ever added scopes or casts, the migration would break on a fresh database. Replaced with `DB::table('games')->get()`. Four SQLite rebuild migrations wrapped their work in `Schema::disableForeignKeyConstraints()` which is a no-op inside a transaction. The redundant calls were removed; the existing `PRAGMA defer_foreign_keys = ON` already handles FK deferral correctly.

**Missing indexes.** Two new migrations added indexes on FK columns that the SQLite rebuild migrations had created via raw SQL without indexes: `colonies.empire_id`, `colonies.star_id`, `colonies.planet_id`, `colony_inventory.colony_id`, `colony_template_items.colony_template_id`, `colony_templates.game_id`, `games.status`, and four columns on turn-report sub-tables.

**Policies.** Only `GamePolicy`, `TurnReportPolicy`, and `UserPolicy` existed. Six new policies were created — `EmpirePolicy`, `StarPolicy`, `PlanetPolicy`, `HomeSystemPolicy`, `PlayerPolicy`, `InvitationPolicy` — with 400+ lines of policy tests covering admin, GM, active player, and unrelated user scenarios.

**Model cleanup.** The phantom `is_gm` boolean cast was removed from `User` — the column doesn't exist; the attribute is loaded via `withExists()` which already returns a boolean. Six TurnReport-family models gained their missing `@use HasFactory<XFactory>` annotations to match every other model in the codebase.

---

## Phase 4 — Controller Slimming and Cleanup

Six tasks extracted business logic from fat controllers and cleaned up naming.

**TemplateController** had two methods totaling 109 lines of template parsing, pay-rate calculation, and DB transactions. The logic was extracted into `ImportHomeSystemTemplate` and `ImportColonyTemplates` action classes. Controller methods dropped to under 15 lines each.

**TurnReportController::download()** was 90+ lines of inline JSON assembly with cadre detection, pay calculation, and food consumption math. All of it moved into `TurnReportJsonExporter`. The controller method is now 13 lines.

**GameGenerationController** was the largest at 339 lines with 12 private helper methods. The helpers moved into `GenerationPagePresenter`, cluster download logic into `ClusterExporter`, and game activation into an `ActivateGame` action. The controller dropped to 60 lines.

**Route naming.** Six route names used inconsistent kebab-case segments (`update-star`, `delete-step`, `empires.create`). All were normalized to dot-notation (`stars.update`, `steps.destroy`, `empires.store`). Tests and Wayfinder-generated frontend code were updated to match.

**Console cleanup.** The default `inspire` Artisan command — a Laravel starter artifact — was removed.

---

## By the Numbers

| Metric | Before | After |
|---|---|---|
| Tests | 622 | 674 |
| Assertions | 3,960 | 4,025 |
| Files changed | — | 66 |
| Lines added | — | 1,926 |
| Lines removed | — | 604 |
| Controllers > 60 lines | 3 | 0 |
| FormRequests without `authorize()` | 11 | 0 |
| Models without policies | 6 | 0 |
| FK columns without indexes | 11 | 0 |

---

## What's Next

The codebase is now structurally clean — authorization is in policies and FormRequests, business logic is in action classes and exporters, routes use scoped bindings consistently, and every mutation endpoint is rate-limited. The next focus shifts back to gameplay: wiring the inventory model into the turn-processing pipeline and building the order parser.
