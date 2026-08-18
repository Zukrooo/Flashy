<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\View;
use App\Support\Auth;
use App\Support\Flash;

final class AuthController
{
    public function __construct(private readonly Auth $auth)
    {
    }

    public function showLogin(): void
    {
        View::render('admin/login', ['title' => 'Admin login']);
    }

    public function login(): void
    {
        $email = trim((string) ($_POST['email'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');

        stash_old_input(['email' => $email]);

        if ($email === '' || $password === '') {
            Flash::put('error', 'Email and password are required.');
            redirect('/admin/login');
        }

        if (!$this->auth->attemptAdmin($email, $password)) {
            Flash::put('error', 'Invalid login details.');
            redirect('/admin/login');
        }

        clear_old_input();
        Flash::put('success', 'Welcome back.');
        redirect('/admin');
    }

    public function logout(): void
    {
        $this->auth->logoutAdmin();
        Flash::put('success', 'You have been logged out.');
        redirect('/admin/login');
    }
}
