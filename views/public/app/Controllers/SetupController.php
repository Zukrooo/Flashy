<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Config;
use App\Core\Database;
use App\Core\Migrator;
use App\Core\View;
use App\Repositories\UserRepository;
use App\Support\Flash;

final class SetupController
{
    public function __construct(private readonly string $basePath)
    {
    }

    public function show(): void
    {
        View::render('install/setup', [
            'title' => 'Install Flashy',
            'hide_nav' => true,
        ]);
    }

    public function install(): void
    {
        $host = trim((string) ($_POST['db_host'] ?? ''));
        $port = (int) ($_POST['db_port'] ?? 3306);
        $name = trim((string) ($_POST['db_name'] ?? ''));
        $user = trim((string) ($_POST['db_user'] ?? ''));
        $pass = (string) ($_POST['db_pass'] ?? '');
        $charset = trim((string) ($_POST['db_charset'] ?? 'utf8mb4'));
        $firstName = trim((string) ($_POST['first_name'] ?? ''));
        $lastName = trim((string) ($_POST['last_name'] ?? ''));
        $email = trim((string) ($_POST['email'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        $passwordConfirm = (string) ($_POST['password_confirm'] ?? '');

        stash_old_input([
            'db_host' => $host,
            'db_port' => (string) $port,
            'db_name' => $name,
            'db_user' => $user,
            'db_charset' => $charset,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => $email,
        ]);

        if (
            $host === ''
            || $name === ''
            || $user === ''
            || $firstName === ''
            || $lastName === ''
            || $email === ''
            || $password === ''
            || $passwordConfirm === ''
        ) {
            Flash::put('error', 'Database settings and admin account fields are all required.');
            redirect('/install');
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Flash::put('error', 'Enter a valid admin email address.');
            redirect('/install');
        }

        if (mb_strlen($password, 'UTF-8') < 8) {
            Flash::put('error', 'Admin password must be at least 8 characters.');
            redirect('/install');
        }

        if ($password !== $passwordConfirm) {
            Flash::put('error', 'Admin passwords do not match.');
            redirect('/install');
        }

        $config = Config::fromArray($this->basePath, [
            'host' => $host,
            'port' => $port,
            'name' => $name,
            'user' => $user,
            'pass' => $pass,
            'charset' => $charset !== '' ? $charset : 'utf8mb4',
        ]);

        $database = new Database($config);
        $pdo = $database->connection();

        Migrator::migrate($database);

        $users = new UserRepository($pdo);

        if ($users->findByEmail($email) !== null) {
            Flash::put('error', 'That admin email already exists in this database.');
            redirect('/install');
        }

        $users->create(
            $email,
            password_hash($password, PASSWORD_DEFAULT),
            true,
            $firstName,
            $lastName
        );

        Config::write($this->basePath, $config);

        clear_old_input();
        Flash::put('success', 'Flashy is installed. You can now log in.');
        redirect('/login');
    }
}
