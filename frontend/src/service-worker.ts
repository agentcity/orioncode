/* eslint-disable no-restricted-globals */
import { precacheAndRoute } from 'workbox-precaching';
import { registerRoute, NavigationRoute } from 'workbox-routing';
import { StaleWhileRevalidate, CacheFirst, NetworkOnly, NetworkFirst } from 'workbox-strategies';
import { ExpirationPlugin } from 'workbox-expiration';
import { BackgroundSyncPlugin } from 'workbox-background-sync';

interface Window {
    __WB_MANIFEST: any;
}
declare const self: ServiceWorkerGlobalScope;

const serverBase = (process.env.REACT_APP_API_URL || 'http://localhost:8080/api').replace(/\/api$/, '');

// 1. Предварительный кэш билда (то, что генерирует webpack)
// @ts-ignore: Это нужно, чтобы TS не ругался на отсутствие переменной до сборки
precacheAndRoute(self.__WB_MANIFEST);

// Регистрируем маршрут для навигации (переходы по страницам)
const navigationRoute = new NavigationRoute(new NetworkFirst({
    cacheName: 'navigations',
    plugins: [
        {
            // Если сеть упала, принудительно отдаем index.html из кэша
            handlerDidError: async () => caches.match('/index.html'),
        },
    ],
}));

registerRoute(navigationRoute);

// 2. Кэшируем картинки и шрифты (Cache First)
// Они не меняются, поэтому берем из кэша, экономя трафик
registerRoute(
    ({ request }) => request.destination === 'image' || request.destination === 'font',
    new CacheFirst({
        cacheName: 'assets-cache',
        plugins: [
            new ExpirationPlugin({
                maxEntries: 100, // Храним до 100 картинок
                maxAgeSeconds: 30 * 24 * 60 * 60, // 30 дней
            }),
        ],
    })
);

// 3. Кэшируем API запросы (Stale While Revalidate)
// Сначала показываем старое из кэша, в фоне обновляем из сети
registerRoute(
    ({ url }) => url.origin === serverBase || url.host === 'api.orioncode.ru',
    new StaleWhileRevalidate({
        cacheName: 'api-cache',
        plugins: [
            new ExpirationPlugin({
                maxEntries: 50,
                maxAgeSeconds: 24 * 60 * 60, // Храним данные 24 часа
            }),
        ],
    })
);

// 4. Фоновая синхронизация для отправки сообщений
// Если интернет пропал в момент отправки — сообщение уйдет само, когда сеть появится
const bgSyncPlugin = new BackgroundSyncPlugin('retry-queue', {
    maxRetentionTime: 24 * 60, // Пробовать отправить в течение 24 часов
});

// Регулярное выражение поймает /api/conversations/<UUID>/messages
registerRoute(
    ({ url }) => url.pathname.match(/\/api\/conversations\/[\w-]+\/messages/),
    new NetworkOnly({
        plugins: [bgSyncPlugin],
    }),
    'POST'
);

// Позволяет новому воркеру сразу брать управление (для быстрого обновления)
(self as any).addEventListener('message', (event: any) => {
    if (event.data && event.data.type === 'SKIP_WAITING') {
        (self as any).skipWaiting();
    }
});

// Добавь это в конец:
(self as any).addEventListener('install', () => {
    (self as any).skipWaiting(); // Принудительно активируем новый воркер сразу после скачивания
});

(self as any).addEventListener('activate', (event: any) => {
    (self as any).waitUntil((self as any).clients.claim()); // Заставляем воркер сразу контролировать все открытые вкладки
});

