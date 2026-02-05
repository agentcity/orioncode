import { Capacitor } from '@capacitor/core';

export const getBaseDomain = () => {
    // В Capacitor window.location.origin возвращает адрес из capacitor.config.ts
    if (Capacitor.isNativePlatform()) {
        return window.location.origin;
    }
    if (window.location.hostname === '192.168.1.13') {
        return 'http://192.168.1.13:8080';
    }
    return 'http://localhost:8080';
};

export const API_URL = `${getBaseDomain()}/api`;
export const WS_URL = getBaseDomain().replace('8080', '3000');