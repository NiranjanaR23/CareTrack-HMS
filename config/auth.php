<?php
session_start();

function require_login() {
    if (!isset($_SESSION['user'])) {
        header("Location: login.php");
        exit;
    }
}

function current_user() {
    return $_SESSION['user'] ?? null;
}
?>