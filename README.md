# Flashy

Small plain PHP flashcard app for studying a language with exact answers, admin-managed languages and sets, and learner progress tracking.

## Requirements

- PHP 8.1+
- Node.js and npm
- `pdo_sqlite` for local development
- `pdo_mysql` for production / 20i

## Local development

Local development now uses SQLite automatically.

If Flashy is running in a local dev environment, such as:

- `php -S localhost:8000 router.php`
- `localhost`
- `127.0.0.1`

and there is no production database config yet, Flashy will use:

```text
data/flashy.sqlite
```

The local SQLite schema is created automatically on boot.

Build assets:

```bash
npm install
npm run build:css
```

Start the app:

```bash
php -S localhost:8000 router.php
```

Then open:

```text
http://localhost:8000
```

## Production / 20i install

Production still uses MySQL.

If Flashy is not in a local dev environment and no database config exists, it redirects to:

```text
/install
```

The installer will:

1. Ask for your MySQL connection details
2. Create the required tables
3. Create the first admin user
4. Write `config/database.php`

On 20i shared hosting, use the database hostname shown in My20i, not `localhost`.

## Database config

The app loads production database settings from either:

1. `FLASHY_DB_*` environment variables
2. `config/database.php`

Environment variables take priority.

Supported MySQL variables:

```bash
export FLASHY_DB_HOST=shareddb1b.hosting.stackcp.net
export FLASHY_DB_PORT=3306
export FLASHY_DB_NAME=your_database_name
export FLASHY_DB_USER=your_database_user
export FLASHY_DB_PASS=your_database_password
export FLASHY_DB_CHARSET=utf8mb4
```

Generated `config/database.php` looks like:

```php
<?php

declare(strict_types=1);

return [
    'host' => 'shareddb1b.hosting.stackcp.net',
    'port' => 3306,
    'name' => 'your_database_name',
    'user' => 'your_database_user',
    'pass' => 'your_database_password',
    'charset' => 'utf8mb4',
];
```

## Database commands

Run migrations manually:

```bash
php scripts/migrate.php
```

Create an admin user manually:

```bash
php scripts/create_admin.php --email=admin@example.com --password=change-me --first-name=Admin --last-name=User
```

On local dev this will target SQLite. On production it will target the configured MySQL database.

## Admin tools

Inside the admin dashboard `Tools` tab you can:

- See the current active database and connection target
- Run migrations against the active database
- Import a local `.sql` file into the configured MySQL database
- Create an admin user from the UI
- Download the sample CSV for set imports

## Deploying to 20i

1. Build CSS locally:

```bash
npm install
npm run build:css
```

2. Upload the project so the web root contains:

```text
app/
config/
index.php
public/
resources/
robots.txt
scripts/
views/
```

3. Make sure the `config` directory is writable for the initial install so Flashy can create `config/database.php`.

4. Visit the site and complete `/install`.

5. After install, confirm you can log in and access `/admin`.
