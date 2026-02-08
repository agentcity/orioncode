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

    useEffect(() => {
        const checkAuth = async () => {
            const token = localStorage.getItem('jwt_token');

            // 1. Если токена нет — даже не пытаемся стучать в дверь
            if (!token) {
                setUser(null);
                setLoading(false);
                return;
            }

            try {
                // 2. Если токен есть, подстрахуемся и пропишем его в axios ПЕРЕД запросом
                axiosClient.defaults.headers.common['Authorization'] = `Bearer ${token}`;

                const response = await axiosClient.get('/users/me');
                setUser(response.data);
            } catch (error) {
                console.error("Auth check failed:", error);
                localStorage.removeItem('jwt_token'); // Чистим протухший токен
                delete axiosClient.defaults.headers.common['Authorization'];
                setUser(null);
            } finally {
                setLoading(false);
            }
        };
        checkAuth();
    }, []);

    const login = async (email: string, password: string) => {
        setLoading(true);
        try {
            // 1. Получаем ответ (там должен быть token)
            const response = await axiosClient.post('/login', { email, password });

            // 2. СОХРАНЯЕМ ТОКЕН (Ключ должен совпадать с axiosClient!)
            if (response.data.token) {
                const token = response.data.token;
                localStorage.setItem('jwt_token', response.data.token);
                axiosClient.defaults.headers.common['Authorization'] = `Bearer ${token}`;

            }else {
                console.error('No token received from backend!');
            }

            // 3. Теперь запрос /me уйдет с заголовком Authorization
            const userResponse = await axiosClient.get('/users/me');
            setUser(userResponse.data);
        } catch (error) {
            delete axiosClient.defaults.headers.common['Authorization'];
            console.error('Login failed:', error);
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
        window.location.href = '/login';
    };

    return (
        <AuthContext.Provider value={{ user, isAuthenticated: !!user, login, logout, loading }}>
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