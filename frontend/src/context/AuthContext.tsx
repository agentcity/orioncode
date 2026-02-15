// src/context/AuthContext.tsx
import React, { createContext, useState, useEffect, useContext } from 'react';
import axiosClient from '../api/axiosClient';
import { User } from '../types';

interface AuthContextType {
    user: User | null;
    isAuthenticated: boolean;
    login: (email: string, password: string) => Promise<void>;
    logout: () => void;
    loading: boolean;
}

const AuthContext = createContext<AuthContextType | undefined>(undefined);

export const AuthProvider: React.FC<{ children: React.ReactNode }> = ({ children }) => {
    const [user, setUser] = useState<User | null>(null);
    const [loading, setLoading] = useState(true);
    const [isAuthenticated, setAuthenticated] = useState(false);

    const login = async (email: string, password: string) => {
        setLoading(true);
        try {
            // 1. Пытаемся войти
            const response = await axiosClient.post('/login', { email, password });

            if (response.data.token) {
                const token = response.data.token;
                localStorage.setItem('jwt_token', token);
                axiosClient.defaults.headers.common['Authorization'] = `Bearer ${token}`;

                // 2. СНАЧАЛА получаем юзера, потом ставим статус
                const userResponse = await axiosClient.get('/users/me');
                setUser(userResponse.data);

                // 🚀 ТОЛЬКО ТЕПЕРЬ авторизован!
                setAuthenticated(true);
            } else {
                throw new Error('Токен не получен');
            }
        } catch (error: any) {
            // 🚀 Чистим всё при ошибке
            localStorage.removeItem('jwt_token');
            delete axiosClient.defaults.headers.common['Authorization'];
            setAuthenticated(false);
            setUser(null);

            console.error('Login failed:', error);
            // Выбрасываем ошибку, чтобы LoginPage её поймал
            throw error;
        } finally {
            setLoading(false);
        }
    };


    const logout = () => {
        localStorage.removeItem('jwt_token'); // <--- Ключ как в axiosClient!
        localStorage.removeItem('user');
        delete axiosClient.defaults.headers.common['Authorization'];
        setUser(null);
        // Опционально: редирект, чтобы сбросить все стейты
        window.location.href = '/';
    };

    useEffect(() => {
        const checkAuth = async () => {
            const token = localStorage.getItem('jwt_token');
            const savedUser = localStorage.getItem('user');

            // 1. ОПТИМИСТИЧНЫЙ ВХОД: Если есть и токен, и данные юзера — пускаем сразу!
            if (token && savedUser) {
                try {
                    const parsedUser = JSON.parse(savedUser);
                    setUser(parsedUser);
                    setAuthenticated(true);
                    setLoading(false); // 🚀 ЧАТЫ ОТКРОЮТСЯ МГНОВЕННО ТУТ

                    // Прописываем заголовок для будущих запросов
                    axiosClient.defaults.headers.common['Authorization'] = `Bearer ${token}`;
                } catch (e) {
                    console.error("Saved user corrupted");
                }
            }

            // 2. ФОНОВАЯ ПРОВЕРКА: Актуализируем данные с сервера Jino
            if (token) {
                try {
                    axiosClient.defaults.headers.common['Authorization'] = `Bearer ${token}`;
                    const response = await axiosClient.get('/users/me');

                    // Обновляем стейт и кэш свежими данными (например, если баланс изменился)
                    setUser(response.data);
                    localStorage.setItem('user', JSON.stringify(response.data));
                    setAuthenticated(true);
                } catch (error) {
                    // Если токен реально протух — только тогда выкидываем
                    console.error("Auth check failed:", error);
                    logout();
                } finally {
                    setLoading(false);
                }
            } else {
                // Токена нет совсем
                setAuthenticated(false);
                setLoading(false);
            }
        };
        checkAuth();
    }, []);



    return (
        <AuthContext.Provider value={{ user, isAuthenticated, login, logout, loading }}>
            {children}
        </AuthContext.Provider>
    );
};

export const useAuth = () => {
    const context = useContext(AuthContext);
    if (context === undefined) {
        throw new Error('useAuth must be used within an AuthProvider');
    }
    return context;
};