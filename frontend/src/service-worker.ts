/* eslint-disable no-restricted-globals */
import { precacheAndRoute } from 'workbox-precaching';
import { registerRoute } from 'workbox-routing';
import { StaleWhileRevalidate, CacheFirst } from 'workbox-strategies';

const serverBase = (process.env.REACT_APP_API_URL || 'http://localhost:8080/api').replace(/\/api$/, '');
// Кэшируем билд (JS/CSS)
precacheAndRoute((self as any).__WB_MANIFEST);

// Кэшируем картинки/шрифты (живут долго)
registerRoute(
    ({ request }) => request.destination === 'image' || request.destination === 'font',
    new CacheFirst({ cacheName: 'assets' })
);

// Кэшируем API запросы (показываем старое, пока грузится новое)
registerRoute(
    ({ url }) => url.origin === serverBase,
    new StaleWhileRevalidate({ cacheName: 'api-cache' })
);
