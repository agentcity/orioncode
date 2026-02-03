// src/api/axiosClient.ts
import axios from 'axios';

const API_URL = process.env.REACT_APP_API_URL || 'http://localhost:8080/api'; // Убедитесь, что это соответствует вашему Nginx

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
    if (token) {
        config.headers.Authorization = `Bearer ${token}`;
    }
    return config;
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