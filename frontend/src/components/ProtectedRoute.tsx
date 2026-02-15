import React from 'react';
import { Navigate } from 'react-router-dom';
import { useAuth } from '../context/AuthContext';
import LoadingScreen from './LoadingScreen';

const ProtectedRoute = ({ children }: { children: React.ReactNode }) => {
    const { isAuthenticated, loading } = useAuth();

    // 🚀 Пока идет проверка токена (например, из localStorage) — показываем загрузку
    if (loading) {
        return <LoadingScreen />;
    }

    // 🚀 Если не авторизован — редирект на логин
    if (!isAuthenticated) {
        return <Navigate to="/login" replace />;
    }

    return <>{children}</>;
};

export default ProtectedRoute;
