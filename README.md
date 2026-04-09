# FinanceApp

A self-hosted personal finance tracker for managing bank accounts,
categorizing transactions, and gaining insight into spending patterns.
Built with Laravel, Vue.js, and Inertia.js.

## Features

### Accounts

Track multiple accounts (checking, savings, cash) with running balances.
Each account can store an IBAN for automatic matching during CSV import.
A combined "Alle rekeningen" view shows transactions across every
account.

### Transactions

Open an account to see its transaction history. Four quick actions are
available:

- **Inkomsten** — add income
- **Uitgaven** — add an expense
- **Overboeken** — transfer between your own accounts (does not count as
  income or expense)
- **Klonen** — search for a past transaction and re-use it as a template

### Categories

Organise transactions with custom categories. Each category has a name,
colour, and income/expense type. Categories support a parent/child
hierarchy for grouping (e.g. Boodschappen > Albert Heijn).

### CSV Import (Rabobank)

Upload a Rabobank CSV export to import transactions in bulk.

- The matching account is **auto-detected from the IBAN** inside the
  file. If the IBAN doesn't match an existing account, you can create
  one on the spot.
- A **preview** shows all parsed transactions before importing.
- **Duplicate detection** via a per-row hash — re-uploading or importing
  overlapping date ranges is safe.
- **Transfer detection** — transactions where the counterparty IBAN
  matches one of your own accounts are automatically recognised as
  transfers.
- Supports CSVs containing multiple IBANs (grouped into tabs on the
  preview page).

### Encryption at Rest

Sensitive fields (account IBAN, counterparty name, counterparty IBAN)
are encrypted with Laravel's `encrypted` cast (AES-256-CBC, keyed off
`APP_KEY`).

> **Back up `APP_KEY` somewhere safe.** If you lose it, every encrypted
> field becomes unreadable.

## Getting Started

### Prerequisites

- Docker and Docker Compose

Everything else runs inside containers.

### Setup

```bash
# Start all containers
docker compose up -d

# Install PHP dependencies
docker compose exec app composer install

# Install frontend dependencies
docker compose exec node npm install

# Create the database and run migrations
docker compose exec app php artisan migrate

# Open in your browser
# http://localhost:8000
```

### Daily Usage

```bash
# Start the app
docker compose up -d

# Stop the app
docker compose down

# Run migrations after pulling new changes
docker compose exec app php artisan migrate

# Rebuild containers after Dockerfile changes
docker compose build && docker compose up -d
```

### Access from Phone

While running locally, find your laptop's IP and open
`http://<your-ip>:8000` on your phone (same WiFi network).

```bash
hostname -I
```

## Tech Stack

- **Backend**: Laravel (PHP 8.4)
- **Frontend**: Vue.js 3 via Inertia.js
- **Database**: SQLite
- **Styling**: Tailwind CSS
- **Containerization**: Docker + Docker Compose
