import { Capacitor } from '@capacitor/core';

export const getBaseDomain = () => {
    // Если мы на мобилке или открыли по IP
    const host = window.location.hostname; // Это будет '192.168.1.13' или 'localhost'

    // Если Capacitor (телефон), возвращаем жесткий IP компа
    if (Capacitor.isNativePlatform()) {
        return 'http://192.168.1.13:8080';
    }

    return `http://${host}:8080`;
};

export const API_URL = `${getBaseDomain()}/api`;

// Сокеты на порту 3000 (согласно docker ps)
export const WS_URL = getBaseDomain().replace(':8080', ':3000');
