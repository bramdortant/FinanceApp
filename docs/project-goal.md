# FinanceApp - Project Goal

## Vision

A personal finance tracking application accessible from both laptop and phone,
providing clear insight into spending patterns through categorized transaction data.

## Core Requirements

### Accounts

- Multiple accounts (checking, savings, cash, etc.)
- Each account has a name, type, and starting balance
- Transfers between accounts (should NOT count as income/expense)
- Account balance overview

### Import & Data

- Import bank transaction exports (CSV format, possibly PDF later)
- Support for Dutch bank export formats
- Store transactions in a persistent database accessible from multiple devices
- Manual transaction entry (for cash payments, etc.)
- Duplicate detection when uploading CSV (prevent re-importing same transactions)
- One-time migration from Monefy app (via CSV export, match with bank data,
  preserve existing categories). Temporary feature, removed after migration.

### Categorization

- Fully custom categories (CRUD: create, edit, delete categories)
- Hierarchical/nested categories (e.g. Groceries → Foods, Alcoholic Beverages)
- Start with flat categories (1 level), design for max 2-3 levels deep in the future
- Auto-categorization that learns from user corrections (AI-powered, OpenAI)
- Ability to split a single transaction into multiple categories
- Bulk/quick category assignment for efficient processing

### Insights & Visualization

- Switchable chart types on the dashboard (view same data in different ways):
  - Donut/pie chart: category breakdown for a period
  - Bar chart (grouped): monthly totals compared side by side
  - Stacked bar chart: monthly totals broken down by category
  - Line chart: running balance or spending trend over time
  - Horizontal bar chart: top spending categories ranked
  - Account balances over time: line per account
- Budget limits per category with progress bars
- Monthly/weekly/yearly time period selector
- Income vs expenses overview

### Access

- Accessible from laptop (browser)
- Accessible from phone (browser or app-like experience)
- Single user (personal use only)

### Display Preferences

- Currency amounts displayed in Dutch notation (€ 12,50 with comma)
- Dates displayed in Dutch format (dd-mm-yyyy)
- Data stored internally in programming format (dot decimals, ISO dates)

## Feature Priority

### MVP (must have for first usable version)

- Multiple accounts + transfers
- Category CRUD
- Manual transaction entry
- CSV import with duplicate detection
- Transaction list with search/filter
- Basic dashboard with pie chart and monthly overview
- Authentication (login)

### Post-MVP (phased implementation)

- Monefy CSV migration (Phase 4b)
- Categorization workflow (quick/bulk assign, category rules)
- AI auto-categorization (OpenAI)
- Advanced dashboard charts (trends, comparisons, stacked bars)
- Transaction splitting
- Sub-categories (hierarchical)
- Recurring transactions
- Budget limits with progress bars

### Nice to Have (future)

- Dark theme
- Calculator input for amounts
- Export reports (PDF/Excel)
- Multiple bank format presets (ING, ABN AMRO, Rabobank, etc.)
- Year-over-year comparisons
- PWA (installable on phone home screen)
- Automatic bank sync via GoCardless API (no more manual CSV exports)
  — requires checking current pricing/free tier availability

## Constraints & Preferences

- Privacy-first: self-hosted on Oracle Cloud Free Tier (VPS, user controls server)
- Cost: free preferred, small monthly cost acceptable if necessary
- User is learning development, Claude Code assists with implementation
- User has an OpenAI API key available for AI features
- Docker-based setup (same environment locally and in production)

## Inspiration

- Monefy app (liked the concept, limited by free tier restrictions)
- Pain points with Monefy: fixed categories, no CSV import, no auto-categorization,
  no transaction splitting, no laptop access, no advanced charts
