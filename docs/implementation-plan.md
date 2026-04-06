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
Oracle Cloud (Phase 10), the app runs 24/7 independently.

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

**Note**: During early development (before Phase 10 deployment), we use
`main` as the single branch. The `development` + `production` split
happens in Phase 10 when we actually deploy.

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

### Service Classes (from Phase 4 onward)

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
- Add ARIA accessibility attributes when customizing the default layout

**README update**: Replace default Laravel README with project
description, Docker setup instructions, and how to run migrations.

**Deliverable**: You can manage your accounts and spending categories.

### Phase 3: Manual Transaction Entry + Transfers

**Branch**: `feature/transactions`

- **Transaction form**: Account selector, date, description, amount,
  income/expense toggle, category dropdown, optional notes
- **Transfer form**: From account, to account, amount, date, notes
  (this creates a transfer-type transaction — not counted as spending)
- **Transaction list page**: See all transactions, sorted by date,
  filterable by account
- **Edit/delete transactions**
- **Account balance display**: Each account shows its current balance
  (starting balance + all transactions)
- **TransactionType enum**: Add a PHP 8.1+ backed enum for the type
  field (income/expense/transfer) — gives IDE autocompletion and type
  safety when building transaction forms

**README update**: Add manual transaction entry and transfer usage.

**Deliverable**: You can add transactions, make transfers, and see balances.

### Phase 4: CSV Import

**Branch**: `feature/csv-import`

- **Upload page**: File picker for CSV, account selector (which account
  is this CSV for?)
- **CSV preview**: Show parsed rows before importing (so you can verify)
- **Column mapping**: Match CSV columns to our fields (date, description,
  amount, counterparty_name, counterparty_iban, balance_after,
  transaction_code) — Dutch bank formats vary, so this step is important
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

### Phase 4b: Monefy Data Migration (one-time)

**Branch**: `feature/monefy-migration`

This is a temporary feature to migrate your existing data from Monefy into
the new app. It will be removed after the migration is complete.

#### Strategy: Bank CSV first, then overlay Monefy categories

Why this order:

- Your bank export is the **source of truth** — it has every transaction
- Monefy might have manual adjustments (moved dates, missing entries)
- We use the bank data as the base, then use Monefy as a "cheat sheet"
  to copy over categories you already assigned

**Steps:**

1. **Import bank CSVs** (using Phase 4's import feature) — this gives us
   the complete, raw transaction history
2. **Upload Monefy CSV export** — parse the Monefy CSV format (comma
   delimiter, UTF-8, dot decimal)
3. **Auto-match**: For each Monefy entry, try to find the matching bank
   transaction by date + amount (allowing small date differences, since
   some dates were intentionally moved)
4. **Copy categories**: Where a match is found, copy the Monefy category
   to the bank transaction. Create new categories as needed from Monefy's
   category names. Note: some Monefy categories were repurposed (e.g.
   "Bets" was used to track alcoholic beverages). Include a category
   mapping step where these can be renamed to their intended meaning
5. **Review unmatched**: Show a list of:
   - Monefy entries that didn't match any bank transaction (why?)
   - Bank transactions that have no Monefy match (uncategorized — normal
     if Monefy didn't have them)
6. **Manual review UI**: Let you go through unmatched items and decide
   what to do (skip, manually match, add as manual entry)

**After migration is complete**: Remove this feature branch's code or
simply leave it unused. Since it's a separate route/page, it won't
affect normal app usage.

**Deliverable**: All historical data from Monefy + bank is merged into
the new app with categories preserved.

### Phase 5: Transaction Categorization Workflow

**Branch**: `feature/categorization`

- **Uncategorized transactions view**: Filter to show only transactions
  without a category
- **Quick categorize**: Click a transaction → dropdown to assign category
- **Bulk categorize**: Select multiple transactions → assign same category
- **Category rules**: "If description contains 'Albert Heijn' → Groceries"
  (saves manual rules for auto-matching on future imports)
- **Correction tracking**: When a user changes a category, store the
  correction. These corrections serve double duty: they become local
  rules immediately, and they feed into AI context in Phase 6

**README update**: Add categorization workflow overview.

**Deliverable**: Efficiently categorize imported transactions.

### Phase 6: AI Auto-Categorization

**Branch**: `feature/ai-categorization`

- **OpenAI integration**: Send transaction descriptions to OpenAI, get
  suggested categories back
- **Auto-suggest on import**: After CSV import, automatically suggest
  categories for new transactions
- **Interactive learning**: When you manually change a category, this
  automatically creates a local rule (Phase 5) AND gets stored as
  correction context for future AI prompts. Over time, the system
  shifts from AI-heavy to rule-heavy as patterns are learned
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

**One-shot tracking (Phase 7 addition):**

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

### Phase 7: Dashboard and Insights

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

### Phase 7b: Recurring Transactions

**Branch**: `feature/recurring-transactions`

- **Create recurring transaction**: Set up repeating entries (rent, salary,
  subscriptions) with a schedule (weekly, monthly, yearly)
- **Auto-create**: Recurring transactions are automatically added on their
  scheduled date
- **Manage recurring**: List, edit, pause, or delete recurring entries
- **Visual indicator**: In the transaction list, recurring transactions
  are marked with an icon

**Deliverable**: Automated entry for regular income and expenses.

### Phase 8: Transaction Splitting

**Branch**: `feature/transaction-splitting`

- **Split UI**: On a transaction, click "Split" → add rows with category
  - amount. Amounts must add up to the original total
- **Split display**: In the transaction list, show split transactions
  with their sub-categories

**Deliverable**: Split a single transaction across multiple categories.

### Phase 9: Sub-Categories (Hierarchical)

**Branch**: `feature/sub-categories`

- **Parent category selector**: When creating a category, optionally pick
  a parent (e.g., "Foods" under "Groceries")
- **Tree display**: Show categories as a collapsible tree
- **Dashboard grouping**: Charts can show parent-level or drill into
  sub-categories

**Deliverable**: Organize categories into parent/child groups.

### Phase 10: Polish and Deployment

**Branch**: `feature/deployment`

- **Oracle Cloud VM setup**: Create free account, provision VM, install
  Docker (just Docker — nothing else needed, since our app runs in containers)
- **Deploy the app**: Clone repo on server, `docker compose up -d` — done
- **SSL certificate**: Free via Let's Encrypt (HTTPS)
- **Domain setup**: Point a domain (or use free alternatives) to the VM
- **Phone home screen**: Add PWA manifest so it can be "installed" on
  your phone home screen
- **Basic security**: Rate limiting, CSRF protection (Laravel has this
  by default), auth required
- **Update admin credentials**: Change the seeded user email and
  password from the development defaults (<admin@financeapp.local> /
  password) to real credentials before deploying
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
