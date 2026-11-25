<?php
// Roteador SÓ para o servidor embutido do PHP (desenvolvimento): imita o .htaccess.
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
if (preg_match('#^/(api|entrar|trocar-senha|cadastro|recuperar-senha|redefinir-senha|sair|app|treinador|admin|termos|privacidade)(/.*)?$#', $path)) {
    require __DIR__ . '/app.php';
    return true;
}
// No computador local o site (Landing) roda em outra porta: "/" redireciona para ele.
// Em produção isto não existe: o index.html do site está na própria raiz.
if ($path === '/') {
    $env = @file_get_contents(dirname(__DIR__, 2) . '/.env') ?: '';
    $site = preg_match('/^SITE_URL=(\S+)/m', $env, $m) ? $m[1] : 'http://localhost:4321/';
    if (preg_match('#^https?://#', $site)) {
        header('Location: ' . $site, true, 302);
        return true;
    }
}
return false; // serve arquivos estáticos de backend/public
