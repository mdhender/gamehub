# Frontend Page and Component Architecture

**DRAFT** - must be updated for Laravel fullstack!

## Purpose

This document sketches the page, layout, routing, and component architecture for the frontend of the system.
It assumes:

- small authenticated user base
- modern desktop-first browsers, with mobile support still required
- Bun on the server
- separate backend API
- common application shell for Admin, GM, and Player roles
- a landing page and login flow for unauthenticated users
- dashboard-style application with CRUD, detail views, file/report workflows, and order validation/submission

The goal is to produce a frontend that is:

- easy to reason about
- easy to extend
- friendly to coding agents
- cleanly separated from backend concerns
- practical for a React + Vite style SPA

---

## Architectural Summary

The frontend should be structured as a client-side application with:

- a small public area
- an authenticated application shell
- role-aware navigation and route guards
- feature-oriented page modules
- shared UI primitives
- thin API clients
- explicit state boundaries

### High-level split

- **Public area**
  - Landing page
  - Login page
  - Logged-out informational pages if needed later

- **Authenticated area**
  - Shared app shell
  - Dashboard
  - Admin pages
  - GM pages
  - Player pages
  - Shared game/report/order/entity pages

---

## Frontend Responsibility Boundaries

The frontend should own:

- routing
- layout shell
- responsive navigation
- forms and local interaction state
- optimistic UI only where safe
- display of validation and processing results returned by the backend
- role-aware menus and route access behavior
- user-visible maintenance mode handling

The frontend should not own:

- business rules for game processing
- order parsing logic
- role authorization decisions
- turn execution logic
- persistent maintenance mode enforcement

Those belong to the backend.

The frontend may hide or disable actions based on role and state, but the backend remains authoritative.

---

## Route Architecture

## Public Routes

### `/`
**Page:** LandingPage

Purpose:
- hero/splash
- brief product description
- instructions for requesting an account
- convenience link to log in

Sections:
- HeroSection
- RequestAccessSection
- HowItWorksSection
- LoginCallToAction
- Footer

### `/login`
**Page:** LoginPage

Purpose:
- username/password sign-in
- show authentication errors
- redirect authenticated users to dashboard

Components:
- AuthCard
- LoginForm
- FormField components
- SubmitButton
- AuthErrorBanner

Optional later:
- forgot password flow
- password reset flow
- account status message

---

## Authenticated Route Group

All authenticated routes live beneath a shared shell.

Suggested prefix:
- `/app/*`

Examples:
- `/app/dashboard`
- `/app/games`
- `/app/orders`
- `/app/admin/users`
- `/app/gm/games/:gameId`

This keeps public and authenticated areas clearly separated.

---

## Shared Authenticated Shell

### `AppShell`

The shared shell wraps all authenticated pages.

Responsibilities:
- top bar
- sidebar on desktop
- stacked or drawer navigation on mobile
- current user display
- role badge or context if useful
- global notifications area
- maintenance banners
- page title region / breadcrumb region
- logout action

Subcomponents:
- AppHeader
- SidebarNav
- MobileNav
- MainContent
- GlobalToastRegion
- GlobalModalHost
- MaintenanceBanner
- Breadcrumbs

### Shell behavior

The shell should:
- persist while navigating between authenticated pages
- avoid full remount on route changes
- highlight active navigation entries
- collapse gracefully on small screens
- allow role-aware menu sections

---

## Route Guards and Session Handling

### Guards

Use explicit route wrappers or loader checks for:

- `RequireAuth`
- `RequireRole`
- `RequireNotMaintenance` or maintenance-aware handling

Behavior:
- unauthenticated users hitting `/app/*` go to `/login`
- authenticated users hitting `/login` go to `/app/dashboard`
- unauthorized users see an AccessDenied page or are redirected to a safe page
- non-admin users blocked during maintenance mode see a MaintenanceLocked page

### Session model

Frontend session state should contain only what the UI needs, such as:
- authenticated or not
- current user summary
- roles
- active game or selected game if applicable
- maintenance state summary if returned by the API

Do not put trust-sensitive authorization logic in the frontend.

---

## Core Page Modules

## 1. Dashboard

### Route
- `/app/dashboard`

### Page
- `DashboardPage`

### Purpose
A role-aware summary page after login.

### Common content
- welcome panel
- current user summary
- recent activity
- notifications / alerts
- shortcuts
- current maintenance status if relevant

### Role-specific cards

#### Admin cards
- users
- games
- maintenance mode
- recent errors or jobs

#### GM cards
- games I run
- current turns needing attention
- order validation issues
- turn processing actions

#### Player cards
- games I play
- current turn status
- recent reports
- draft/current orders

