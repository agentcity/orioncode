import { Capacitor } from '@capacitor/core';

export const getBaseDomain = () => {
    const envUrl = process.env.REACT_APP_API_URL;

    // 1. ЛОКАЛЬНО (MacBook)
    if (!envUrl || envUrl.includes('localhost')) {
        const host = window.location.hostname;
        return `http://${host}:8080`;
    }

    // 2. ПРОД (Jino) или МОБИЛКА (Capacitor)
    // Берем протокол текущей страницы (http: или https:)
    const protocol = window.location.protocol;

    // Если в envUrl уже есть протокол, возвращаем как есть,
    // если нет (как мы договорились) — добавляем текущий протокол
    return envUrl.startsWith('http') ? envUrl : `${protocol}//${envUrl}`;
};

// Базовый URL для API
export const API_URL = `${getBaseDomain()}/api`;

// Универсальный URL для WebSocket
export const getWsUrl = () => {
    const envWsUrl = process.env.REACT_APP_WS_URL;
    const base = getBaseDomain();

    // Локально: меняем 8080 на 3000
    if (base.includes('localhost')) {
        return base.replace(':8080', ':3000');
    }

    // На проде: используем WS_URL из .env (ws.orioncode.ru)
    // и подставляем правильный протокол (ws или wss)
    const wsProtocol = window.location.protocol === 'https:' ? 'wss:' : 'ws:';

    if (envWsUrl) {
        return envWsUrl.startsWith('ws') ? envWsUrl : `${wsProtocol}//${envWsUrl}`;
    }

    return `${wsProtocol}//ws.orioncode.ru`;
};

export const WS_URL = getWsUrl();
