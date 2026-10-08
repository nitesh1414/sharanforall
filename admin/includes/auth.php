<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

if (session_status() === PHP_SESSION_NONE) session_start();

function admin_login($username, $password){
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM admins WHERE username = ? OR email = ? LIMIT 1");
    $stmt->execute([$username, $username]);
    $admin = $stmt->fetch();
    if ($admin && password_verify($password, $admin['password'])) {
        $_SESSION['admin'] = [
            'id' => $admin['id'],
            'name' => $admin['name'],
            'username' => $admin['username'],
            'role' => $admin['role'],
        ];
        return true;
    }
    return false;
}

function admin_logout(){
    $_SESSION = [];
    session_destroy();
}

function admin_check(){
    if (empty($_SESSION['admin'])) {
        redirect(ADMIN_URL . 'login.php');
    }
}

function current_admin(){
    return $_SESSION['admin'] ?? null;
}
