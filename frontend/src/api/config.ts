import { Capacitor } from '@capacitor/core';

/**
 * Определяет базовый домен в зависимости от окружения
 */
export const getBaseDomain = () => {
    // 1. ПРИОРИТЕТ ДЛЯ ПРОДА (Jino)
    // Если при сборке в Docker была передана переменная REACT_APP_API_URL
    if (process.env.REACT_APP_API_URL || Capacitor.isNativePlatform()) {
        return process.env.REACT_APP_API_URL;
    }

    // 2. ДЛЯ МОБИЛЬНЫХ УСТРОЙСТВ (Capacitor)
    // Пока переделали на прод Если запускаем на iOS/Android, используем твой локальный IP
    if (Capacitor.isNativePlatform()) {
        return 'http://192.168.1.13:8080';
    }

    // 3. ДЛЯ ЛОКАЛЬНОЙ РАЗРАБОТКИ (MacBook)
    // Берем текущий хост из адресной строки (localhost или 127.0.0.1)
    const host = window.location.hostname;
    return `http://${host}:8080`;
};

// Полный URL для API запросов
export const API_URL = `${getBaseDomain()}/api`;

/**
 * URL для WebSocket (Socket.io)
 * На проде берем из переменной, локально меняем порт 8080 на 3000
 */
const base = getBaseDomain() || '';
export const WS_URL = process.env.REACT_APP_WS_URL || base.replace(':8080', ':3000');
