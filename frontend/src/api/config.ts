import { Capacitor } from '@capacitor/core';

// Функция определяет базовый домен (без /api)
export const getBaseDomain = () => {
    // Если мы в приложении на телефоне
    if (Capacitor.isNativePlatform()) {
        // Берем адрес из текущего окна (Capacitor сам подставит IP из своего конфига)
        return window.location.origin;
    }

    if (window.location.hostname === '192.168.1.13') {
        return 'http://192.168.1.13:8080';
    }

    // Если мы в браузере на ПК
    return 'http://localhost:8080';
};

export const API_URL = `${getBaseDomain()}/api`;
export const WS_URL = getBaseDomain().replace('8080', '3000'); // Меняем порт для сокетов
