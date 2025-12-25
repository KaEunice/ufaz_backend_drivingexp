<?php
session_start();
require_once __DIR__ . '/../config/database.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Supervised Driving Experience</title>
    <link rel="stylesheet" href="css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="js/scripts.js" defer></script>
</head>
<body>
    <nav class="navbar">
        <div class="container">
            <h1 class="logo">Driving Experience</h1>
            <div class="nav-links">
                <a href="index.php">Home</a>
                <a href="add_experience.php">Add Experience</a>
                <a href="dashboard.php">Dashboard</a>
            </div>
        </div>
    </nav>
    <main class="container">
