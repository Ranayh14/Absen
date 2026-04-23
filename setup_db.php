<?php
$pdo = new PDO('mysql:host=127.0.0.1', 'root', '');
$pdo->exec('CREATE DATABASE IF NOT EXISTS laravel_absen_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
echo "Database created";
