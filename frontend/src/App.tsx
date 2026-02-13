import React, { useState, useEffect } from 'react';
import { BrowserRouter as Router, Routes, Route, Navigate } from 'react-router-dom';
import { AuthProvider, useAuth } from './context/AuthContext';
import LoginPage from './pages/LoginPage';
import DashboardPage from './pages/DashboardPage';
import OfflineStub from './components/OfflineStub';
import axiosClient from './api/axiosClient';
import LoadingScreen from './components/LoadingScreen';
import { Box, CssBaseline, ThemeProvider, createTheme } from '@mui/material';
import { useWebSocket } from './hooks/useWebSocket';
import { LocalNotifications } from '@capacitor/local-notifications';


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
    const { isAuthenticated, loading, user } = useAuth();
    const [isOnline, setIsOnline] = useState(navigator.onLine);
    const [isServerAvailable, setIsServerAvailable] = useState(true);

    // 1. СНАЧАЛА ВСЕ ХУКИ (БЕЗ УСЛОВИЙ ВЫШЕ) 🚀
    useWebSocket(undefined, user?.id);

    useEffect(() => {
        const requestPushPermission = async () => {
            try {
                if (navigator.userAgent.includes('Android')) {
                    await LocalNotifications.requestPermissions();
                } else if ('Notification' in window) {
                    await Notification.requestPermission();
                }
            } catch (e) {
                console.warn("Push permission error:", e);
            }
        };
        requestPushPermission();
    }, []);

    useEffect(() => {
        const handleStatusChange = () => setIsOnline(navigator.onLine);
        window.addEventListener('online', handleStatusChange);
        window.addEventListener('offline', handleStatusChange);

        const interceptor = axiosClient.interceptors.response.use(
            response => response,
            error => {
                if (!error.response) setIsServerAvailable(false);
                return Promise.reject(error);
            }
        );

        return () => {
            window.removeEventListener('online', handleStatusChange);
            window.removeEventListener('offline', handleStatusChange);
            axiosClient.interceptors.response.eject(interceptor);
        };
    }, []);

    useEffect(() => {
        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.addEventListener('controllerchange', () => {
                window.location.reload();
            });
        }
    }, []);

    // 2. И ТОЛЬКО ТЕПЕРЬ УСЛОВНЫЕ RETURN 🛑
    if (loading) {
        return <LoadingScreen />;
    }

    if (!isOnline || !isServerAvailable) {
        return (
            <Box sx={{ height: '100vh' }}>
                <OfflineStub onRetry={() => window.location.reload()} />
            </Box>
        );
    }

    // 3. ОСНОВНОЙ РЕНДЕР
    return (
        <Routes>
            <Route path="/login" element={!isAuthenticated ? <LoginPage /> : <Navigate to="/dashboard" />} />
            <Route path="/dashboard/*" element={<PrivateRoute><DashboardPage /></PrivateRoute>} />
            <Route path="/" element={<Navigate to={isAuthenticated ? "/dashboard" : "/login"} />} />
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