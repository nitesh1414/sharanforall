<?php
require_once __DIR__ . '/includes/auth.php';
admin_logout();
redirect(ADMIN_URL . 'login.php');
