import React, { useState } from 'react';
import { useNavigate, Link as RouterLink } from 'react-router-dom';
import {
    TextField, Button, Box, Typography, Alert,
    Paper, Container, Fade, Link, Stack, keyframes
} from '@mui/material';
import { AutoAwesome, ArrowBack, PersonAddOutlined } from '@mui/icons-material';
import axios from 'axios';
import { API_URL } from '../api/config';
import { useAuth } from '../context/AuthContext';


const pulse = keyframes`
  0% { transform: scale(1); opacity: 1; }
  50% { transform: scale(1.05); opacity: 0.8; }
  100% { transform: scale(1); opacity: 1; }
`;

const RegisterPage: React.FC = () => {
    const [formData, setFormData] = useState({
        email: '',
        password: '',
        firstName: ''
    });
    const [error, setError] = useState<string | null>(null);
    const [loading, setLoading] = useState(false);
    const navigate = useNavigate();
    const { login } = useAuth(); //  Достаем метод логина

    const errorMessages: Record<string, string> = {
        'USER_ALREADY_EXISTS': 'Пользователь с таким Email уже существует. Пожалуйста, авторизуйтесь.',
        'INVALID_EMAIL': 'Некорректный формат почты.',
        'DEFAULT': 'Произошла ошибка. Попробуйте позже.',
        'WEAK_PASSWORD': 'Пароль должен быть не менее 6 символов.',
        'TOO_MANY_REQUESTS': 'Слишком много попыток. Попробуйте позже.'
    };
    const handleSubmit = async (event: React.FormEvent) => {
        event.preventDefault();
        setLoading(true);
        setError(null);

        try {
            // Отправляем данные на бэкенд
            // Бэкенд сам создаст Organization-заглушку и привяжет юзера
            await axios.post(`${API_URL}/register`, formData);

            // АВТОМАТИЧЕСКИЙ ВХОД
            // Вызываем тот же метод, что и на странице логина
            await login(formData.email, formData.password);

            // Переходим в работу (useEffect в AuthContext или этот navigate подхватят систему)
            navigate('/dashboard')

        } catch (err: any) {
            const errorCode = err.response?.data?.message; // Получаем 'USER_ALREADY_EXISTS'
            // 🚀 Подставляем перевод из нашего словаря
            setError(errorMessages[errorCode] || errorMessages['DEFAULT']);
        } finally {
            setLoading(false);
        }
    };

    return (
        <Box sx={{
            minHeight: '100vh',
            display: 'flex',
            alignItems: 'center',
            bgcolor: '#f4f7f6',
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
                        <Box sx={{ animation: `${pulse} 3s infinite ease-in-out`, mb: 2 }}>
                            <PersonAddOutlined sx={{ fontSize: 48, color: '#1976d2' }} />
                        </Box>

                        <Typography variant="h4" sx={{ fontWeight: 700, mb: 1, color: '#1976d2' }}>
                            ORION
                        </Typography>
                        <Typography variant="body2" sx={{ mb: 4, color: 'text.secondary' }}>
                            Создайте аккаунт Orion
                        </Typography>

                        {error && (
                            <Alert severity="error" sx={{ mb: 3, borderRadius: 2, textAlign: 'left' }}>
                                {error}
                            </Alert>
                        )}

                        <Box component="form" onSubmit={handleSubmit} noValidate>
                            <TextField
                                label="Ваше имя"
                                fullWidth
                                variant="outlined"
                                margin="normal"
                                value={formData.firstName}
                                onChange={(e) => setFormData({...formData, firstName: e.target.value})}
                                required
                                sx={{ '& .MuiOutlinedInput-root': { borderRadius: 3 } }}
                            />
                            <TextField
                                label="Email"
                                type="email"
                                fullWidth
                                variant="outlined"
                                margin="normal"
                                value={formData.email}
                                onChange={(e) => setFormData({...formData, email: e.target.value})}
                                required
                                sx={{ '& .MuiOutlinedInput-root': { borderRadius: 3 } }}
                            />
                            <TextField
                                label="Пароль"
                                type="password"
                                fullWidth
                                variant="outlined"
                                margin="normal"
                                value={formData.password}
                                onChange={(e) => setFormData({...formData, password: e.target.value})}
                                required
                                sx={{ '& .MuiOutlinedInput-root': { borderRadius: 3 } }}
                            />

                            <Button
                                type="submit"
                                variant="contained"
                                fullWidth
                                size="large"
                                disabled={loading}
                                sx={{
                                    mt: 4,
                                    py: 1.5,
                                    borderRadius: 3,
                                    fontWeight: 600,
                                    textTransform: 'none',
                                    boxShadow: '0 4px 12px rgba(25, 118, 210, 0.3)'
                                }}
                            >
                                {loading ? 'Создание...' : 'Зарегистрироваться'}
                            </Button>

                            <Box sx={{ mt: 3 }}>
                                <Link component={RouterLink} to="/login" sx={{
                                    display: 'inline-flex',
                                    alignItems: 'center',
                                    textDecoration: 'none',
                                    color: 'text.secondary',
                                    fontSize: '0.875rem',
                                    '&:hover': { color: '#1976d2' }
                                }}>
                                    <ArrowBack sx={{ fontSize: 16, mr: 0.5 }} /> Уже есть аккаунт? Войти
                                </Link>
                            </Box>
                        </Box>
                    </Paper>
                </Fade>
            </Container>
        </Box>
    );
};

export default RegisterPage;
