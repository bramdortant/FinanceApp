# Implementation Plan

## Stack Decision

- **Backend**: Laravel (PHP)
- **Frontend**: Vue.js (via Inertia.js — no separate API needed)
- **Database**: SQLite (one file, zero setup)
- **AI**: OpenAI API (for auto-categorization)
- **Styling**: Tailwind CSS (comes with Laravel)
- **Charts**: ApexCharts (via vue3-apexcharts)
- **Hosting**: Oracle Cloud Free Tier (later)
- **Local dev**: Docker (same setup locally and in production)
- **Containerization**: Docker + Docker Compose
- **UI Language**: Dutch — all user-facing text in Dutch, code and
  database values in English

## Phase 0: Project Setup

### 0.1 Initialize Laravel + Inertia + Vue

Create a fresh Laravel project with the official starter kit that includes
Inertia + Vue + Tailwind out of the box. All commands run inside Docker —
you do NOT need PHP, Node, or Composer installed on your laptop.

```bash
# Step 1: Set up Docker files first (Dockerfile, docker-compose.yml, nginx.conf)
# Step 2: Start the containers
docker compose up -d

# Step 3: Install Laravel inside the container
docker compose exec app composer create-project laravel/laravel . --prefer-dist

# Step 4: Install the Breeze starter kit with Vue + Inertia
docker compose exec app php artisan breeze:install vue

# Step 5: Install npm packages
docker compose exec node npm install
```

This gives us:

- Laravel backend (routes, controllers, models, migrations)
- Vue frontend (pages, components) via Inertia
- Tailwind CSS (styling)
- Basic authentication (login/register — useful for securing the app)
- Vite (builds Vue files into JavaScript the browser understands)

### 0.2 Docker Setup

We use Docker so the same setup runs on your laptop AND on the Oracle Cloud
server later. No "it works on my machine" problems.

**What Docker gives us:**

- PHP, Nginx, Node — all defined in files, not installed on your system
- One command to start everything: `docker compose up`
- Update PHP version = change one line in a file, rebuild
- Same environment locally and in production

**Files to create:**

**`Dockerfile`** — defines the PHP + Node environment:

```dockerfile
FROM php:8.4-fpm

# Install system dependencies and PHP extensions
# (SQLite support, etc.)

# Install Node.js (for building Vue)
# Install Composer (for PHP packages)

# Set working directory to /var/www
```

**`docker-compose.yml`** — defines all services:

```yaml
services:
  app:
    # PHP-FPM — runs Laravel
    build: .
    volumes:
      - .:/var/www          # Your code is shared with the container
    ports:
      - "9000:9000"

  nginx:
    # Web server — receives browser requests, forwards to PHP
    image: nginx:alpine
    ports:
      - "8000:80"           # Open http://localhost:8000 in browser
    volumes:
      - .:/var/www
      - ./docker/nginx.conf:/etc/nginx/conf.d/default.conf

  node:
    # Vite dev server — hot-reloads Vue changes during development
    image: node:20-alpine
    volumes:
      - .:/var/www
    ports:
      - "5173:5173"         # Vite dev server
    command: npm run dev
```

**`docker/nginx.conf`** — tells Nginx how to serve the Laravel app.

**How it works (simplified):**

```text
Your browser → http://localhost:8000
                    ↓
              [Nginx container]  "Is this a PHP request?"
                    ↓ yes                    ↓ no
            [PHP-FPM container]      serves static files
            runs Laravel code        (CSS, JS, images)
                    ↓
              [SQLite file]
              (on your disk, shared with container via volume)
```

The SQLite database file lives on YOUR disk (not inside the container),
shared via a Docker volume. This means:

- Your data survives container restarts and rebuilds
- You can back it up by just copying the file
- You can inspect it with any SQLite tool

**Daily usage:**

```bash
# Start everything (first time takes a few minutes to build)
docker compose up -d

# Run Laravel commands (like migrations)
docker compose exec app php artisan migrate

# Install a new PHP package
docker compose exec app composer require some/package

# Install a new npm package
docker compose exec node npm install some-package

# Stop everything
docker compose down

# Update PHP version: change Dockerfile, then:
docker compose build
docker compose up -d
```

### 0.3 Configure environment files

**.env.example** (committed to git — holds placeholders):

```env
APP_NAME=FinanceApp
APP_ENV=local
APP_KEY=
APP_DEBUG=true
APP_URL=http://localhost:8000

DB_CONNECTION=sqlite
# No other DB_ variables needed — SQLite uses a single file at database/database.sqlite

OPENAI_API_KEY=your-openai-api-key-here
OPENAI_MODEL=gpt-4o-mini
```

**.env** (NOT committed — holds your real secrets):

```env
APP_NAME=FinanceApp
APP_ENV=local
APP_KEY=base64:generated-automatically
APP_DEBUG=true
APP_URL=http://localhost:8000

DB_CONNECTION=sqlite

OPENAI_API_KEY=sk-your-actual-key-here
OPENAI_MODEL=gpt-4o-mini
```

**.gitignore** already includes `.env` by default in Laravel.

### 0.4 Configure SQLite

```bash
# Create the database file
touch database/database.sqlite

# Run migrations (inside the container)
docker compose exec app php artisan migrate
```

That is it. The database is ready.

### 0.5 Set up GitHub repository

```bash
# Push to GitHub (repo should already exist or create it)
gh repo create ZeroPlexBV/FinanceApp --private --source=. --push
# Or if using personal account:
gh repo create BDortant/FinanceApp --private --source=. --push
```

Enable CodeRabbit on the repository for automatic PR reviews.

### 0.6 Running locally

```bash
# Start all containers (Laravel + Nginx + Vite)
docker compose up -d

# Open in your browser:
# → http://localhost:8000
```

That is it. One command starts everything.

To test on your phone (same WiFi network):

```bash
# The app is already accessible on your network.
# Find your laptop's IP:
hostname -I

# Then open http://<your-laptop-ip>:8000 on your phone
# Example: http://192.168.1.42:8000
```

Note: Your laptop must be running for local access. Once we deploy to
Oracle Cloud (Phase 11), the app runs 24/7 independently.

---

## Development Workflow

### Branch strategy

```text
production (live on Oracle Cloud — only receives tested code)
  ↑ merge when ready
development (active work — feature branches merge here)
  └── feature/xxx (one branch per feature, PR with CodeRabbit review)
```

Two long-lived branches:

- **development**: Where all active work happens. Feature branches are
  created from and merged back into development. Safe to break — this
  is your local/testing environment
- **production**: The code running on Oracle Cloud. Only receives merges
  from development when a feature is complete and tested. This protects
  live data from half-finished features or refactors

Both branches are protected: no direct pushes, no deletion. All changes
go through PRs with CodeRabbit review.

**Note**: During early development (before Phase 11 deployment), we use
`main` as the single branch. The `development` + `production` split
happens in Phase 11 when we actually deploy.

### PR workflow

1. Create feature branch: `git checkout -b feature/categories`
2. Implement the feature
3. Push and create PR: `gh pr create`
4. CodeRabbit reviews automatically
5. Fix any CodeRabbit feedback
6. Merge to development
7. When ready for production: create PR from development → production

---

## Architecture & Patterns

We follow established patterns to keep the code organized, secure,
and maintainable as the app grows.

### MVC (Model-View-Controller)

The core pattern of the application:

- **Models** (`app/Models/`): Represent database tables. Handle
  relationships, data casting, and define which fields can be filled.
  Example: `Account`, `Category`, `Transaction`
- **Controllers** (`app/Http/Controllers/`): Handle HTTP requests.
  Receive input, call the model, and return a response (either a
  page or a redirect). Each controller maps to one resource.
  Example: `AccountController` handles all account CRUD operations
- **Views** (`resources/js/Pages/`): Vue components rendered via
  Inertia. Display data and capture user input. No business logic
  here — just presentation

### Form Requests

Validation logic lives in dedicated Form Request classes
(`app/Http/Requests/`), not in controllers. This keeps controllers
thin and makes validation rules reusable and testable.
Example: `CategoryRequest` validates name, color, and prevents
circular parent chains.

### Resource Controllers

