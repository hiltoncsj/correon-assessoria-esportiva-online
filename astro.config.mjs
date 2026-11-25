// @ts-check
import { defineConfig } from 'astro/config';

// https://astro.build/config
// Em desenvolvimento, /api vai para o servidor PHP local; em produção é o mesmo domínio.
export default defineConfig({
	vite: { server: { proxy: { '/api': 'http://localhost:8080' } } },
});
