// Copia tokens de design e logo do site para a área PHP (evita duplicar a paleta à mão).
import { copyFileSync } from 'node:fs';

copyFileSync('src/styles/tokens.css', 'backend/public/assets/tokens.css');
copyFileSync('src/assets/logo-correon.png', 'backend/public/assets/logo-correon.png');
console.log('Assets da área logada sincronizados.');