### Components
- DashboardGrid
- DashboardCard
- ActivityFeed
- AlertPanel
- QuickActionsPanel

---

## 2. Games

This area differs by role but should reuse shared list/detail components where practical.

### Shared routes
- `/app/games`
- `/app/games/:gameId`

### Admin routes
- `/app/admin/games`
- `/app/admin/games/new`
- `/app/admin/games/:gameId/edit`

### GM routes
- `/app/gm/games`
- `/app/gm/games/:gameId`
- `/app/gm/games/:gameId/settings`

### Player routes
- `/app/player/games`
- `/app/player/games/:gameId`

### Pages
- GamesListPage
- GameDetailPage
- GameEditPage
- GameCreatePage
- GameSettingsPage

### Shared list/detail components
- GameList
- GameListToolbar
- GameTable or GameCardList
- GameSummaryPanel
- GameStatusBadge
- GameMetadataPanel

### Detail page sections
- summary
- participants
- turn status
- reports
- orders
- controlled entities
- admin or GM actions

---

## 3. Users and Player Management

Primarily admin-facing.

### Routes
- `/app/admin/users`
- `/app/admin/users/new`
- `/app/admin/users/:userId`
- `/app/admin/users/:userId/edit`

Optional split if players are distinct from users:
- `/app/admin/players`
- `/app/admin/players/:playerId`

### Pages
- UserListPage
- UserDetailPage
- UserEditPage
- UserCreatePage

### Components
- UserTable
- UserFilters
- UserSummaryPanel
- RoleBadge
- AccountStatusBadge
- UserForm

### CRUD form sections
- account identity
- credentials or reset controls
- role assignment
- game memberships
- status flags

---

## 4. Reports

Reports are important enough to deserve a dedicated feature area.

### Shared routes
- `/app/reports`
- `/app/reports/:reportId`

### GM routes
- `/app/gm/games/:gameId/reports`
- `/app/gm/games/:gameId/reports/:reportId`

### Player routes
- `/app/player/games/:gameId/reports`
- `/app/player/games/:gameId/reports/:reportId`

### Pages
- ReportsListPage
- ReportDetailPage

### List concerns
- filter by game
- filter by turn
- filter by type
- sort by newest
- download actions

### Detail concerns
- metadata
- generated time
- source turn
- report type
- download links
- preview if text or JSON is previewable

### Components
- ReportList
- ReportTable
- ReportFilters
- ReportMetadataCard
- ReportDownloadsCard
- ReportPreviewPanel

---

## 5. Orders

This is one of the most important features in the system.

### Routes
- `/app/orders`
- `/app/orders/:orderId`
- `/app/orders/:orderId/edit`
- `/app/player/games/:gameId/orders`
- `/app/player/games/:gameId/orders/current`

For clarity, also consider a stable route for the current editable order:
- `/app/player/games/:gameId/current-orders`

### Pages
- OrdersListPage
- OrderDetailPage
- OrderEditorPage

### Order editor responsibilities
- load current order draft
- allow create/update
- display last saved timestamp
- validate against backend
- submit to backend for actual turn use
- show validation results distinctly from persistence results
- display prior orders read-only

### Recommended page layout for editor
Left or top area:
- editor text area
- order metadata
- save state

Right or bottom area:
- validation results
- parse errors
- warnings
- submission status
- server messages

### Components
- OrderEditor
- OrderTextArea
- OrderMetadataBar
- SaveStatusIndicator
- ValidateButton
- SubmitButton
- OrderResultTabs
- ParseErrorsPanel
- ValidationWarningsPanel
- SubmissionResultPanel
- PriorOrdersList
- PriorOrderPreview

### Important UX rules
- validation and submission must be separate visible actions if both exist
- validation output must be preserved long enough for the user to read it
- backend error messages should be shown with minimal distortion
- current turn and game context must always be obvious
- accidental overwrite risk should be minimized

### Strong recommendation
Treat the order editor as its own feature package, not just another generic form.

---

## 6. Entity Summary and Detail Pages

Players need summary/detail pages for things they control.
GMs may need broader visibility.

The actual entity types may vary, but the frontend should be prepared for reusable patterns.

### Routes
- `/app/entities`
- `/app/entities/:entityId`

Or nested by game:
- `/app/games/:gameId/entities`
- `/app/games/:gameId/entities/:entityId`

### Pages
- EntityListPage
- EntityDetailPage

### Components
- EntityTable
- EntitySummaryCard
- EntityStatsPanel
- EntityRelationshipsPanel
- EntityActivityPanel
- EntityOrdersPanel
- EntityReportsPanel

