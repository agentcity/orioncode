import React, { useState } from 'react';
import { Link as RouterLink } from 'react-router-dom';
import {
    TextField, Button, Box, Typography, Alert,
    Paper, Container, Fade, Link, keyframes
} from '@mui/material';
import { MailOutline, ArrowBack } from '@mui/icons-material';
import axios from 'axios';

const pulse = keyframes`
  0% { transform: scale(1); opacity: 1; }
  50% { transform: scale(1.05); opacity: 0.8; }
  100% { transform: scale(1); opacity: 1; }
`;

const ForgotPasswordPage: React.FC = () => {
    const [email, setEmail] = useState('');
    const [status, setStatus] = useState<'idle' | 'loading' | 'success' | 'error'>('idle');
    const [message, setMessage] = useState('');

    const handleSubmit = async (e: React.FormEvent) => {
        e.preventDefault();
        setStatus('loading');
        try {
            //  API: /api/forgot-password
            await axios.post('https://api.orioncode.ru/forgot-password', { email });
            setStatus('success');
            setMessage('Инструкции по сбросу пароля отправлены на ваш email.');
        } catch (err: any) {
            setStatus('error');
            setMessage(err.response?.data?.message || 'Ошибка. Проверьте правильность Email.');
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
                            <MailOutline sx={{ fontSize: 48, color: '#1976d2' }} />
                        </Box>

                        <Typography variant="h5" sx={{ fontWeight: 700, mb: 1, color: '#1976d2' }}>
                            Восстановление
                        </Typography>

                        {status === 'success' ? (
                            <Fade in={true}>
                                <Box>
                                    <Alert severity="success" sx={{ mt: 2, mb: 3, borderRadius: 2 }}>
                                        {message}
                                    </Alert>
                                    <Button component={RouterLink} to="/login" variant="outlined" fullWidth sx={{ borderRadius: 3 }}>
                                        Вернуться ко входу
                                    </Button>
                                </Box>
                            </Fade>
                        ) : (
                            <Box component="form" onSubmit={handleSubmit} noValidate>
                                <Typography variant="body2" sx={{ mb: 3, color: 'text.secondary' }}>
                                    Введите ваш Email, и мы пришлем ссылку для сброса пароля.
                                </Typography>

                                {status === 'error' && (
                                    <Alert severity="error" sx={{ mb: 2, borderRadius: 2 }}>{message}</Alert>
                                )}

                                <TextField
                                    label="Email"
                                    type="email"
                                    fullWidth
                                    variant="outlined"
                                    value={email}
                                    onChange={(e) => setEmail(e.target.value)}
                                    required
                                    sx={{ '& .MuiOutlinedInput-root': { borderRadius: 3 } }}
                                />

                                <Button
                                    type="submit"
                                    variant="contained"
                                    fullWidth
                                    size="large"
                                    disabled={status === 'loading'}
                                    sx={{
                                        mt: 4,
                                        py: 1.5,
                                        borderRadius: 3,
                                        fontWeight: 600,
                                        textTransform: 'none',
                                    }}
                                >
                                    {status === 'loading' ? 'Отправка...' : 'Отправить ссылку'}
                                </Button>

                                <Box sx={{ mt: 3 }}>
                                    <Link component={RouterLink} to="/login" sx={{
                                        display: 'inline-flex',
                                        alignItems: 'center',
                                        textDecoration: 'none',
                                        color: 'text.secondary',
                                        '&:hover': { color: '#1976d2' }
                                    }}>
                                        <ArrowBack sx={{ fontSize: 16, mr: 0.5 }} /> Назад к входу
                                    </Link>
                                </Box>
                            </Box>
                        )}
                    </Paper>
                </Fade>
            </Container>
        </Box>
    );
};

export default ForgotPasswordPage;
