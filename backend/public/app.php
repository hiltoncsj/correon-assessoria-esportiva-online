<?php
declare(strict_types=1);

// Front controller. Desenvolvimento: backend/public/app.php (src em ../src).
// Produção (LocalWeb): public_html/app.php, com a pasta da aplicação em ../correon (fora do public_html).
$base = is_file(__DIR__ . '/../src/bootstrap.php') ? dirname(__DIR__) : dirname(__DIR__) . '/correon';
require $base . '/src/bootstrap.php';

Correon\App::run();
