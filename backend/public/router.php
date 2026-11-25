<?php
// Roteador SÓ para o servidor embutido do PHP (desenvolvimento): imita o .htaccess.
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
if (preg_match('#^/(api|entrar|cadastro|recuperar-senha|redefinir-senha|sair|app|treinador|admin|termos|privacidade)(/.*)?$#', $path)) {
    require __DIR__ . '/app.php';
    return true;
}
return false; // serve arquivos estáticos de backend/public
