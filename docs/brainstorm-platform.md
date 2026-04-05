# Platform Brainstorm

## Context

We explored 11+ platform options. Some were eliminated early:

- **Flutter**: Great phone UX but Flutter Web is sluggish — bad laptop experience
- **React Native**: Needs separate backend + app store publishing overhead
- **Tauri**: Builds installable apps, not web apps — must install on every device
- **Kotlin Multiplatform**: Immature charting ecosystem, complex infrastructure
- **Directus/Strapi**: Loses control over business logic (CSV parsing, AI, duplicates)
- **Next.js / Nuxt.js**: Resource hungry, 1GB RAM is tight with Node.js
- **Go + HTMX**: Great efficiency but poor phone UX for interactive features
- **Django + HTMX**: No clear advantage over Laravel, same UX limitations

Coding learning curve is NOT a factor (Claude Code handles that).
Infrastructure/setup complexity matters more.

## Comparison Table

| Concern | Laravel+Inertia+Vue | SvelteKit | PocketBase+Svelte | Laravel+Livewire |
|---|---|---|---|---|
| **Phone UX** | 8/10 | 8/10 | 8/10 | 6/10 |
| **Laptop UX** | 9/10 | 9/10 | 9/10 | 8/10 |
| **Infra simplicity** | 7/10 | 7/10 | 9/10 | 8/10 |
| **Fits 1GB RAM?** | Yes | Yes | Yes (tiny) | Yes |
| **Familiarity** | Partial | None | None | High |
| **Feature fit** | Strong | Strong | Weak for CSV/AI | Strong |
| **Maintenance** | Two ecosystems | One (new to you) | Minimal but limited | One (known) |
| **Biggest risk** | Debugging Vue | Debugging everything | Outgrowing PocketBase | Livewire lag |

---

## Option 1: Laravel + Inertia + Vue (Recommended)

### What it is

Laravel handles the backend (CSV parsing, database, AI calls). Vue handles the
frontend (everything you see). Inertia.js is the bridge — it means your Laravel
controllers return Vue pages directly, so you do NOT need to build a separate API.

### What building it looks like

You work in two types of files:

- **PHP files** (controllers, models, migrations) — the Laravel side you partly know
- **Vue files** (.vue) — HTML template + JavaScript logic + styling, all in one file

Adding a new page (e.g. "Category Management"):

1. Create a Laravel controller that fetches categories from the database
2. Return `Inertia::render('Categories/Index', ['categories' => $categories])`
3. Create a Vue component that receives those categories and renders the UI

### What you see on your phone

- **Page transitions**: Seamless, no white flashes. Inertia swaps content in-place.
- **Charts**: Smooth animations via ApexCharts or Vue-ChartJS
- **Forms**: Responsive dropdowns, search-as-you-type category selectors
- **Loading**: No full page reloads — feels like a native app
- **Install on home screen**: Possible with a PWA plugin (needs setup)

### Pain points (honest)

- Vue is completely new to you. When something looks wrong on screen, you need to
  understand Vue's concepts (reactivity, props, components) to debug it
- Inertia has its own learning curve (shared data, form helpers, partial reloads)
- Two ecosystems to keep updated (Composer for PHP + npm for JavaScript)
- Build step required: `npm run build` compiles Vue into static files, so you need
  Node.js on your server

### Infrastructure

