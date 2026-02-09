import React from 'react';
import { Box, CircularProgress, Typography, keyframes } from '@mui/material';
import { AutoAwesome } from '@mui/icons-material'; // Или любая твоя иконка/лого

const pulse = keyframes`
  0% { transform: scale(1); opacity: 1; }
  50% { transform: scale(1.1); opacity: 0.7; }
  100% { transform: scale(1); opacity: 1; }
`;

const LoadingScreen: React.FC = () => {
    return (
        <Box
            sx={{
                height: '100vh',
                width: '100vw',
                display: 'flex',
                flexDirection: 'column',
                justifyContent: 'center',
                alignItems: 'center',
                bgcolor: '#f4f7f6', // Твой фоновый цвет
            }}
        >
            <Box
                sx={{
                    animation: `${pulse} 2s infinite ease-in-out`,
                    mb: 3,
                    display: 'flex',
                    flexDirection: 'column',
                    alignItems: 'center'
                }}
            >
                {/* Здесь можно вставить <img src="/logo.png" /> */}
                <AutoAwesome sx={{ fontSize: 60, color: '#1976d2' }} />
                <Typography
                    variant="h5"
                    sx={{
                        mt: 2,
                        fontWeight: 600,
                        color: '#1976d2',
                        letterSpacing: '1px'
                    }}
                >
                    ORION
                </Typography>
            </Box>

            <CircularProgress
                size={40}
                thickness={4}
                sx={{ color: '#1976d2', opacity: 0.8 }}
            />

            <Typography
                variant="caption"
                sx={{ mt: 2, color: 'text.secondary', opacity: 0.6 }}
            >
                Загрузка данных...
            </Typography>
        </Box>
    );
};

export default LoadingScreen;
