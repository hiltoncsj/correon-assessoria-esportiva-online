// Copia tokens de design e logo do site para a área PHP (evita duplicar a paleta à mão).
import { copyFileSync } from 'node:fs';

copyFileSync('src/styles/tokens.css', 'backend/public/assets/tokens.css');
copyFileSync('src/assets/logo-correon.png', 'backend/public/assets/logo-correon.png');
copyFileSync('public/favicon.svg', 'backend/public/favicon.svg');
copyFileSync('src/assets/logo-correon.png', 'backend/views/mail/logo-correon.png'); // logo dos e-mails
console.log('Assets da área logada sincronizados.');
