# FinanceApp

A personal finance tracking application for managing accounts, categorizing
transactions, and gaining spending insights. Built with Laravel, Vue.js,
and Inertia.js.

## Getting Started

### Prerequisites

- Docker and Docker Compose

That's it. Everything else runs inside containers.

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

## Features

- **Accounts** — track multiple accounts (checking, savings, cash) with running balances.
- **Categories** — organise spending with parent/child categories, custom colours, and an income/expense type.
- **Account-centric transactions** — open an account to see its history and add entries with one of four actions: income, expense, transfer between your own accounts, or clone a previous entry.
- **All-accounts overview** — combined read-only view across every account.
- **Encryption at rest** — sensitive fields (`accounts.iban`, `transactions.counterparty_name`, `transactions.counterparty_iban`) are encrypted with Laravel's `encrypted` cast (AES-256-CBC, keyed off `APP_KEY`).

> ⚠️ **Back up `APP_KEY` somewhere safe.** If you lose it, every encrypted field becomes unreadable — Laravel cannot recover them.
