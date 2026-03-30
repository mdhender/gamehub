# Burndown — Laravel Best Practices & Code Smells

Findings from a full codebase review. Tackle in chunks as time permits.

## High Severity

- [x] **N+1 on every request** — `HandleInertiaRequests::share()` calls `$user->isGm()` which queries the DB on every Inertia request. Cache the value in the session at login or use `loadExists()`.
  - `app/Http/Middleware/HandleInertiaRequests.php` L44
- [x] **Bypasses validated data** — `SecurityController::update()` accesses `$request->password` instead of `$request->validated('password')`.
  - `app/Http/Controllers/Settings/SecurityController.php` L53
- [x] **Mailable missing ShouldQueue** — `InvitationMail` is dispatched with `->queue()` but does not implement `ShouldQueue`.
  - `app/Mail/InvitationMail.php` L12

## Medium Severity

- [x] **Phantom cast** — `User` model casts `'is_gm' => 'boolean'` but no `is_gm` column exists in the database. Remove it.
  - `app/Models/User.php` L33
  - Cast is intentionally retained: the N+1 fix uses `loadExists(['games as is_gm' => ...])` in `HandleInertiaRequests`, which dynamically sets this attribute. The boolean cast ensures 0/1 from the EXISTS query is properly serialised as `true`/`false` in the Inertia payload.
- [x] **Route outside admin middleware** — `admin/users/{user}` is outside the `admin` middleware group, exposing admin UI layouts to non-admin users viewing their own profile.
  - `routes/admin.php` L16
- [ ] **Missing preventLazyLoading** — `AppServiceProvider` does not call `Model::preventLazyLoading(!app()->isProduction())` to catch N+1 queries during development.
  - `app/Providers/AppServiceProvider.php`
- [ ] **SELECT * on eager load** — `GameController::show()` calls `$game->load('users')` loading all columns. Constrain to `$game->load('users:id,name,email')`.
  - `app/Http/Controllers/GameController.php` L41
- [ ] **Missing index on user_id** — `game_user` table uses composite PK `(game_id, user_id)` but has no standalone index on `user_id`, causing full table scans for reverse lookups.
  - `database/migrations/2026_03_29_024414_create_game_user_table.php` L16

## Low Severity

- [ ] **Duplicated GM-check logic** — `GamePolicy::viewAny()` manually queries pivot instead of calling `$user->isGm()`.
  - `app/Policies/GamePolicy.php` L15–19
- [ ] **Inline validation** — `GameMemberController::store()` uses inline validation. Extract to a `StoreGameMemberRequest` Form Request.
  - `app/Http/Controllers/GameMemberController.php` L19–27
- [ ] **Redundant Hash::make** — `CreateAdminUser` command wraps password in `Hash::make()` but the model's `hashed` cast already handles this.
  - `app/Console/Commands/CreateAdminUser.php` L30
- [ ] **Wrong PHPDoc** — `UserFactory::admin()` docblock says "email should be unverified" instead of "user has admin privileges".
  - `database/factories/UserFactory.php` L43

## Test Improvements

All of these test files use `RefreshDatabase` instead of the faster `LazilyRefreshDatabase`:

- [ ] `tests/Feature/CreateAdminUserTest.php` — also uses `test_*` naming instead of `#[Test]` attributes, and `assertEquals` instead of `assertSame`
- [ ] `tests/Feature/ExampleTest.php`
- [ ] `tests/Unit/ExampleTest.php`
- [ ] `tests/Feature/Settings/ProfileUpdateTest.php`
- [ ] `tests/Feature/Settings/SecurityTest.php`
- [ ] `tests/Feature/Auth/AuthenticationTest.php`
- [ ] `tests/Feature/Auth/EmailVerificationTest.php`
- [ ] `tests/Feature/Auth/PasswordConfirmationTest.php`
- [ ] `tests/Feature/Auth/PasswordResetTest.php`
- [ ] `tests/Feature/Auth/RegistrationTest.php`
- [ ] `tests/Feature/Auth/TwoFactorChallengeTest.php`
- [ ] `tests/Feature/Auth/VerificationNotificationTest.php`