### Detail layout
- header with identity and status
- summary cards
- tabs or sections for:
  - details
  - orders
  - reports
  - history

---

## 7. GM Turn Management

This is another major feature area and deserves its own section.

### Routes
- `/app/gm/games/:gameId/turns`
- `/app/gm/games/:gameId/turns/:turnId`
- `/app/gm/games/:gameId/turns/:turnId/run`
- `/app/gm/games/:gameId/turns/:turnId/finalize`
- `/app/gm/games/:gameId/turns/new`

### Pages
- TurnListPage
- TurnDetailPage
- TurnRunPage
- TurnFinalizePage
- TurnCreatePage

### Workflow stages
- review submitted orders
- run parser/validation batch
- fix errors or resolve issues
- execute turn processing
- review generated outputs
- finalize results
- open next turn

### Components
- TurnSummaryPanel
- TurnStatusTimeline
- SubmittedOrdersTable
- TurnErrorsPanel
- TurnProcessingControls
- GeneratedArtifactsPanel
- FinalizationChecklist

### UX note
This area should feel procedural and safe, with strong confirmation steps for destructive or irreversible actions.

---

## 8. Maintenance Mode

### Routes
- `/app/admin/system`
- `/maintenance` or a maintenance-aware lock screen route

### Pages
- SystemSettingsPage
- MaintenanceLockedPage

### Components
- MaintenanceModeToggle
- MaintenanceStatusCard
- AffectedUsersNotice
- ConfirmDangerActionDialog

### Behavior
When maintenance mode is active:
- non-admin authenticated users should be blocked from app functionality
- the shell may be replaced by a lock screen or banner + disabled navigation
- admin users continue to work

Backend enforcement remains authoritative.

---

## Shared Layout Components

These are cross-feature structural building blocks.

### `PageLayout`
Standard page wrapper with title, subtitle, actions, body.

### `PageHeader`
Page title, breadcrumbs, action buttons.

### `SectionCard`
Generic content card.

### `SectionGrid`
Responsive grid for summary panels.

### `DataTable`
Reusable tabular list component.

### `EmptyState`
Used when there is no data.

### `LoadingState`
Used during async fetches.

### `ErrorState`
Used when fetches fail.

### `ConfirmDialog`
Reusable confirmation dialog.

### `StatusBadge`
Badge system for statuses like draft, submitted, failed, finalized, maintenance, active, inactive.

---

## Navigation Architecture

## Sidebar sections

### Common
- Dashboard
- Games
- Reports
- Orders
- Entities

### Admin
- Users
- Games Admin
- System / Maintenance

### GM
- My Games
- Turn Management
- Reports

### Player
- My Games
- Current Orders
- Reports
- Controlled Entities

Navigation should be generated from a role-aware configuration object, not hardcoded separately in multiple places.

---

## Suggested Feature-Oriented File Structure

```text
src/
  app/
    router/
      routes.tsx
      guards.tsx
    providers/
      AuthProvider.tsx
      SessionProvider.tsx
      ToastProvider.tsx
    layout/
      AppShell.tsx
      AppHeader.tsx
      SidebarNav.tsx
      MobileNav.tsx
  pages/
    public/
      LandingPage.tsx
      LoginPage.tsx
    dashboard/
      DashboardPage.tsx
    admin/
      users/
        UserListPage.tsx
        UserDetailPage.tsx
        UserEditPage.tsx
      games/
        AdminGamesPage.tsx
        AdminGameEditPage.tsx
      system/
        SystemSettingsPage.tsx
    gm/
      games/
        GMGamesPage.tsx
        GMGameDetailPage.tsx
      turns/
        TurnListPage.tsx
        TurnDetailPage.tsx
        TurnRunPage.tsx
        TurnFinalizePage.tsx
    player/
      games/
        PlayerGamesPage.tsx
        PlayerGameDetailPage.tsx
      orders/
        CurrentOrdersPage.tsx
    shared/
      reports/
        ReportsListPage.tsx
        ReportDetailPage.tsx
      orders/
        OrdersListPage.tsx
        OrderDetailPage.tsx
        OrderEditorPage.tsx
      entities/
        EntityListPage.tsx
        EntityDetailPage.tsx
  features/
    auth/
      api.ts
      hooks.ts
      components/
    users/
      api.ts
      hooks.ts
      components/
    games/
      api.ts
      hooks.ts
      components/
    reports/
      api.ts
      hooks.ts
      components/
    orders/
      api.ts
      hooks.ts
      components/
    turns/
      api.ts
      hooks.ts
      components/
    entities/
      api.ts
      hooks.ts
      components/
    system/
      api.ts
      hooks.ts
      components/
  components/
    ui/
      Button.tsx
      Input.tsx
      Card.tsx
      Badge.tsx
      Dialog.tsx
      Table.tsx
      Tabs.tsx
      TextArea.tsx
    common/
      PageLayout.tsx
      PageHeader.tsx
      LoadingState.tsx
      ErrorState.tsx
      EmptyState.tsx
      ConfirmDialog.tsx
  lib/
    api/
      client.ts
      errors.ts
    auth/
      session.ts
    formatting/
      dates.ts
      statuses.ts
```

