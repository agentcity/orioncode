import React, { useState, useEffect } from 'react';
import { BrowserRouter as Router, Routes, Route, Navigate } from 'react-router-dom';
import { AuthProvider, useAuth } from './context/AuthContext';
import LoginPage from './pages/LoginPage';
import DashboardPage from './pages/DashboardPage';
import OfflineStub from './components/OfflineStub';
import axiosClient from './api/axiosClient';
import LoadingScreen from './components/LoadingScreen';
import { LocalNotifications } from '@capacitor/local-notifications';
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
    if (loading) return <LoadingScreen />;
    return isAuthenticated ? <>{children}</> : <Navigate to="/login" />;
};

const AppContent: React.FC = () => {
    const { isAuthenticated, loading } = useAuth();
    const [isOnline, setIsOnline] = useState(navigator.onLine);
    const [isServerAvailable, setIsServerAvailable] = useState(true);

    useEffect(() => {
        const requestPushPermission = async () => {
            // Проверяем, на мобилке мы или в браузере
            if (navigator.userAgent.includes('Android')) {
                const status = await LocalNotifications.requestPermissions();
                console.log('Permission status:', status);
            } else if ('Notification' in window) {
                Notification.requestPermission();
            }
        };

        requestPushPermission();
    }, []);

    useEffect(() => {
        // 1. Проверяем наличие объекта Notification в глобальной области видимости
        if (typeof window !== 'undefined' && 'Notification' in window) {
            Notification.requestPermission().catch((err) =>
                console.warn("Уведомления заблокированы или ошибка:", err)
            );
        } else {
            console.log("Этот браузер не поддерживает системные уведомления");
        }
    }, []);

    useEffect(() => {
        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.addEventListener('controllerchange', () => {
                // Когда воркер обновился — перезагружаем страницу
                window.location.reload();
            });
        }
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

    if (loading) {
        return <LoadingScreen />;
    }

    // Если нет сети — заглушка
    if (!isOnline || !isServerAvailable) {
        return (
            <Box sx={{ height: '100vh' }}>
                <OfflineStub onRetry={() => window.location.reload()} />
            </Box>
        );
    }

    return (
        <Routes>
            {/* Если залогинен и лезем на /login — кидаем в дашборд */}
            <Route path="/login" element={!isAuthenticated ? <LoginPage /> : <Navigate to="/dashboard" />} />

            {/* Защищенный роут */}
            <Route path="/dashboard/*" element={<PrivateRoute><DashboardPage /></PrivateRoute>} />

            {/* Корень: решаем куда отправить на основе авторизации */}
            <Route path="/" element={<Navigate to={isAuthenticated ? "/dashboard" : "/login"} />} />

            {/* Если зашли на несуществующий путь */}
            <Route path="*" element={<Navigate to="/" />} />
        </Routes>
    );
};

const App: React.FC = () => {
    return (
        <ThemeProvider theme={theme}>
            <CssBaseline />
            <Router>
                <AuthProvider>
                    <AppContent />
                </AuthProvider>
            </Router>
        </ThemeProvider>
    );
};

export default App;