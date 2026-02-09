import React, { useState, useEffect } from 'react';
import { BrowserRouter as Router, Routes, Route, Navigate } from 'react-router-dom';
import { AuthProvider, useAuth } from './context/AuthContext';
import LoginPage from './pages/LoginPage';
import DashboardPage from './pages/DashboardPage';
import OfflineStub from './components/OfflineStub';
import axiosClient from './api/axiosClient';
import { CircularProgress, Box, CssBaseline, ThemeProvider, createTheme } from '@mui/material';

const theme = createTheme({
    palette: {
        primary: { main: '#1976d2' },
        secondary: { main: '#dc004e' },
        background: { default: '#f4f7f6' },
    },
});

const PrivateRoute: React.FC<{ children: React.ReactNode }> = ({ children }) => {
    const { isAuthenticated, loading } = useAuth();
    if (loading) return (
        <Box sx={{ display: 'flex', justifyContent: 'center', alignItems: 'center', height: '100vh' }}>
            <CircularProgress />
        </Box>
    );
    return isAuthenticated ? <>{children}</> : <Navigate to="/login" />;
};

const App: React.FC = () => {
    const [isOnline, setIsOnline] = useState(navigator.onLine);
    const [isServerAvailable, setIsServerAvailable] = useState(true);

    useEffect(() => {
        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.addEventListener('controllerchange', () => {
                // Когда воркер обновился — перезагружаем страницу
                window.location.reload();
            });
        }
    }, []);

    // Запрос разрешения на уведомления
    useEffect(() => {
        Notification.requestPermission();
    }, []);

    useEffect(() => {
        // 1. Мониторинг интернета на самом устройстве
        const handleStatusChange = () => setIsOnline(navigator.onLine);
        window.addEventListener('online', handleStatusChange);
        window.addEventListener('offline', handleStatusChange);

        // 2. Перехват ошибок сети от Axios (если сервер упал или неверный IP)
        const interceptor = axiosClient.interceptors.response.use(
            response => response,
            error => {
                if (!error.response) { // Ошибка сети (Network Error)
                    setIsServerAvailable(false);
                }
                return Promise.reject(error);
            }
        );

        return () => {
            window.removeEventListener('online', handleStatusChange);
            window.removeEventListener('offline', handleStatusChange);
            axiosClient.interceptors.response.eject(interceptor);
        };
    }, []);

    const handleRetry = () => {
        setIsServerAvailable(true);
        window.location.reload();
    };

    // Если нет интернета или сервер недоступен — показываем заглушку
    if (!isOnline || !isServerAvailable) {
        return (
            <ThemeProvider theme={theme}>
                <CssBaseline />
                <OfflineStub onRetry={handleRetry} />
            </ThemeProvider>
        );
    }

    return (
        <ThemeProvider theme={theme}>
            <CssBaseline />
            <Router>
                <AuthProvider>
                    <Routes>
                        <Route path="/login" element={<LoginPage />} />
                        <Route path="/dashboard/*" element={<PrivateRoute><DashboardPage /></PrivateRoute>} />
                        <Route path="/" element={<Navigate to="/dashboard" />} />
                    </Routes>
                </AuthProvider>
            </Router>
        </ThemeProvider>
    );
};

export default App;