This structure keeps:
- pages for route-level composition
- features for domain-specific UI and data access
- shared UI primitives separate from feature code

---

## State Management Guidance

Use the smallest tool that works.

### Local component state
Use for:
- form fields
- open/closed panels
- tab selection
- editor transient state

### Shared app state
Use for:
- session/user summary
- maintenance banner state
- global notifications
- maybe selected game context

### Server state
Use a dedicated server-state library if desired for:
- caching list/detail fetches
- mutation invalidation
- background refetch

Examples include TanStack Query, but the important point is the separation between:
- client UI state
- server-backed resource state

Do not invent a global state store for everything unless complexity proves it necessary.

---

## API Client Design

The frontend should use thin API modules per feature.

Example pattern:
- `features/orders/api.ts`
- `features/orders/hooks/useCurrentOrder.ts`
- `features/orders/hooks/useValidateOrder.ts`

Avoid scattering raw fetch calls across pages.

### API client responsibilities
- attach auth credentials
- normalize transport errors
- return typed DTOs
- keep request shape explicit

### API modules should not contain UI logic.

---

## Error and Feedback Model

User feedback must be predictable.

### Page-level fetch errors
Use in-page error states.

### Mutation success/failure
Use inline feedback plus toasts where helpful.

### Dangerous actions
Require confirmation dialogs.

### Validation results
For order parsing and submission, show structured results:
- errors
- warnings
- informational notes
- success state

Do not collapse all backend feedback into generic toast messages.

---

## Mobile and Responsive Behavior

Desktop is primary, but responsive support should be deliberate.

### Desktop
- persistent sidebar
- wider tables
- side-by-side panels for editor/result workflows

### Mobile
- drawer or stacked navigation
- cards instead of wide tables where needed
- vertically stacked order editor and validation panels
- sticky action bar for save/validate/submit if useful

The most important goal is not feature parity of layout but preservation of task clarity.

---

## Suggested Initial MVP Page Set

To keep the first version practical, I would prioritize:

### Public
- LandingPage
- LoginPage

### Shared authenticated
- DashboardPage
- GamesListPage
- GameDetailPage

### Player
- ReportsListPage
- ReportDetailPage
- OrdersListPage
- OrderEditorPage
- EntityListPage
- EntityDetailPage

### GM
- GMGamesPage
- TurnDetailPage
- TurnRunPage
- TurnFinalizePage

### Admin
- UserListPage
- UserEditPage
- AdminGamesPage
- SystemSettingsPage

Everything else can grow from that spine.

---

## Strong Design Recommendations

1. **Use one shared authenticated shell.**
   Do not create separate visual applications for Admin, GM, and Player.

2. **Keep route-level pages thin.**
   Pages compose feature components and data hooks; they should not accumulate business logic.

3. **Treat orders and turns as first-class features.**
   They are the heart of the application, not generic CRUD.

4. **Make role-aware navigation declarative.**
   Define menu items and permissions in configuration.

5. **Preserve backend authority.**
   Frontend checks improve UX; backend checks enforce truth.

6. **Optimize for clarity over cleverness.**
   This audience needs reliable workflows more than fashionable frontend patterns.

---

## Open Questions to Resolve Next

1. What exact entity types need summary/detail pages?
2. Are reports downloadable only, previewable inline, or both?
3. Are orders plain text only in v1, or will there be structured helpers later?
4. Can a user belong to multiple roles at once?
5. Does a GM also act as a player in some games?
6. How should current game context be selected and displayed?
7. What actions are reversible versus irreversible in turn management?
8. What does maintenance mode allow besides admin access?
9. Will the frontend need job progress or polling for long-running turn operations?
10. Is audit/history visible in the UI for admin and GM actions?

---

## Conclusion

The frontend should be designed as a role-aware authenticated dashboard application with a shared shell, feature-oriented modules, and especially careful treatment of orders and turn-management workflows.

This architecture fits a React + Vite style SPA very naturally, keeps the frontend focused on presentation and interaction, and gives the backend full authority over game rules, parsing, validation, and turn execution.