Controllers follow Laravel's resource convention with standard method
names: `index`, `create`, `store`, `edit`, `update`, `destroy`. This
gives predictable URLs and method names across all resources.

### Middleware

Cross-cutting concerns (authentication, auto-login, flash messages)
are handled by middleware — code that runs before/after every request
without cluttering controllers.

### Service Classes (from Phase 4a onward)

When business logic gets complex (CSV parsing, AI categorization),
it moves into service classes (`app/Services/`). Controllers stay
thin — they call the service and return the result. This keeps each
class focused on one responsibility.

---

## Incremental Feature Phases

Each phase is one or more PRs. We build the app layer by layer — each phase
results in something you can actually use and test.

### Phase 1: Database Foundation

**Branch**: `feature/database-schema`

Create the database tables (migrations) and models:

- **accounts** table: id, name, type (checking/savings/cash), starting_balance,
  currency (default EUR), icon, created_at, updated_at
- **categories** table: id, name, parent_id (nullable, for nesting later),
  color (for charts), created_at, updated_at
- **transactions** table: id, account_id, date, description, amount,
  original_description (raw from CSV), category_id (nullable — uncategorized
  initially), type (income/expense/transfer), transfer_to_account_id (nullable,
  for transfers), notes, csv_import_hash (for duplicate detection),
  created_at, updated_at
- **transaction_splits** table: id, transaction_id, category_id, amount,
  created_at, updated_at (for splitting one transaction across categories)
- **csv_imports** table: id, filename, imported_at, row_count (track what
  was imported, helps with duplicate awareness)
- **category_rules** table: id, match_pattern, category_id, created_at,
  updated_at (for auto-matching, e.g. "Albert Heijn" → Groceries)

**Eloquent models** with relationships:

- Account hasMany Transactions
- Category hasMany Transactions, belongsTo parent Category, hasMany CategoryRules
- Transaction belongsTo Account, belongsTo Category, hasMany TransactionSplits
- TransactionSplit belongsTo Transaction, belongsTo Category
- CategoryRule belongsTo Category

**Important**: Transfers are stored as a single transaction with type "transfer",
amount is negative on source account, and transfer_to_account_id points to the
destination. Transfers are excluded from income/expense charts.

**Deliverable**: Database ready, models created, no UI yet.

### Phase 2: Account Management + Category Management (CRUD)

**Branch**: `feature/accounts-and-categories`

Two simple CRUDs — the building blocks everything else depends on.

**Accounts:**

- **Account list page**: See all accounts with current balance
- **Create account**: Name, type (checking/savings/cash), starting balance
- **Edit/delete account**

**Categories:**

- **Category list page**: See all categories in a list/grid
- **Create category**: Form with name and color picker
- **Edit category**: Change name/color, optional parent category selector
- **Delete category**: With confirmation (only if no transactions linked)
- **Loop prevention**: Server-side validation rule (Form Request) to block
  circular parent chains + UI dropdown filtering to exclude invalid parents

This is the simplest feature — a good first experience with Vue + Inertia.

**Cleanup from Phase 0:**

- Set up Dutch localization (config/app.php locale, date/currency formatting)

**README update**: Replace default Laravel README with project
description, Docker setup instructions, and how to run migrations.

**Deliverable**: You can manage your accounts and spending categories.

### Phase 3: Account-Centric Transaction UX

**Branch**: `feature/transactions`

This phase introduces the **account-centric UX** that becomes the heart
of the app. Manual transaction entry is intentionally a *secondary* path
(most transactions will come from CSV import in Phase 4), but it needs
to be **fast and intuitive** for cash, corrections, and edge cases.

#### Design philosophy

- **Account is the home screen**: You click an account, you're "in" it.
  Everything you do there — add, transfer, view — is implicit to that
  account. No repeated account selection.
- **Minimal manual actions**: Big +/− buttons, keyboard-first modals,
  smart defaults, clone existing transactions to skip typing.
- **Designed for future expansion**: The transaction list and quick-add
  modal are built as reusable components so Phase 5 (categorization
  workflow), Phase 7 (dashboard records list with sort/group), and
  Phase 8 (transaction splitting) can extend them without rewrites.

#### Carryover items from Phase 2 (discovered during Phase 3 design)

These came out of the design evolution and live in Phase 3 because
they're prerequisites for the new UX:

- **Account `show` route**: Re-add the `show` route to the accounts
  resource (excluded in Phase 2). The account detail page becomes the
  primary navigation target.
- **`accounts.iban` field**: Add a nullable `iban` column to accounts
  via migration. Required as preparation for Phase 4 transfer detection.
  Use Laravel's `encrypted` cast (see Encryption section below).
- **`categories.type` field**: Add a `type` enum column (`income` /
  `expense`) to categories. Income and expense categories are kept
  separate because they don't overlap (Salary is never an expense,
  Boodschappen is never income). Defaults to `expense`.

#### Account index — the launch pad

The Rekeningen page becomes the home screen of the app:

- One card per account (current balance, name, type)
- An **"Alle rekeningen"** card at the top showing the combined total
  across all accounts (read-only — clicking it goes to a combined
  transaction view, no add buttons)
- Clicking an account card → navigates to that account's show page

#### Account show page — the center of gravity

Layout:

- **Header**: account name, current balance (large), starting balance
  (small, secondary)
- **Action row** (right under header): four buttons
  - Green **"+ Inkomsten"** — opens quick-add modal in income mode
  - Red **"− Uitgaven"** — opens quick-add modal in expense mode
  - **"⇄ Overboeken"** — opens transfer modal
  - **"📋 Klonen"** — opens clone picker
- **Transaction list**: all transactions for this account, newest first.
  Built as a reusable `TransactionList.vue` component.
- **Edit / delete account**: small icons in a corner of the header.
  Edit only allows changing `name` and `starting_balance`. The `type`
  field is locked after creation to prevent confusion. Delete only
  works when no transactions exist (already enforced from Phase 2).

#### Quick-add modal (the +/− buttons)

The fastest possible manual entry path:

- Opens with **amount field auto-focused** so you can immediately type
- Fields visible by default: Amount, Description, Category (filtered to
  income or expense based on which button you clicked), Date (defaults
  to today)
- Income vs expense is implicit from the button — no toggle in the form
- **Enter submits**, Escape cancels — never need the mouse for cash entry
- Category dropdown is built as a reusable component so Phase 5 can add
  smart auto-selection (description-to-category memory) and Phase 5/6
  can add numeric keyboard shortcuts (1/2/3 to pick) without rewriting it

#### Clone modal (the 📋 button)

Searchable picker for one-tap repeat entries:

- Opens with **search box auto-focused**
- Below: list of past transactions, **newest first**, each row showing
  description + amount + category color dot + relative date
  ("2 dagen geleden")
- **Arrow keys** navigate the list, **Enter** picks
- Picking a transaction → opens the quick-add modal pre-filled with that
  transaction's description and category, amount field auto-focused so
  you can immediately overwrite it
- Common case (clone the most recent): one click + Enter + new amount + Enter

#### Transfer modal (the ⇄ button)

- Layout: `[Huidige rekening] ⇄ [Andere rekening ▼]`
- The ⇄ button swaps which side is source vs destination
- Fields: amount, date, optional notes
- No category, no type selector — transfers are always type=transfer
- Saves as a single transfer-type transaction with `transfer_to_account_id`

#### Alle rekeningen view

Read-only combined transaction list across all accounts. Phase 3
implementation is intentionally minimal:

- Reuses the `TransactionList.vue` component
- No add/transfer buttons (you'd need to pick an account first, which
  defeats the purpose)
- Real dashboard features (totals per week/month/year, average-based
  budget hints, sort/group by date or category) → **Phase 7**, not now

#### Encryption of sensitive financial data

Since we're handling financial data on a self-hosted server, we encrypt
the high-sensitivity fields at rest using Laravel's `encrypted` cast
(AES-256, keyed off `APP_KEY`).

**What gets encrypted:**

- `accounts.iban` (Phase 3)
- `transactions.counterparty_iban` (Phase 4)
- `transactions.counterparty_name` (Phase 4)

**What stays plaintext:**

