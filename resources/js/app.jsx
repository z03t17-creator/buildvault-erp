import '../css/app.css';
import './bootstrap';

import { createInertiaApp, router } from '@inertiajs/react';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createRoot } from 'react-dom/client';

const appName = import.meta.env.VITE_APP_NAME || 'BuildVault ERP';

function applyDocumentLocale(page) {
    const locale = page?.props?.locale ?? 'en';
    const direction = page?.props?.direction ?? 'ltr';

    document.documentElement.lang = locale;
    document.documentElement.dir = direction;
}

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.jsx`,
            import.meta.glob('./Pages/**/*.jsx'),
        ),
    setup({ el, App, props }) {
        applyDocumentLocale(props.initialPage);

        router.on('success', (event) => {
            applyDocumentLocale(event.detail.page);
        });

        const root = createRoot(el);

        root.render(<App {...props} />);
    },
    progress: {
        color: '#10b981',
    },
});