- **Server runs**: PHP-FPM + Nginx + SQLite + queue worker (for AI jobs)
- **Deploy**: `git pull` → `composer install` → `npm run build` → `php artisan migrate`
- **Backup**: Copy the SQLite file (it's one file)
- **Maintenance**: Keep PHP and npm packages updated

### How each feature works

| Feature | How it's built |
|---|---|
| CSV upload | Laravel controller receives file, PHP parses it |
| Duplicate detection | Eloquent query comparing hashes of existing transactions |
| OpenAI categorization | Laravel queue job calls OpenAI, stores result in database |
| Hierarchical categories | Eloquent self-referencing relationship (parent_id column) |
| Transaction splitting | Vue form with dynamic rows, Laravel stores split records |
| Dashboard charts | Vue chart components with reactive data from Laravel |
| Manual entry | Vue form with Inertia form helper, validates in Laravel |

---

## Option 2: SvelteKit Full-Stack

### What it is

SvelteKit handles BOTH backend and frontend in one framework, one language
(JavaScript/TypeScript). Prisma ORM talks to SQLite for the database.

### What building it looks like

You work in `.svelte` files that contain HTML, CSS, and JavaScript together.
Each page is a folder with:

- `+page.server.ts` — loads data and handles form submissions (the backend part)
- `+page.svelte` — the visual page (the frontend part)

Adding a "Category Management" page:

1. Create `src/routes/categories/+page.server.ts` to fetch/save data
2. Create `src/routes/categories/+page.svelte` for the UI

Everything is in one project, one language.

### What you see on your phone

- **Page transitions**: Fast, no white flashes
- **Charts**: Svelte has excellent built-in transitions and animations
- **Forms**: Reactive by default — very smooth two-way binding
- **Svelte syntax**: Closest to plain HTML of any framework — reads naturally
- **Install on home screen**: Good PWA support via plugin

### Pain points (honest)

- Everything is unfamiliar. No Laravel, no Eloquent, no Blade, no PHP
- Prisma is good but different from Eloquent — migrations work differently
- No built-in auth — you build it yourself or use a library
- When something breaks in production, you're debugging Node.js, not PHP
- Smaller ecosystem — fewer "just install this package" solutions
- No built-in queue system for background jobs (like OpenAI calls)
- You'd be fully dependent on Claude Code for understanding the entire stack

### Infrastructure

- **Server runs**: Node.js process (PM2 or systemd keeps it alive) + SQLite
- **Deploy**: `npm run build` → restart Node process
- **Backup**: Copy the SQLite file
- **Maintenance**: Simpler than Laravel in some ways (fewer moving parts), but
  the PHP hosting ecosystem is more mature

### How each feature works

| Feature | How it's built |
|---|---|
| CSV upload | SvelteKit form action receives file, parsed in server-side JS |
| Duplicate detection | Prisma queries comparing records |
| OpenAI categorization | Server-side API call (no built-in queue — needs workaround) |
| Hierarchical categories | Prisma self-relation + Svelte recursive component |
| Transaction splitting | Svelte reactive form with dynamic rows (instant, no server) |
| Dashboard charts | LayerCake or Chart.js wrapper for Svelte |
| Manual entry | Svelte form with two-way binding |

---

## Option 3: PocketBase + SvelteKit Frontend

### What it is

PocketBase is your entire backend — a single tiny program (15MB) that gives you a
database, API, and admin panel out of the box. You build the frontend in SvelteKit,
which calls PocketBase's API to fetch and save data.

### What building it looks like

- You define your data structure (tables/collections) through PocketBase's web admin
  panel — click to add fields, no migration files needed
- Frontend is SvelteKit pages that call PocketBase's API
- Adding a Category Management page = create a Svelte page, call
  `pb.collection('categories').getFullList()` to fetch data

No backend code needed for basic CRUD operations.

### What you see on your phone

Same excellent Svelte experience as Option 2. The difference is invisible to the
user — PocketBase just serves data differently behind the scenes. Real-time
subscriptions are built in (live-updating dashboards without polling).

### Pain points (honest)

- **This is the critical one**: PocketBase is limited for complex business logic.
  CSV parsing, duplicate detection with complex matching, AI categorization with
  retry logic, transaction splitting with validation — all of these are awkward in
  PocketBase because it's designed for simple CRUD, not application logic
- PocketBase hooks (custom code) exist but are primitive compared to Laravel
- Complex database queries (aggregating by nested categories) require workarounds
- Small ecosystem — when you hit a wall, fewer answers online
- You might outgrow it and need to rewrite the backend later
- Still need to learn Svelte (same as Option 2)

### Infrastructure

- **Server runs**: One PocketBase binary + static files. That's it
- **Deploy**: Replace binary, restart. Copy new frontend files
- **Backup**: Copy one SQLite file
- **Maintenance**: Almost none — this is the simplest option by far

### How each feature works

| Feature | How it's built |
|---|---|
| CSV upload | Awkward — need a custom PocketBase hook or external script |
| Duplicate detection | API filter queries, but complex matching is limited |
| OpenAI categorization | PocketBase JS hook (limited error handling, no retries) |
| Hierarchical categories | Relations work, but tree queries are clunky via API |
| Transaction splitting | Frontend logic + multiple API calls (no transaction grouping) |
| Dashboard charts | Same Svelte libraries as Option 2 |
| Manual entry | Simple API calls — this part works great |

---

## Option 4: Laravel + Livewire + Alpine.js

### What it is

Pure Laravel — everything in PHP. Livewire adds reactive components (dynamic updates
without page reloads) while staying in PHP. Alpine.js handles small JavaScript
interactions (dropdowns, toggles). This is the closest to what you do at work with
the components package.

### What building it looks like

You edit Blade files and Livewire component classes (PHP). Very similar to your
current work.

Adding a "Category Management" page:

1. Run `php artisan make:livewire CategoryManager`
2. This gives you a PHP class (with methods like `save()`, `delete()`) and a Blade view
3. The Blade view uses `wire:click`, `wire:model` to bind actions to the PHP class

No .vue files. Minimal frontend tooling compared to SPA stacks, but npm/Vite is typically still used for asset builds (Tailwind, CSS).

### What you see on your phone

- **Page transitions**: Good with `wire:navigate` (Livewire 3) — avoids white flashes
- **Interaction speed**: Each action makes a server round-trip. Quick actions (like
  rapidly categorizing 20 transactions) feel slightly laggy compared to Vue/Svelte
- **Charts**: Work via Alpine.js + a JS chart library — functional but less seamless
- **Forms**: Feel good with `wire:model.live` for instant feedback
- **Install on home screen**: Same PWA approach as Option 1

### Pain points (honest)

- **Phone UX is the weakness**. Livewire makes a network request for every interaction.
  On a slow connection, this is noticeable. Categorizing many transactions in a row
  will feel slower than the Vue/Svelte options
- Heavy interactions (splitting transactions, reordering categories) feel less smooth
- Charts require mixing in JavaScript anyway (Alpine + Chart.js), which partially
  defeats the "no JavaScript" advantage
- Livewire has quirks — DOM diffing issues, event ordering problems, nested component
  complexity

### Infrastructure

- **Server runs**: PHP-FPM + Nginx + SQLite + queue worker
- **Deploy**: `git pull` → `composer install` → `php artisan migrate` (no build step!)
- **Backup**: Copy the SQLite file
- **Maintenance**: Keep Composer packages updated. Most familiar setup for you

### How each feature works

| Feature | How it's built |
|---|---|
| CSV upload | Livewire file upload (built-in), PHP parses CSV |
| Duplicate detection | Eloquent queries — straightforward |
| OpenAI categorization | Laravel queue job, Livewire polls for completion |
| Hierarchical categories | Eloquent self-relations, Blade recursive partial |
| Transaction splitting | Livewire component with dynamic rows (server round-trip per row) |
| Dashboard charts | Alpine.js component wrapping Chart.js |
| Manual entry | Livewire form — feels natural and familiar |

---

## Hosting: Oracle Cloud Free Tier

**Cost: Free. Permanently (not a trial).**

Oracle Cloud gives you a VM with 1GB RAM and 24GB storage. This IS self-hosting — you
control the server, install your own software. Your application data remains under your
account and access controls, while Oracle operates the underlying infrastructure.

Alternative: Raspberry Pi at home (maximum privacy, but networking is complex).

## Architecture Diagrams

### Option 1 & 4 (Laravel-based)

```text
[Phone/Laptop Browser]
         |
    opens your URL
         |
         v
[Oracle Cloud VM - free]
    ├── Nginx (web server)
    ├── PHP-FPM (runs Laravel)
    ├── Laravel application
    |     ├── Controllers / Livewire components
    |     ├── Eloquent models
    |     └── Queue worker (for AI jobs)
    ├── Vue.js (Option 1 only, compiled to static files)
    └── SQLite database (one file)
```

### Option 2 (SvelteKit)

```text
[Phone/Laptop Browser]
         |
         v
[Oracle Cloud VM - free]
    ├── Nginx (reverse proxy)
    ├── Node.js (runs SvelteKit)
    ├── SvelteKit application
    |     ├── Server routes (API + data loading)
    |     └── Svelte pages (frontend)
    ├── Prisma ORM
    └── SQLite database (one file)
```

### Option 3 (PocketBase + SvelteKit)

```text
[Phone/Laptop Browser]
         |
         v
[Oracle Cloud VM - free]
    ├── Nginx (serves static files + proxies API)
    ├── PocketBase (single binary — database + API + auth)
    └── SvelteKit (compiled to static HTML/JS/CSS files)
```