- Amounts, dates, descriptions, categories, account names, transaction
  types — operational data we need to query and aggregate. Encrypting
  these would break filtering, sorting, and dashboard aggregations.

**Trade-offs you should know about:**

- We can't `WHERE iban = '...'` in SQL — comparisons happen in PHP code
  after loading. Fine for personal-app data volumes.
- **Losing `APP_KEY` means losing all encrypted data forever.** Phase 10
  (Security Audit) documents the backup procedure and Phase 11
  (Deployment) includes the step of actually backing it up separately
  from the database.
- Encryption defends against database file leaks, backup leaks, and
  cloud provider snooping. It does NOT defend against full server
  compromise — but no app-level encryption can.

#### Other Phase 3 deliverables

- **TransactionType enum**: PHP 8.1+ backed enum for the `type` field
  (`income`, `expense`, `transfer`) — IDE autocompletion and type safety
- **TransactionController**: full resource controller with `index`,
  `create`, `store`, `edit`, `update`, `destroy`. The `index` is
  account-scoped (nested route).
- **Routing**: nested resource routes —
  `/accounts/{account}/transactions/...` for account-scoped actions
- **TransactionRequest** form request with validation including:
  account_id exists, amount precision, type in enum, transfer
  consistency rules (transfer_to_account_id required when type=transfer
  and must differ from account_id)
- **Splitting-aware list design**: `TransactionList.vue` is built to
  show split transactions later (parent row + indented sub-rows) even
  though splitting itself lands in Phase 8

**Accessibility pass**: Add ARIA attributes across all existing and new
UI components (`role="alert"` on flash messages, `aria-label` on color
swatches, semantic landmarks, modal focus traps, keyboard navigation).
Deferred from Phase 2 to do a single comprehensive pass once more UI
exists.

**README update**: Add account-centric workflow, transaction entry,
transfer usage, and a note about IBAN encryption (for transparency).

**Deliverable**: A fast, intuitive, account-centric experience for
viewing accounts, entering transactions (cash/manual), cloning past
entries, and making transfers — all designed to extend cleanly into
Phase 4 (CSV import) and beyond.

### Phase 4a: CSV Import

**Branch**: `feature/csv-import`

- **Upload page**: Drag-and-drop file picker for CSV. The target account
  is **auto-detected from the IBAN inside the CSV** (Rabobank exports
  include the owner IBAN). If the IBAN matches an existing account, use
  it. If not, show an inline form to create the missing account(s) with
  the IBAN pre-filled — the user fills in name, type, and starting
  balance, then continues to the preview. A cancel button aborts the
  import and cleans up the stashed file. (NOTE: the inline account
  creation step may be removed in a future phase if the workflow proves
  unnecessary.)
- **CSV preview**: Show parsed rows before importing (so you can verify)
- **Rabobank-only parsing**: Column mapping is hardcoded for Rabobank
  CSV format. A flexible column mapping UI for other banks (ING, ABN)
  is deferred — see nice-to-haves after Phase 9b
- **Automatic transfer detection** (uses Phase 3's IBAN field): When
  parsing a row, check if the counterparty IBAN matches another account
  the user owns in this system. If yes, create a transfer-type
  transaction instead of a regular expense/income. When the matching
  CSV (the other side of the transfer) is later imported, detect the
  duplicate (matching amount + date + IBAN, opposite direction) and
  skip it. This handles real-world cases like:
  - Auto-transfer rules ("if checking > 3k EUR, move overflow to savings")
  - Recurring scheduled transfers (e.g. fixed amount to a stocks account)
  - Manual one-off transfers between own accounts
- **Rabobank-specific**: Map Naam tegenpartij → counterparty_name,
  Tegenrekening IBAN → counterparty_iban, Saldo na trn → balance_after,
  Code → transaction_code. Concatenate Omschrijving 1/2/3 into
  original_description. Remaining fields stored in original_description
  as a fallback
- **Duplicate detection**: Hash each row (date + amount + description) and
  check against existing transactions. Show duplicates with option to
  skip them
- **Import confirmation**: Show summary (X new, Y duplicates skipped)

**README update**: Add CSV import instructions and supported bank formats.

**Deliverable**: Upload a bank CSV and import transactions into a specific
account.

### Phase 5: Transaction Categorization Workflow

**Branch**: `feature/categorization`

#### Part 1: Prerequisite cleanup — remove category hierarchy

Phase 2 built a `parent_id` column and parent/child relationships on
categories, anticipating a hierarchical tree display (originally planned
as Phase 9). After re-evaluation, flat categories + transaction splitting
(Phase 6) covers the real use case better — for example, "Alcoholische
dranken" should be a standalone category, not a child of "Boodschappen",
because you also buy alcohol at the liquor store. Remove:

- **Migration**: Drop the `parent_id` column from `categories`
- **Model**: Remove `parent()` and `children()` relationships from
  `Category`; remove `parent_id` from `$fillable`
- **Controller**: Remove `parentCategories` from `create()`/`edit()`,
  remove `getDescendantIds()`, remove children-count guard from
  `destroy()`, remove `->with('parent')` / `->withCount('children')`
- **CategoryRequest**: Remove `parent_id` validation rule and
  `wouldCreateLoop()` method
- **Create.vue / Edit.vue**: Remove the "Hoofdcategorie" dropdown and
  `parent_id` from the form
- **Index.vue**: Remove the "Hoofdcategorie" column and
  `children_count` references from the delete button

#### Part 2: Make category mandatory on all transactions

Every transaction must have a category — no uncategorized transactions
in the database. This eliminates the need for a separate "uncategorized
view" and categorization workflow after the fact.

- **Migration**: Make `category_id` non-nullable on `transactions`
  (after assigning a default to any existing uncategorized rows)
- **System category**: Create a hidden "Overboeking" category for
  transfers. This category does not appear in the normal category
  management UI or in dropdowns for income/expense. Transfers get
  this category automatically
- **Quick-add modal**: Already has a category dropdown — no change
  needed, just make it required
- **Edit modal**: Category can be changed but not unset

#### Part 3: Categorization during CSV import

Move categorization into the import flow — assign categories on the
preview page before confirming the import. The import cannot be
confirmed until every row has a category.

- **Category column on preview**: Each row in the CSV preview gets a
  category picker. Auto-matched rows are pre-filled; unmatched rows
  are highlighted and need manual assignment
- **Keyboard-driven assignment**: The active row is highlighted.
  Arrow keys navigate between uncategorized rows. Categories are
  numbered (1–9, 0) for quick selection. Pressing a number assigns
  the category and advances to the next uncategorized row
- **Category rules**: Pattern-based auto-matching. "If description
  contains 'Albert Heijn' → Boodschappen." Rules are applied
  automatically during import preview — matched rows arrive
  pre-categorized
- **Learning over time**: Each manual assignment during import can
  optionally create a rule ("Always categorize this as...?"). Over
  successive imports, more and more rows are auto-matched:
  - First import: 0% auto-matched, all manual
  - After a few imports: 80%+ auto-matched
  - With AI (Phase 9): 95%+ auto-matched

#### Part 4: Category rule management

Rules need to be editable to prevent wrong matches from persisting.

- **Rule list page**: Show all rules with their pattern, target
  category, and match count (how many transactions matched)
- **Edit a rule**: Change the pattern or the target category
- **Delete a rule**: Remove it entirely
- **Override prompt**: When a user changes the category on a
  transaction that was auto-matched by a rule, ask: "Regel
  aanpassen, of alleen deze transactie?" (Update the rule, or
  just this one?)
- **Conflict detection**: If two rules could match the same
  description, show a warning. Use the most specific match (longest
  pattern) as the default
- **Correction tracking**: Every manual category change (whether it
  updates a rule or not) is stored as a correction. These feed into
  AI context in Phase 9

**README update**: Add categorization workflow overview.

**Deliverable**: Every transaction has a category. CSV import includes
inline categorization with keyboard-driven assignment and auto-matching
rules that improve over time.

### Phase 5b: Rule Review Screen

**Branch**: `feature/rule-review`

After assigning categories to all rows on the CSV import preview, a
second screen appears before the import is confirmed. This screen
shows all unique counterparty/description → category assignments and
lets the user decide which ones to save as persistent rules.

