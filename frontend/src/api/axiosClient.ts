// src/api/axiosClient.ts
import axios from 'axios';
import { API_URL } from './config';

const axiosClient = axios.create({
    baseURL: API_URL,
    headers: {
        'Content-Type': 'application/json',
    },
    withCredentials: true, // Важно для работы с куками сессии или JWT
});

axiosClient.interceptors.request.use((config) => {
    // Можно добавить токен авторизации, если используете JWT
    const token = localStorage.getItem('jwt_token');
    // Проверяем существование headers перед записью
    if (token && config.headers) {
        config.headers.Authorization = `Bearer ${token}`;
    }
    
    return config;
}, (error) => {
    return Promise.reject(error);
});

axiosClient.interceptors.response.use(
    (response) => response,
    (error) => {
        // Обработка ошибок, например, редирект на логин при 401
        if (error.response && error.response.status === 401) {
            console.log('Unauthorized, redirecting to login...');
            // window.location.href = '/login'; // или использовать react-router-dom
        }
        return Promise.reject(error);
    }
);

export default axiosClient;
