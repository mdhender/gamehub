# Reviews

Here's a practical plan to audit the codebase for Laravel best practices and code smells:

Phase 1 — Automated tooling (quick wins)
1. Run `vendor/bin/pint --dirty --format agent` to catch formatting issues
2. Run the full test suite (`php artisan test --compact`) to confirm everything passes as a baseline
3. Check for any deprecation warnings in logs

Phase 2 — Backend audit
1. **Models** — N+1 queries, missing `$fillable/$casts`, oversized models, missing relationships
2. **Controllers** — Fat controllers that should use form requests, policies, or service classes
3. **Migrations** — Missing indexes, foreign keys, or proper column types
4. **Routes** — Unused routes, missing middleware, missing names
5. **Queries** — Raw queries that could use Eloquent, missing eager loading
6. **Security** — Mass assignment, authorization gaps, unvalidated input

Phase 3 — Frontend audit
1. **Inertia pages** — Proper use of v3 patterns (deferred props, useForm, useHttp)
2. **Wayfinder** — Hardcoded URLs that should use generated route functions
3. **Components** — Duplicated logic, missing TypeScript types

Phase 4 — Architecture
1. Check for proper separation of concerns (no business logic in controllers/views)
2. Review job/queue pattern

## Agent prompting

```text
We prefer to work from BURNDOWN.md.
Replace the contents with these findings,s then consult the Oracle to create a plan to address.
Tasks should be ordered based on dependencies, not priorities.
They should be small and sized to fit in a coding agents context window.
They should contain enough information for the agent to find and fix the problem.
They should specify acceptance criteria or tests to verify the fix.
They should have a status that the agents can check off as they complete the work.
Add the plan to the BURNDOWN.
```