- **Rule proposals**: Each unique pattern → category pair from the
  import gets a row showing: the suggested pattern, the assigned
  category (with color swatch), and the number of rows that matched
- **Checkbox toggle**: Each rule is pre-checked for creation. Enter
  toggles + advances to next row. Space toggles without advancing.
  Arrow keys navigate between rows
- **Pattern editing**: An edit button per row to clean up the
  pattern (e.g. remove timestamps, store numbers from
  "Albert Heijn Amsterdam 14:32" → "Albert Heijn")
- **Confirm button**: Creates the selected rules and imports the
  transactions in one action

This replaces the inline "Altijd categoriseren als...?" prompt that
was removed in Phase 5. The two-step flow keeps categorization fast
(no interruptions) while still capturing rules.

**Deliverable**: Rules are created in bulk after categorization,
with the ability to review and edit patterns before saving.

### Phase 6: Transaction Splitting

**Branch**: `feature/transaction-splitting`

- **Split UI**: On a transaction, click "Split" → add rows with category
  \+ amount. Amounts must add up to the original total
- **Split display**: In the transaction list, show split transactions
  with their categories

**Validation strategy** (defense-in-depth, lesson from Phase 5b):
- **Frontend live**: as the user edits split amounts, show a running
  remaining/over indicator. Disable submit when sum ≠ original.
- **Backend**: validate sum equality in `TransactionSplitRequest`.
  Don't trust the frontend math — a malformed client could submit
  splits that don't sum.
- **Database** (added in Phase 8 schema hardening): consider a CHECK
  constraint or a trigger that enforces sum equality at the DB layer.
  At minimum a `NOT NULL` on `amount` per split row.

**Deliverable**: Split a single transaction across multiple categories.

**Nice-to-have from Phase 5b**: multi-rule split suggestion. When two
active rules both match a transaction's description (e.g. "Albert Heijn"
→ Boodschappen and "Albert Heijn" → Alcohol), instead of just picking
the longest pattern, offer to split the transaction between the two
matched categories. The user only needs to set the amounts — the
categories are pre-filled from the rules. During CSV import, this could
surface as a "split suggestion" on the preview screen: highlight the
row, show both matched rules, and let the user set the split. This
connects Phase 5's rule system with Phase 6's splitting in a way that
reduces manual work for recurring mixed-category purchases. Note: this
only works correctly because the matcher (fixed in Phase 5b) now
considers `counterparty_name` — without that fix, most rules wouldn't
even match the second time around, so the "two rules match" scenario
would be extremely rare.

### Phase 7: Transaction Buckets

**Branch**: `feature/buckets`

Group transactions into named buckets to track total spending on a
specific purpose — for example a holiday, a renovation, a birthday
party, or a side project. Unlike categories (which are permanent and
reusable), buckets are one-off collections tied to a specific event or
goal.

