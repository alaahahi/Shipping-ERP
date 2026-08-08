import { cpSync, existsSync, rmSync } from 'node:fs';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';

const rootDir = dirname(fileURLToPath(import.meta.url));

/**
 * Mirror public/build → ./build so shared hosts that use the project root
 * as the web root can serve assets without a manual copy after npm run build.
 */
function mirrorBuildToWebRoot() {
    return {
        name: 'mirror-build-to-web-root',
        closeBundle() {
            const from = resolve(rootDir, 'public/build');
            const to = resolve(rootDir, 'build');

            if (!existsSync(from)) {
                return;
            }

            rmSync(to, { recursive: true, force: true });
            cpSync(from, to, { recursive: true });
        },
    };
}

export default defineConfig({
    plugins: [
        laravel({
            input: 'resources/js/app.js',
            refresh: true,
        }),
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
        mirrorBuildToWebRoot(),
    ],
});

