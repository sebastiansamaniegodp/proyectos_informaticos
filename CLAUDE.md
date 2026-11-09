# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

Management system (products, stock, customers, orders) for "Shopping de los Tejidos", a wire-mesh/fabric shop in Paraguay. University project (Proyectos Informáticos, UniNorte 2026). Laravel 13 + Livewire 4 + Tailwind 4 (Vite), PostgreSQL 16, PHP 8.4.

## Commands

```bash
php artisan serve                 # http://localhost:8000 (run alongside npm run dev)
npm run dev                       # Vite dev server; npm run build for production assets
php artisan migrate               # creates the schema (see "Database" below)
php artisan db:seed               # only the dev users, one per role: admin, vendedor, deposito (password 123)
php artisan db:seed --class=DemoSeeder   # demo products, customers, ~6 months of orders (not run by db:seed)

composer test                     # config:clear + php artisan test
php artisan test --filter=PedidoServiceTest            # one class
php artisan test --filter='PedidoServiceTest::nombre_del_test'   # one method
vendor/bin/pint                   # code style (Laravel Pint)
```

Tests run against a real PostgreSQL database `tejidos_test` (set in `phpunit.xml`), not SQLite: the schema is PostgreSQL-specific SQL. Local role/password is `tejidos`/`tejidos` for both `tejidos` and `tejidos_test`. `.claude/launch.json` (in the parent `~/web`) defines a `tejidos` preview on :8000 and a `tejidos-demo` preview on :8001 pointed at `tejidos_test`.

## Database

- The base schema is **not** written as Laravel migrations. `database/migrations/0001_01_01_000000_crear_esquema_inicial.php` executes `database/sql/modelo_datos_postgresql.sql` (v1.1 of the data model) verbatim, stripping `BEGIN`/`COMMIT` in memory. **Never edit that SQL file or that migration**; every schema change goes in a new migration, written as raw SQL via `DB::unprepared` (see existing ones).
- The SQL script also inserts sample data (families and two products, `HEX-12-150-50` and `ROM-5-150-25`, with 2 closed pieces each). Tests rely on these rows existing after `RefreshDatabase`.
- Tables and columns follow the ERD, not Laravel conventions: singular lowercase table names (`producto`, `pedido_detalle`), `id_<tabla>` primary keys, no timestamps, booleans as `'S'`/`'N'` strings (`activo`, `fraccionable`). Every model sets `$table`, `$primaryKey` and `$timestamps = false`.
- Money is guaraníes as integers (`BIGINT`). Metre quantities allow 2 decimals; pieces are integers.
- Auth uses `App\Models\Usuario` (table `usuario`, password column `clave_hash` via `getAuthPasswordName()`, no remember token; long sessions come from `SESSION_LIFETIME`).

## Architecture

- **Screens are full-page Livewire components** in `app/Livewire/<Módulo>/` (Listado, Formulario, Detalle, ...), registered with `Route::livewire(...)` in `routes/web.php`; views in `resources/views/livewire/<módulo>/`. Shared layout `resources/views/layouts/app.blade.php`; Blade components in `resources/views/components/` (charts are hand-built in `components/grafico/`). Plain controllers only for login, product photos and the PDF receipt.
- **Services own all writes to stock and orders** (`app/Services/`):
  - `StockService` is the only code that writes `stock`, `rollo_abierto` and `movimiento_stock`. Every operation runs in a transaction that first locks the product's `stock` row (`SELECT ... FOR UPDATE`). It decides which open roll a cut comes from.
  - `PedidoService` is the only code that writes `pedido`/`pedido_detalle`. It implements the order state machine (BORRADOR → CONFIRMADO → ENTREGADO → COBRADO, plus ANULADO from any non-final state) and asks `StockService` to deduct/restore stock on confirm/cancel. It locks the order row first.
  - `Redondeo` computes line totals with bcmath: quantity × unit price − discount, then round to the nearest multiple of 500 Gs (ties round up). Never round the unit price first, never use floats for this.
  - `ReporteService` (monthly results, dashboard), `ComprobanteService` (order receipt PDF via dompdf; business data from `config/negocio.php`).
  - Business-rule violations throw `App\Exceptions\ErrorDeStock` / `ErrorDePedido` with a user-facing Spanish message; Livewire components catch these and show them.
- **Roles**: `App\Enums\Rol` (ADMIN, VENDEDOR, DEPOSITO) holds the permission matrix `PERMISOS`; each permission is meant to be a Gate of the same name defined in `AppServiceProvider`, and `InicioSegunRol` middleware redirects non-admins from the dashboard to their start screen.
- `App\Support\Guaranies` formats amounts (`1.300.500`, `1,3 M`, `850 mil`) and computes chart axis scales.
- Code comments and docblocks cite requirement IDs from the project's analysis documents (`RN-xx` business rules, `DD-xx` design decisions, `SP-xx`, `PED-xx` test cases, etc.). Keep those references when editing and add them for new rules.

## Conventions

- Everything domain-facing is in Spanish: class, method, variable, route and table names, UI text, validation messages, comments, commit messages. UI text uses Paraguayan/rioplatense voseo ("Elegí", "Agregá").
- In prose you write (README, commit messages, UI text) do not use the em dash.
- The team works directly on `main`.
- UI is ported from the screen prototype `~/web/Prototipo de pantallas — Shopping de los Tejidos.html` (outside this repo); the loose files in `~/web` belong to the user and should not be modified.