- **Bucket CRUD**: Create a bucket with a name, optional description,
  and optional target amount (e.g. "holiday budget: € 2.000"). Bucket
  names follow the same uniqueness pattern as `CategoryRule.match_pattern`
  (introduced in Phase 5b): case-insensitive uniqueness with display
  case preserved. Use a `Bucket::upsertByName()` helper modelled on
  `CategoryRule::upsertByPattern()`. If we end up with a third use case
  (Phase 10b's recurring transactions), extract a trait/concern.
- **Assign transactions**: Add existing transactions to a bucket. A
  transaction can belong to at most one bucket (keeps the model simple;
  revisit if needed)
- **Bucket overview page**: Show all transactions in a bucket with a
  running total. If a target amount was set, show progress
- **Bucket list page**: Overview of all buckets with their totals,
  sorted by most recent activity
- **Dashboard integration**: Optionally show bucket totals on the
  dashboard (Phase 10)

**UI/UX — to be decided before implementation:**

The assignment interaction is the key design question. Options to
evaluate:

1. **Drag-and-drop** from transaction list into a bucket sidebar or
   drop zone — most intuitive but complex to build, especially on
   mobile
2. **Checkbox selection + "Toevoegen aan bucket" button** — simpler,
   works on mobile, similar to the bulk categorize flow (Phase 5)
3. **Per-transaction dropdown/button** — like category assignment, add
   a "Bucket" field to the transaction detail or edit modal
4. **Bucket-first**: open a bucket, then search/filter transactions to
   add — good for building a bucket from scratch

Decide during investigation which approach (or combination) fits best.
Consider mobile usability — drag-and-drop may need a different
interaction on phones.

**Database**: `buckets` table (id, name, description, target_amount,
created_at, updated_at). Add `bucket_id` nullable foreign key to
`transactions`.

**Deliverable**: Group transactions into purpose-specific buckets and
see how much was spent on each.

### Phase 8: Monefy Data Migration & Reconciliation (one-time)

**Branch**: `feature/monefy-migration`

A one-time feature to migrate historical data from Monefy into the app
and reconcile it with bank transactions. The goal: after this phase,
the database contains a correct, categorized history so that going
forward you only need to import new bank CSVs. The migration code will
be removed after completion.

Deferred to after Phases 5–7 so the app has the tools (categories,
splitting, buckets) to properly represent everything Monefy has.

**Critical dependency from Phase 5b**: The CategoryRuleService matcher
now correctly searches `description + original_description +
counterparty_name`. This was a bug fix during Phase 5b — the matcher
previously only searched description fields, which for Rabobank CSVs
meant rules almost never matched (the merchant identity lives in
`counterparty_name`). This entire phase's auto-categorization depends
on the matcher working correctly: thousands of historical transactions
will be auto-matched against the rules built up during the migration.
If the matcher were still broken, the migration would silently
mis-categorize most rows. Verify the matcher behaviour with a small
test import before starting the bulk Monefy work.

#### Critical: database backup strategy

**Why this matters now**: Up to this point, the database only contained
test data — running `php artisan migrate:fresh` or `migrate:rollback`
was fine because nothing of value was lost. Phase 8 changes that. Each
reconciled period represents hours of manual categorization work. If a
migration rollback runs after real data exists, it can corrupt that
work in ways that are hard to recover from.

Specifically, the `category_id` column on `transactions` is nullable
at the database level (SQLite cannot alter column constraints after
creation), but the application requires it — `TransactionRequest`
validation rejects null values. The system categories migration's
`down()` method sets `category_id` to null for transactions that had
system categories. After rollback, those transactions become
uneditable through the UI because validation blocks saving without a
category, but the database has null. You'd need manual SQL or tinker
to fix every affected row.

**Backup approach**: SQLite databases are single files — backing up is
just copying the file. Before starting Phase 8, set up a simple backup
routine:

```bash
# Create backups directory (one-time)
mkdir -p database/backups

# Before each period's reconciliation work:
cp database/database.sqlite database/backups/backup-$(date +%Y%m%d-%H%M%S).sqlite
```

**When to back up**:

- Before starting each period's reconciliation (Step 2)
- Before running any new migration
- Before any bulk data operation (imports, category reassignments)
- After completing a period's reconciliation (checkpoint)

**The rollback rule**: Once real categorized data exists in the
database, **never use `migrate:rollback` or `migrate:fresh`**. From
this point forward, all schema changes must be **additive** — new
migrations that add columns, tables, or modify data forward. If a
migration has a bug, write a new migration to fix it rather than
rolling back. The `down()` methods in migrations are effectively dead
code after this point.

Add `database/backups/` to `.gitignore` so backup files don't bloat
the repository.

#### Prerequisite: schema hardening (DB-level integrity constraints)

Before real data lands, do a schema audit. Most validation today lives
in Laravel Form Requests and controller validators — which protects
against user input but NOT against bugs, direct SQL, or future code
paths that skip the validator. SQLite's `ALTER TABLE` is limited, so
adding constraints gets painful once a table has real rows. This is
the last easy window.

Candidates to push down to the DB layer:

- `category_rules.match_pattern`: `CHECK (LENGTH(TRIM(match_pattern)) > 0)`
  — mirrors the app's `regex:/\S/` rule at the DB layer
- `category_rules`: expression unique index on `LOWER(match_pattern)` —
  enforces case-insensitive uniqueness at the DB layer (today only
  guarded by `CategoryRule::upsertByPattern`; any future write path
  that skips the helper could still insert duplicates)
- `NOT NULL` audit across all tables — any column the app never writes
  null to should probably have `NOT NULL` at the DB layer too. The
  known gap: `transactions.category_id` is nullable because Phase 5
  couldn't alter the column in SQLite. Revisit during this audit.
- Foreign key `ON DELETE` behavior audit — decide CASCADE vs SET NULL
  vs RESTRICT per relationship. Today most are defaults.

Each constraint needs a migration and will require a table-rewrite on
SQLite. Do them as a batch here (one migration per table), verified on
a seeded test DB, then committed before the first Monefy import.

#### Prerequisite: finalize default categories

After reviewing the Monefy category mapping (Step 1 below), decide on
the final set of default categories for fresh installs. Create a seeder
with these categories and their colors. This happens naturally after
the mapping step, since that's when you see which Monefy categories
were real vs repurposed.

#### Step 1: Monefy import with category mapping

Upload the Monefy CSV export. Before storing anything, show a
**category mapping screen** listing every unique Monefy category with
the number of entries that use it:

```
Monefy categorie        →  FinanceApp categorie
─────────────────────────────────────────────────
Boodschappen (312)       →  Boodschappen         ✓ behouden
Transport (89)           →  Transport             ✓ behouden
Bets (47)                →  Alcoholische dranken  ✏️ hernoemd
Entertainment (23)       →  Entertainment         ✓ behouden
```

For each Monefy category, the user can:

- **Keep** — create a new FinanceApp category with the same name
- **Rename** — type a different name (for repurposed categories like
  "Bets" → "Alcoholische dranken")
- **Map to existing** — pick an existing FinanceApp category from a
  dropdown (useful if categories were already created in Phase 5)

The entry count helps spot repurposed categories: "Bets (47 entries)"
is clearly not actual gambling. If a mapping is missed, the category
can always be renamed later through the normal edit page.

After mapping, store Monefy entries as **reference data** in a
separate table (not in the main transactions table). These are used
for comparison in Step 2.

#### Step 2: Period-by-period reconciliation

Work through the history one period at a time (month, quarter, or year
— user picks the granularity per round):

1. **Import bank CSV** for the selected period (via existing Phase 4a
   import flow)
2. **Comparison view**: bank transactions on one side, Monefy entries
   on the other, with auto-matching highlighted:
   - **Matched pairs**: same date + amount → copy the Monefy category
     to the bank transaction
   - **Potential splits**: multiple Monefy entries match one bank
     transaction (same date, amounts sum to bank total) → offer to
     create a transaction split (Phase 6) with the Monefy categories
   - **Potential buckets**: multiple bank transactions match one Monefy
     entry (grouped in Monefy) → offer to create a bucket (Phase 7)
   - **Unmatched Monefy entries**: cash payments, manual entries, or
     moved dates → skip, manually match, or add as manual transaction
   - **Unmatched bank transactions**: no Monefy equivalent → leave
     uncategorized (normal — Monefy didn't track everything)
3. **Resolve** each difference and mark the period as done
4. Move to the next period

#### Mind the leaks

- The reconciliation comparison view will reuse `TransactionList.vue`
  for the Monefy column. Those rows are **reference data, not editable
  transactions** — render them with `:read-only="true"` (the prop
  added in Phase 6 for "Alle rekeningen") so edit/split affordances
  don't leak through. Without it, the user could mutate the very data
  they're reconciling against.
- After a period is marked done in Step 2, **lock its rows** in the UI
  (hide or disable the "Resolve" actions). Re-running reconciliation
  on a finished period could silently overwrite already-resolved
  categories/splits — an `reconciled_at` flag is the simplest gate.

#### Step 3: Cleanup

Once all periods are reconciled, the Monefy reference data and the
reconciliation UI can be removed. The bank transactions remain as the
actual records, now enriched with categories, splits, and buckets from
the Monefy data.

**Deliverable**: A fully categorized, reconciled transaction history
ready for deployment. After this, only new bank CSVs need importing.

### Phase 9: AI Auto-Categorization

**Branch**: `feature/ai-categorization`

#### Prerequisite: confidence and source tracking

Add `category_confidence` (integer 0-100) and `category_source`
(enum: manual, rule, ai) columns to `transactions`. Backfill existing
transactions: manual assignments get confidence 100 + source "manual",
rule-matched get confidence 100 + source "rule".

This enables smart paint mode behavior in the CSV import sidebar:
- **Skip** rows with confidence 100 + source "manual" (user chose this)
- **Skip** rows with confidence 100 + source "rule" (trusted match)
- **Override** rows with source "ai" and confidence < 100 (AI unsure)
- **Always skip** transfers (system-assigned)

Clicking a row manually always allows override regardless of
confidence — that's an explicit user action.

#### Mind the leaks

- The paint-mode handler must receive `category_source`,
  `category_confidence`, and `is_transfer` per row and short-circuit
  on those flags **before** applying the brush. Selection alone is
  not enough — relying on it would silently overwrite manual choices
  and transfer categories during a drag-paint. Direct row clicks
  remain the only override path for protected rows.
- Suggestion-card components must derive auto-apply vs review vs
  manual from each row's certainty band, not from a parent-chosen
  flag. Never let the parent component bulk-apply a suggestion below
  the auto-assign threshold (currently 85%).

#### AI features

- **OpenAI integration**: Send transaction descriptions to OpenAI, get
  suggested categories back
- **Auto-suggest on import**: After CSV import, automatically suggest
  categories for new transactions
- **Interactive learning**: When you manually change a category, this
  automatically creates a local rule (Phase 5) AND gets stored as
  correction context for future AI prompts. Over time, the system
  shifts from AI-heavy to rule-heavy as patterns are learned. **Always
  go through `CategoryRule::upsertByPattern()`** (introduced in
  Phase 5b) — never call `CategoryRule::create()` directly. The helper
  enforces case-insensitive uniqueness while preserving display case.
  Bypassing it would re-introduce the duplicate-rule risk that 5b
  closed
- **Confidence display**: Show how sure the AI is (high confidence =
  auto-assign, low confidence = ask the user)

**How the AI prompt works (few-shot prompting):**

- System message: generic categorizer role + list of available categories
- User message: the transaction to categorize (description, amount,
  anonymized counterparty) + a selection of relevant past corrections
  as examples (e.g. "Jumbo Supermarkten" → Groceries). This is called
  few-shot prompting — the AI learns from your examples in the prompt
- Not ALL corrections are sent every time — only the most relevant ones
  for the current transaction, keeping prompts focused and API costs low
- The system prompt stays stable; corrections go in the user message

**Structured output with confidence & reasoning:**

The AI returns a JSON object for each transaction, not just a category name.
We use OpenAI's structured output feature (`response_format` with
`json_schema` and `strict: true`) to guarantee valid responses. Each
categorization includes three fields:

```json
{
  "category": {
    "value": "Groceries",
    "reasoning": "Albert Heijn is a Dutch supermarket chain",
    "certainty": 95
  }
}
```

- **value**: The suggested category (must be from the provided list)
- **reasoning**: Why the AI chose this category (helps you understand
  and spot mistakes)
- **certainty**: Confidence percentage (0-100). Used to decide:
  - 85-100%: Auto-assign (high confidence)
  - 50-84%: Show for user review with the reasoning
  - Below 50%: Flag as "needs manual categorization"
- These thresholds are configurable and can be tuned over time based
  on actual accuracy

**One-shot tracking:**

Track how often the AI gets it right without correction:

- When user confirms or changes an AI suggestion, store the result
- Calculate one-shot rate (% of suggestions accepted without edits)
- Track which categories the AI struggles with
- Use this data to improve prompts and identify where more rules
  are needed

**AI security measures:**

Our use case is low-risk (single-purpose categorization of bank data,
no tools, no conversation), but we apply layered defense following
OWASP LLM Top 10 (2025) and Agentic Applications Top 10 (2026):

1. **No tools**: The AI has no tool access (`tool_choice: 'none'` or
   simply no tools in the request). It can only return a JSON response.
   Even if a malicious transaction description says "call the delete
   function", the AI literally cannot — no tools are available
2. **Structured output enforcement**: `strict: true` in the JSON schema
   forces the model to output only valid JSON matching our schema. The
   AI cannot return arbitrary text, code, or instructions — only a
   category value, reasoning string, and certainty number
3. **System prompt separation**: The system prompt is sent as a separate
   API parameter (not mixed into user messages), making it harder to
   override with injected instructions
4. **Input delimiters**: Transaction data is wrapped in clear delimiters
   (`===TRANSACTION DATA===`) so the AI distinguishes data from
   instructions
5. **Output validation**: AI responses are validated against the JSON
   schema before being used. Invalid responses are rejected and the
   transaction is flagged for manual categorization
6. **Anonymization** (see Privacy section): Reduces sensitive data
   exposure even if the API were compromised

**Why this level is sufficient:**

- Our AI input is bank transaction data (structured, predictable), not
  arbitrary user text — the attack surface is inherently small
- Our AI output is a category name (from a fixed list) — even a
  successful injection can only result in a wrong category, not data
  loss or code execution
- The human-in-the-loop review (for low-confidence results) catches
  any remaining issues

**Privacy & data minimization:**

- **Local rules first**: Rule-based matching handles most transactions
  without any API call. AI is only used as a fallback for unrecognized
  transactions
- **Anonymize before sending**: Personal names in counterparty fields
  are replaced with placeholders (PERSON) before sending to OpenAI.
  IBANs are never sent. Only transaction description, amount, and
  anonymized counterparty name are included
- **Scenarios requiring anonymization**:
  - Rent payments (landlord name in counterparty)
  - Own transfers (user's name between accounts)
  - Friend payments (payment requests, gifts)
  - Salary (employer name in counterparty)
  - Medical payments (doctor/therapist — sensitive category)
- **API vs consumer**: OpenAI API does not use data for training
  (unlike ChatGPT). Data is retained up to 30 days for abuse
  monitoring only

**README update**: Add OpenAI setup instructions (API key
configuration) and AI categorization feature overview.

**Deliverable**: AI suggests categories with confidence scores, learns
from your corrections, while keeping personal data private and defended
against prompt injection.

### Phase 10: Dashboard and Insights

**Branch**: `feature/dashboard`

Switchable chart views — same data, different perspectives:

- **Donut/pie chart**: Category breakdown for selected period
- **Grouped bar chart**: Monthly totals compared side by side
- **Stacked bar chart**: Monthly totals broken down by category
- **Line chart**: Running balance or spending trend over time
- **Horizontal bar chart**: Top spending categories ranked
- **Account balances over time**: Line per account
- **Income vs expenses**: Monthly balance overview
- **Time period selector**: Switch between weekly / monthly / yearly views
- **Recurring expense detector**: Auto-identify subscriptions and recurring
  payments using transaction codes (ei = direct debit, db = standing order)
  and counterparty IBAN patterns. Show summary: "You have X recurring
  expenses totaling €Y/month"
- **Payment method breakdown**: Using the bank's transaction code field
  (ba = pin, id = iDEAL, ei = direct debit, cb = bank transfer, etc.)

**README update**: Add dashboard features overview and screenshots.

**Deliverable**: Visual insights into your spending from multiple angles.

### Phase 10b: Recurring Transactions

**Branch**: `feature/recurring-transactions`

- **Create recurring transaction**: Set up repeating entries (rent, salary,
  subscriptions) with a schedule (weekly, monthly, yearly). The recurring
  template has a `name` field — apply the same case-insensitive uniqueness
  pattern as `CategoryRule.match_pattern` (Phase 5b) and `Bucket.name`
  (Phase 7). If Phase 7 already extracted a trait/concern, reuse it; this
  is the third use case so the trait is now justified.
- **Auto-create**: Recurring transactions are automatically added on their
  scheduled date
- **Manage recurring**: List, edit, pause, or delete recurring entries
- **Visual indicator**: In the transaction list, recurring transactions
  are marked with an icon

**Deliverable**: Automated entry for regular income and expenses.

### Phase 11: UI/UX Polish — Desktop & Mobile

> **Phase 9 (Sub-Categories) was removed.** After re-evaluation, flat
> categories + transaction splitting (Phase 6) covers the real use cases
> better. Example: "Alcoholische dranken" needs to be a standalone
> category, not a child of "Boodschappen", because it's also used at
> the liquor store. The `parent_id` column and hierarchy code were
> removed in Phase 5.

**Branch**: `feature/ui-polish`

The earlier phases focused on building functionality fast. This phase is
explicitly about making the app look and feel good — and especially about
making the mobile experience first-class, since day-to-day use (quick-add
of a coffee, checking a balance) will mostly happen on the phone, while
heavier flows like CSV import stay on the desktop.

**Investigate first** (no code yet — produce a short notes file in `docs/`):

- Walk every page on desktop and on a real phone (or devtools mobile emulation).
  Capture screenshots, list everything that feels clunky, cramped, or ugly.
- **Per-page checklist** (lesson from Phase 5b): for each page that has
  validation/warnings/conflict-detection, verify "write-path consistency" —
  the rules used to compute UI warnings must use the same normalization
  (`trim`, `toLowerCase`, etc.) as what actually reaches the database.
  Otherwise the UI lies to the user. The Phase 5b conflict-detection bug
  (warning didn't trigger for trailing-whitespace duplicates that the
  backend silently collapsed) is the canonical example.
- Decide per page whether the mobile UX should mirror the desktop layout
  or diverge. Examples to think through:
  - Account show page: action buttons should be large thumb-targets at the
    bottom of the screen on mobile, not a 4-column grid up top.
  - Transaction list: maybe swipe-to-edit/delete on mobile vs click on desktop.
  - Modals: full-screen sheets on mobile vs centred dialogs on desktop.
  - Navigation: bottom tab bar on mobile vs top nav on desktop.
- Identify which flows are desktop-only (CSV import, bulk categorisation,
  category management) and which are mobile-primary (quick-add, balance check,
  recent activity).
- Pick a small visual language to commit to: spacing scale, typography, colour
  accents, button hierarchy, empty-state illustrations.
**Accumulated nice-to-haves to triage during this phase** (loosely
grouped — prioritize during the Phase 11 investigation):

*Visual / UX polish:*
- Auto-scroll during paint mode (Phase 5)
- Font Awesome icons per category (Phase 11 suggestion)
- Pattern display redesign on Category Rules index (Phase 5b)
- Cross-source duplicate detection — manual ↔ CSV (Phase 5b)
- Map IBAN to existing account on missing-accounts page (Phase 4a)
- Inbound-transfer click UX on the destination account (Phase 6)

*Refactors / architectural decisions:*
- Reusable `<CategoryPicker>` component (Phase 5b)
- Race-condition-safe duplicate protection (Phase 4a)
- Cross-CSV transfer deduplication (Phase 4a)
- Stashed-CSV cleanup as scheduled command (Phase 4a)
- Decide whether to remove inline account creation in CSV import (Phase 4a)

*New features:*
- Watch-folder CSV import (Phase 4a)
- Flexible column mapping for non-Rabobank CSVs (Phase 4a)

The detailed descriptions for each follow:

- **Nice-to-have from Phase 4a**: race-condition-safe duplicate protection.
  Add a unique constraint on `transactions(account_id, csv_import_hash)` and
  switch the import insert path to insert-ignore / upsert semantics. Not
  needed for the current single-user setup (no concurrent imports), but a
  cheap safety net if the app ever runs queued imports.
- **Nice-to-have from Phase 4a**: watch-folder CSV import. Drop a Rabobank
  CSV into a designated folder and have it imported automatically. Simplest
  route: a Laravel scheduled command (hourly cron) scans an inbox folder,
  imports each file via the existing `CsvImportService`, and moves it to
  `processed/` or `failed/`. Because the app server can't reach the user's
  laptop directly, pair this with a cloud sync tool (Nextcloud / Dropbox /
  Syncthing) that mirrors a local "Downloads → FinanceApp" folder to the
  server's inbox folder. Side benefit: offsite backup of CSV archive.
- **Nice-to-have from Phase 4a**: cross-CSV transfer deduplication. Phase 4a
  intentionally skipped this because the user only imports from one side of the
  transfer (the payment account). If a future workflow requires importing both
  sides, add detection: when a parsed row's counterparty IBAN matches an own
  account, look for an existing transfer with the opposite direction within ±1
  day and skip it as a duplicate.
- **Nice-to-have from Phase 4a**: refactor stashed-CSV cleanup to use a
  Laravel scheduled command (cron) instead of the current opportunistic
  cleanup that runs on every upload. Good excuse to learn how Laravel's
  task scheduler works (`app/Console/Kernel.php` schedule, `php artisan
  schedule:run` via cron). The opportunistic approach works fine for a
  single user but a scheduled job is the cleaner long-term solution.
- **Nice-to-have from Phase 4a**: flexible column mapping UI. Currently
  CSV import only supports Rabobank's hardcoded format. A column mapping
  step would let the user match CSV headers to our fields (date, amount,
  description, etc.), enabling support for ING, ABN AMRO, and other
  Dutch banks without writing a dedicated parser for each.
- **Nice-to-have from Phase 5**: auto-scroll during paint mode. When
  Ctrl+dragging to paint rows, if the user drags near the top or bottom
  edge of the visible area, the table should automatically scroll in
  that direction. Useful when the transaction list is taller than the
  screen and the user wants to paint many consecutive rows at once.
- **Nice-to-have from Phase 4a**: on the missing-accounts page, add an
  option to map an unrecognised IBAN to an existing account (e.g. if
  the user forgot to add the IBAN to an account they already created).
  Currently the page only offers "create new" or "cancel".
- **Nice-to-have from Phase 4a**: the inline account creation during
  CSV import (`MissingAccounts.vue`, `createAccounts()` controller
  method) may be removed if the "create account first" workflow proves
  sufficient in practice. Evaluate during Phase 11 polish.
- **Suggestion to evaluate**: replace the current category colour swatch with a
  Font Awesome (or similar) icon per category, tinted with the category's hex
  colour. Would need a `categories.icon` column, an icon picker in the category
  create/edit forms, and updates anywhere a category is rendered (transaction
  list, quick-add modal, categories index). Decide during the investigation
  whether this is worth the scope.
- **Nice-to-have from Phase 6**: improve the inbound-transfer click UX
  on the destination account's page. Today, clicking an inbound
  transfer (where the current account is the destination) navigates
  the user to the source account via `router.visit()` without opening
  any modal — they have to find and click the transfer again on the
  source page to edit it. The redirect exists for a real reason
  (`Show.vue` comment: editing from the destination's perspective
  would render the wrong "Van" and exclude the saved destination from
  the dropdown), but the current UX is jarring for an account that
  only ever receives transfers (every click teleports them away).
  Options to evaluate: auto-open the modal after navigation via a
  `?edit=<tx_id>` query param, add a read-only "view details"
  variant of the transfer modal that works from either side, or at
  minimum a small visual hint on inbound-transfer rows that clicking
  will navigate elsewhere.
- **Nice-to-have from Phase 5b**: rethink pattern display on the Category
  Rules index page. The current layout (pattern shown inline as `<code>`)
  feels underwhelming — hard to scan, doesn't stand out. Consider a card
  layout, better typography, or grouping by category. Revisit during the
  visual-language investigation at the top of Phase 11.
- **Nice-to-have from Phase 5b**: cross-source duplicate detection.
  Currently the CSV import only flags duplicates against previously-imported
  CSV rows (via `csv_import_hash`). Manual transactions are invisible to
  this check, so adding "Albert Heijn €23.45" by hand on your phone and
  later importing the bank CSV creates two transactions for the same
  purchase. Solution: during preview, also fuzzy-match each new CSV row
  against existing manual transactions on `account_id + date (±1 day)
  + amount`. Don't auto-skip — flag as "Mogelijk duplicaat" with a
  decision per row (skip / keep / merge). False positives are real
  (you might legitimately spend €23.45 twice on the same day at the
  same shop), so the user must always be in control. This becomes
  acute in steady-state daily use, post-Phase 8.
- **Nice-to-have from Phase 5b**: reusable category picker component. The
  native `<select>` dropdowns on the Category Rules create/edit modals (and
  anywhere else a category is picked) don't show the colour swatch or sort
  by usage. The CSV import preview sidebar already does both. Build a single
  `<CategoryPicker>` component that matches the sidebar: colour swatch (later
  icon too), alphabetical-after-usage sorting, and search. Use it everywhere
  a category is picked — rule create/edit, transaction create/edit, any
  future forms. Consistency + less code to maintain.

**Then implement**:

- Apply the visual language consistently across every existing page.
- Build the mobile-specific layouts/components identified in the investigation
  (responsive breakpoints, conditional components, or genuinely separate views
  where the UX diverges enough).
- Add a PWA manifest + install prompt so the phone version feels like an app
  (this overlaps with Phase 13 — pull it forward if it helps mobile testing).
- Re-run the accessibility pass against any new components.

**Deliverable**: An app that looks intentional rather than scaffolded, with
a phone experience that's actually pleasant for the daily-use flows.

> Note: We deliberately keep re-evaluating each phase as we go. If by the
> time we reach this phase we've already polished things incrementally, the
> scope here can shrink — or vice versa, if more issues have piled up, the
> scope can grow. Update this section before starting the phase.

### Phase 12: Security Audit

**Branch**: `feature/security-audit`

Before deploying anything to a public server, do a comprehensive
security review. We're handling financial data (IBANs, transactions,
counterparty information) so the bar is higher than a typical hobby
project. This phase is **research + verification + fixes**, not new
features. The output is a security audit report saved in `docs/` plus
all fixes applied.

#### Mind the leaks

- **Disable public registration before any production deploy**, even
  if Phase 12 hasn't fully started yet. Breeze ships `/register`
  enabled by default. Leaving it live on a single-user app exposes
  an account-creation surface to the entire internet — any drive-by
  crawler can register. The full removal step is in OWASP A07 below;
  do not wait until the audit phase to apply it.

#### Dependency vulnerability scan

- Run `composer audit` for PHP dependencies — review and address any
  reported CVEs
- Run `npm audit` for JavaScript dependencies — same
- Update or replace any package with known vulnerabilities
- Document any accepted risks (with reasoning) in the audit report

#### Static analysis

- Set up **Larastan / PHPStan** at a strict level and resolve all
  findings (or document accepted exceptions)
- Set up **ESLint** with security plugins for the Vue/JS side
- Optionally try **Psalm** for additional PHP analysis
- These tools catch entire classes of bugs (null dereferences, type
  confusion, dead code) that humans miss

#### OWASP Top 10 walkthrough

Manually review the codebase against the OWASP Top 10 (2021), focusing
on:

- **A01 Broken Access Control**: Every route and controller method —
  is the right authorization in place? (Single-user app makes this
  simpler, but we still need `auth` middleware everywhere.)
- **A02 Cryptographic Failures**: Verify the `encrypted` casts on
  `accounts.iban`, `transactions.counterparty_iban`,
  `transactions.counterparty_name`. Verify `APP_KEY` is set, strong,
  and not committed.
- **A03 Injection**: Audit any raw SQL (`DB::raw`, `DB::statement`)
  and any `eval`-like patterns. Verify all user input goes through
  Form Requests.
- **A04 Insecure Design**: Review the AI categorization design (already
  hardened in Phase 9) and the CSV import (file upload safety, file
  type validation, max size limits). **Also audit every model for the
  pattern `Model::create(['unique_field' => $userInput])`** — any such
  call risks creating duplicates if a `findBy*` / `upsertBy*` helper
  was added later but a code path was missed. The CategoryRule model
  has `upsertByPattern` (Phase 5b); Bucket likely has `upsertByName`
  (Phase 7); recurring transactions likely too (Phase 10b). Grep for
  direct `::create()` calls and verify they don't bypass the helper.
- **A05 Security Misconfiguration**: `APP_DEBUG=false` in production,
  error pages don't leak stack traces, default Laravel routes
  (`/telescope`, `/horizon` etc.) are disabled or protected, version
  headers stripped.
- **A06 Vulnerable & Outdated Components**: Same as the dependency
  scan above — but also check that PHP and Node base images in the
  Dockerfile are recent.
- **A07 Identification & Auth Failures**: Password requirements,
  session timeout, login rate limiting, brute-force protection.
  Also: **disable the public registration route** (`/register` from
  Breeze). This is a single-user app — no one should be able to
  create an account from the internet. Remove the registration
  controller/route entirely or wrap it behind a feature flag that
  defaults to off in production. Consider **2FA via Laravel Fortify**
  as an additional layer for a public-facing finance app.
- **A08 Software & Data Integrity Failures**: Verify Composer and NPM
  use lock files (we already do). Verify no unsigned third-party
  scripts loaded at runtime.
- **A09 Logging & Monitoring Failures**: First, **decide what to log**
  based on real usage patterns after Phases 5–10. Candidates worth
  considering: CSV imports (filename, account, counts — already logged
  via `csv_imports` table), rule changes (create/update/delete, who
  changed what), category reassignments (audit trail for "why is
  this transaction suddenly under X?"), failed login attempts, AI
  categorization calls (request hash, confidence, whether accepted).
  Then: make sure logs don't contain sensitive data (no full
  transaction objects, no IBANs, no plaintext
  decrypted values). Set up basic error logging.
- **A10 SSRF**: Review any code that makes outbound HTTP requests
  (the OpenAI API call in Phase 9) — make sure URLs are not user-
  controlled.

#### Laravel-specific best practices review

- Mass assignment: every Model has explicit `$fillable` (or
  `$guarded`)
- Form Requests: every controller mutation method uses one
- Eloquent relationships: no N+1 queries on hot paths (use
  `withCount`, `with`, `load`)
- File uploads (CSV import): MIME type checked, file size limited,
  stored outside web root
- Cookies: `Secure`, `HttpOnly`, `SameSite=Strict` flags
- CSRF protection: enabled (Laravel default), verify it's not
  bypassed anywhere
- Content Security Policy (CSP) headers configured

#### Configuration audit

- `APP_DEBUG=false` in production `.env`
- `APP_ENV=production` in production `.env`
- HTTPS enforced (force https middleware or web server config)
- `SESSION_SECURE_COOKIE=true` in production
- `SESSION_SAME_SITE=strict`
- Production error pages (`resources/views/errors/`) don't leak info
- All cron/queue jobs run as non-root

#### Database & encryption verification

- All sensitive fields have the `encrypted` cast applied
- `APP_KEY` is strong (Laravel generates 32-byte base64 by default)
- Verify SQLite file permissions are restrictive (not world-readable)
- Check that backups don't accidentally include `.env` alongside the
  database

#### Network isolation (decide before deployment)

Once deployed to Oracle Cloud, the app's IP is public. Bots scan the
entire internet and will find it within hours. The login page alone
is the attack surface — everything else is behind `auth` middleware.
Decide how much isolation is appropriate:

- **Minimum (public-facing with hardening)**: HTTPS, strong password,
  disabled registration, rate limiting, 2FA. The app is reachable from
  anywhere (supermarket, office, etc.) which is the main reason to
  deploy it in the first place. This is the default path.
- **Extra layer (VPN-only)**: Run **Tailscale** on the Oracle VM and
  on your phone. The app binds to the Tailscale private network only —
  no public port. Pro: basically unreachable by anyone except your
  devices. Con: every new device you want to use the app from must
  be enrolled in Tailscale first. Works offline-of-home-WiFi because
  Tailscale is internet-based, not WiFi-based.
- **Hybrid**: Public HTTPS with all hardening above, AND Tailscale as
  an optional private route. Belt and suspenders.

The right choice depends on how paranoid you want to be and how much
friction is acceptable when using the app from new devices. For a
personal finance app with auth + 2FA + rate limiting, fully public
is defensible. Tailscale is a strong "why not" addition if the setup
is low-friction.

#### Backup & recovery procedures

- Document backup procedure for SQLite database
- Document **separate** backup procedure for `APP_KEY` (store in
  password manager) — without this, encrypted fields are unrecoverable
- Test the restore procedure end-to-end at least once
- Document the recovery runbook in `docs/`

#### Testing gaps

- Add automated tests for the security-critical paths:
  - Auth required on every route
  - Form Request validation on every mutation endpoint
  - Encryption casts working as expected (write/read round-trip)
  - Authorization (even though single-user, should be verified)
- Aim for tests on the *risky* code, not 100% coverage

#### Refactoring opportunities

- Look for code that should be in services instead of controllers
- Look for duplicated logic that should be extracted
- Look for missing validation at boundaries
- Anything that smells off — fix it before going public

#### Output

A security audit report (`docs/security-audit.md`) listing:

- Every category of check performed
- Findings (severity, description, fix applied or accepted risk)
- Open items / known limitations
- Backup & recovery runbook

**README update**: Add a section about security measures (encryption,
backup procedure, security audit reference).

**Deliverable**: A documented, audited, hardened codebase ready for
public deployment.

### Phase 13: Polish and Deployment

**Branch**: `feature/deployment`

- **Oracle Cloud VM setup**: Create free account, provision VM, install
  Docker (just Docker — nothing else needed, since our app runs in containers)
- **Deploy the app**: Clone repo on server, `docker compose up -d` — done
- **SSL certificate**: Free via Let's Encrypt (HTTPS)
- **Domain setup**: Point a domain (or use free alternatives) to the VM
- **Phone home screen**: Add PWA manifest so it can be "installed" on
  your phone home screen
- **Basic security**: Rate limiting, CSRF protection (Laravel has this
  by default), auth required (most security work was done in Phase 12)
- **Update admin credentials**: Change the seeded user email and
  password from the development defaults (<admin@financeapp.local> /
  password) to real credentials before deploying. Also remove the
  `'password'` fallback from UserSeeder — require `DEV_ADMIN_PASSWORD`
  env var or skip seeding entirely
- **Remove auto-login middleware**: The AutoLoginDevelopment middleware
  only runs in local environment, but remove it entirely in production
  config for extra safety
- **Dockerfile hardening**: Run containers as non-root user (deferred
  from Phase 0 CodeRabbit review — not needed during local development)
- **Remove Laravel version info**: Strip version exposure from default
  pages/headers before going public
- **Branch split**: Rename `main` to `development`, create
  `production` branch. Set up GitHub branch protection rules on
  both — prevent deletion, no direct pushes, require PR reviews
- **Backup `APP_KEY` to password manager** (separate from database
  backup) — without this, encrypted fields are unrecoverable. Phase 12
  audit will have already documented the procedure.

**README update**: Add deployment instructions, production URL, and
final feature list.

**Deliverable**: App is live and accessible from your phone.

---

## Future Ideas (Not Planned Yet)

- Budget limits per category with progress bars
- Dark theme
- Calculator input for amounts
- Export reports (PDF/Excel)
- Multiple bank format presets (ING, ABN AMRO, Rabobank, etc.)
- Year-over-year comparisons
- Automatic bank transaction sync via GoCardless Bank Account Data API
  (formerly Nordigen) — eliminates need for manual CSV export. Note:
  direct Rabobank API requires an AISP license (not feasible for personal
  use), but GoCardless acts as a licensed middleman. May have a free tier.
  Fallback: MT940 file import (Rabobank supports this format, PHP library
  `jejik/mt940` can parse it)

---

## Phone Access Options

### During development (free)

Access via local network: `http://<laptop-ip>:8000` when on same WiFi.

### In production

| Option | Cost | How it works |
|---|---|---|
| Just use the IP address | Free | Open `http://129.151.x.x` — works but ugly |
| Cloudflare Tunnel | Free | Gives you a subdomain like `finance.yourdomain.com` without opening ports |
| Buy a domain | ~EUR 10/year | Point `finance.yourdomain.nl` to your server |
| Free subdomain services | Free | Services like DuckDNS give you `yourname.duckdns.org` |

Recommendation: Start with the IP address, add a domain later if you want.
HTTPS (secure connection) is free via Let's Encrypt regardless of which option.
