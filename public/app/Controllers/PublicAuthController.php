<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\View;
use App\Repositories\UserProgressRepository;
use App\Repositories\UserRepository;
use App\Support\Auth;
use App\Support\Flash;

final class PublicAuthController
{
    public function __construct(
        private readonly Auth $auth,
        private readonly UserRepository $users,
        private readonly UserProgressRepository $progress
    )
    {
    }

    public function showLogin(): void
    {
        View::render('public/login', ['title' => 'Log in']);
    }

    public function login(): void
    {
        $email = trim((string) ($_POST['email'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');

        stash_old_input(['email' => $email]);

        if ($email === '' || $password === '') {
            Flash::put('error', 'Email and password are required.');
            redirect('/login');
        }

        if (!$this->auth->attemptUser($email, $password)) {
            Flash::put('error', 'Invalid login details.');
            redirect('/login');
        }

        clear_old_input();
        Flash::put('success', 'Welcome back.');
        redirect('/');
    }

    public function showRegister(): void
    {
        View::render('public/register', ['title' => 'Create account']);
    }

    public function register(): void
    {
        $firstName = trim((string) ($_POST['first_name'] ?? ''));
        $lastName = trim((string) ($_POST['last_name'] ?? ''));
        $email = trim((string) ($_POST['email'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        $passwordConfirm = (string) ($_POST['password_confirm'] ?? '');

        stash_old_input(['first_name' => $firstName, 'last_name' => $lastName, 'email' => $email]);

        if ($firstName === '' || $lastName === '' || $email === '' || $password === '' || $passwordConfirm === '') {
            Flash::put('error', 'First name, last name, email, and password fields are required.');
            redirect('/register');
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Flash::put('error', 'Enter a valid email address.');
            redirect('/register');
        }

        if (mb_strlen($password, 'UTF-8') < 8) {
            Flash::put('error', 'Password must be at least 8 characters.');
            redirect('/register');
        }

        if ($password !== $passwordConfirm) {
            Flash::put('error', 'Passwords do not match.');
            redirect('/register');
        }

        if ($this->auth->registerUser($firstName, $lastName, $email, $password) === null) {
            Flash::put('error', 'That email is already in use.');
            redirect('/register');
        }

        clear_old_input();
        Flash::put('success', 'Account created.');
        redirect('/');
    }

    public function logout(): void
    {
        $this->auth->logoutUser();
        Flash::put('success', 'You have been logged out.');
        redirect('/');
    }

    public function showProfile(): void
    {
        $this->auth->requireUser();
        $user = $this->auth->user();

        if ($user === null) {
            redirect('/login');
        }

        View::render('public/profile', [
            'title' => 'Edit profile',
            'user' => $user,
        ]);
    }

    public function updateProfile(): void
    {
        $this->auth->requireUser();
        $user = $this->auth->user();

        if ($user === null) {
            redirect('/login');
        }

        $firstName = trim((string) ($_POST['first_name'] ?? ''));
        $lastName = trim((string) ($_POST['last_name'] ?? ''));
        $email = trim((string) ($_POST['email'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        $passwordConfirm = (string) ($_POST['password_confirm'] ?? '');

        stash_old_input([
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => $email,
        ]);

        if ($firstName === '' || $lastName === '' || $email === '') {
            Flash::put('error', 'First name, last name, and email are required.');
            redirect('/profile');
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Flash::put('error', 'Enter a valid email address.');
            redirect('/profile');
        }

        if ($this->users->emailExistsForOtherUser($email, (int) $user['id'])) {
            Flash::put('error', 'That email is already in use.');
            redirect('/profile');
        }

        if ($password !== '' || $passwordConfirm !== '') {
            if (mb_strlen($password, 'UTF-8') < 8) {
                Flash::put('error', 'Password must be at least 8 characters.');
                redirect('/profile');
            }

            if ($password !== $passwordConfirm) {
                Flash::put('error', 'Passwords do not match.');
                redirect('/profile');
            }
        }

        $this->users->updateProfile((int) $user['id'], $firstName, $lastName, $email);

        if ($password !== '') {
            $this->users->updatePassword((int) $user['id'], password_hash($password, PASSWORD_DEFAULT));
        }

        $updatedUser = $this->users->find((int) $user['id']);

        if ($updatedUser !== null) {
            $this->auth->syncUserSession($updatedUser);
        }

        clear_old_input();
        Flash::put('success', 'Profile updated.');
        redirect('/profile');
    }

    public function clearData(): void
    {
        $this->auth->requireUser();
        $userId = $this->auth->userId();

        if ($userId === null) {
            redirect('/login');
        }

        $this->progress->clearUserData($userId);
        unset($_SESSION['study_sessions'], $_SESSION['last_result'], $_SESSION['last_study_path']);

        Flash::put('success', 'Your study history has been cleared.');
        redirect('/profile');
    }
}
