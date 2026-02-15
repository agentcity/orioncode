import React, { useState, useEffect } from 'react';
import { useAuth } from '../context/AuthContext';
import { useNavigate, Link as RouterLink  } from 'react-router-dom';
import {
    TextField, Button, Box, Typography, Alert,
    Paper, Container, Fade, keyframes, Link, Stack
} from '@mui/material';
import { AutoAwesome } from '@mui/icons-material';
import LoadingScreen from '../components/LoadingScreen';

const pulse = keyframes`
  0% { transform: scale(1); opacity: 1; }
  50% { transform: scale(1.05); opacity: 0.8; }
  100% { transform: scale(1); opacity: 1; }
`;

const LoginPage: React.FC = () => {
    const [email, setEmail] = useState('');
    const [password, setPassword] = useState('');
    const [error, setError] = useState<string | null>(null);
    const { login, isAuthenticated, loading } = useAuth();
    const navigate = useNavigate();

    useEffect(() => {
        if (isAuthenticated) {
            navigate('/dashboard', { replace: true });
        }
    }, [isAuthenticated, navigate]);

    
    const handleSubmit = async (event: React.FormEvent) => {
        event.preventDefault();
        setError(null);
        if (!email || !password) {
            setError('Заполните все поля');
            return;
        }

        try {
            await login(email, password);
        } catch (err: any) {
            const serverMessage = err.response?.data?.message || err.response?.data?.error || '';

            if (serverMessage === 'Invalid credentials.') {
                setError('Неверный email или пароль');
            } else if (err.code === 'ERR_NETWORK') {
                setError('Нет связи с сервером Orion');
            } else {
                setError(serverMessage || 'Ошибка входа. Проверьте данные.');
            }
        }
    };

    if (loading) {
        return <LoadingScreen />;
    }

    return (
        <Box sx={{
            minHeight: '100vh',
            display: 'flex',
            alignItems: 'center',
            bgcolor: '#f4f7f6', // Тот же фон, что у загрузчика
            backgroundImage: `url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org fill='none' fill-rule='evenodd'%3E%3Cg fill='%231976d2' fill-opacity='0.03'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E")`
        }}>
            <Container maxWidth="xs">
                <Fade in={true} timeout={800}>
                    <Paper elevation={0} sx={{
                        p: 4,
                        borderRadius: 4,
                        textAlign: 'center',
                        border: '1px solid rgba(25, 118, 210, 0.12)',
                        boxShadow: '0 8px 32px rgba(0,0,0,0.05)'
                    }}>
                        {/* Логотип как в LoadingScreen */}
                        <Box sx={{ animation: `${pulse} 3s infinite ease-in-out`, mb: 2 }}>
                            <AutoAwesome sx={{ fontSize: 48, color: '#1976d2' }} />
                        </Box>

                        <Typography variant="h4" sx={{ fontWeight: 700, mb: 1, color: '#1976d2' }}>
                            ORION
                        </Typography>
                        <Typography variant="body2" sx={{ mb: 4, color: 'text.secondary' }}>
                            Войдите, чтобы продолжить
                        </Typography>

                        {error && (
                            <Alert severity="error" sx={{ mb: 3, borderRadius: 2 }}>
                                {error}
                            </Alert>
                        )}

                        <Box component="form" onSubmit={handleSubmit} noValidate>
                            <TextField
                                label="Email"
                                type="email"
                                fullWidth
                                variant="outlined"
                                margin="normal"
                                value={email}
                                onChange={(e) => setEmail(e.target.value)}
                                required
                                sx={{ '& .MuiOutlinedInput-root': { borderRadius: 3 } }}
                            />
                            <TextField
                                label="Пароль"
                                type="password"
                                fullWidth
                                variant="outlined"
                                margin="normal"
                                value={password}
                                onChange={(e) => setPassword(e.target.value)}
                                required
                                sx={{ '& .MuiOutlinedInput-root': { borderRadius: 3 } }}
                            />
                            <Box sx={{ textAlign: 'right', mt: 1 }}>
                                <Link component={RouterLink} to="/forgot-password" variant="caption" sx={{ color: '#1976d2', textDecoration: 'none', fontWeight: 500 }}>
                                    Забыли пароль?
                                </Link>
                            </Box>
                            <Button
                                type="submit"
                                variant="contained"
                                fullWidth
                                size="large"
                                sx={{
                                    mt: 4,
                                    py: 1.5,
                                    borderRadius: 3,
                                    fontWeight: 600,
                                    textTransform: 'none',
                                    fontSize: '1rem',
                                    boxShadow: '0 4px 12px rgba(25, 118, 210, 0.3)'
                                }}
                            >
                                Войти
                            </Button>
                            <Stack direction="row" spacing={1} justifyContent="center" sx={{ mt: 3 }}>
                                <Typography variant="body2" color="text.secondary">
                                    Нет аккаунта?
                                </Typography>
                                <Link component={RouterLink} to="/register" variant="body2" sx={{ color: '#1976d2', textDecoration: 'none', fontWeight: 600 }}>
                                    Создать профиль
                                </Link>
                            </Stack>
                        </Box>
                    </Paper>
                </Fade>
            </Container>
        </Box>
    );
};

export default LoginPage;
