import React from 'react';
import { Typography, Button, Container } from '@mui/material';
import WifiOffIcon from '@mui/icons-material/WifiOff';

const OfflineStub: React.FC<{ onRetry: () => void }> = ({ onRetry }) => {
    return (
        <Container sx={{
            height: '100vh', display: 'flex', flexDirection: 'column',
            alignItems: 'center', justifyContent: 'center', textAlign: 'center',
            bgcolor: '#f0f2f5'
        }}>
            <WifiOffIcon sx={{ fontSize: 80, color: 'text.secondary', mb: 2 }} />
            <Typography variant="h5" gutterBottom fontWeight="bold">
                Нет подключения к серверу
            </Typography>
            <Typography variant="body1" color="text.secondary" sx={{ mb: 4 }}>
                Проверьте интернет-соединение или доступность рабочей сети.
            </Typography>
            <Button variant="contained" onClick={onRetry} sx={{ borderRadius: '20px', px: 4 }}>
                Попробовать снова
            </Button>
        </Container>
    );
};

export default OfflineStub;