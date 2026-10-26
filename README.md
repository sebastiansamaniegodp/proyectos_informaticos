# Shopping de los Tejidos

Sistema de gestión de productos, stock, clientes y pedidos para Shopping de los Tejidos (Proyectos Informáticos, UniNorte 2026).

## Requisitos

- PHP 8.4 (con las extensiones `pgsql` y `pdo_pgsql`)
- Composer 2
- Node.js 22 o superior
- PostgreSQL 16

## Levantar el proyecto en local

```bash
git clone <url-del-repositorio> tejidos
cd tejidos
composer install
npm install
cp .env.example .env
php artisan key:generate
```

Crear el rol y la base de datos (la clave `tejidos` es solo para uso local):

```bash
sudo -u postgres psql -c "CREATE ROLE tejidos LOGIN PASSWORD 'tejidos';"
sudo -u postgres psql -c "CREATE DATABASE tejidos OWNER tejidos;"
```

Crear el esquema (ejecuta `database/sql/modelo_datos_postgresql.sql`):

```bash
php artisan migrate
```

Iniciar el proyecto, cada comando en una terminal:

```bash
npm run dev
php artisan serve
```

El sistema queda en http://localhost:8000.

## Ramas

- `main`: versión estable.
- `develop`: integración del trabajo en curso.
- `feature/<modulo>`: desarrollo de cada módulo, sale de `develop` y vuelve a `develop`.
- `fix/<descripcion>`: corrección de errores.
