import React from 'react';
import { Box, Typography, Avatar, IconButton, Badge, styled } from '@mui/material';
import ArrowBackIcon from '@mui/icons-material/ArrowBack';
import WhatsAppIcon from '@mui/icons-material/WhatsApp';
import TelegramIcon from '@mui/icons-material/Telegram';
import HubIcon from '@mui/icons-material/Hub';

// Твой StyledBadge
const StyledBadge = styled(Badge, {
    shouldForwardProp: (prop) => prop !== 'isOnline',
})<{ isOnline?: boolean }>(({ theme, isOnline }) => ({
    '& .MuiBadge-badge': {
        backgroundColor: isOnline ? '#44b700' : '#bdbdbd',
        color: isOnline ? '#44b700' : '#bdbdbd',
        boxShadow: `0 0 0 2px ${theme.palette.background.paper}`,
        '&::after': isOnline ? {
            position: 'absolute', top: 0, left: 0, width: '100%', height: '100%',
            borderRadius: '50%', animation: 'ripple 1.2s infinite ease-in-out',
            border: '1px solid currentColor', content: '""',
        } : {},
    },
    '@keyframes ripple': { '0%': { transform: 'scale(.8)', opacity: 1 }, '100%': { transform: 'scale(2.4)', opacity: 0 } },
}));

const formatLastSeen = (date?: string) => {
    if (!date) return 'давно';
    const lastSeen = new Date(date);
    const now = new Date();
    const diffInSec = Math.floor((now.getTime() - lastSeen.getTime()) / 1000);
    if (diffInSec < 60) return 'только что';
    if (diffInSec < 3600) return `${Math.floor(diffInSec / 60)} мин. назад`;
    if (diffInSec < 86400) return `${Math.floor(diffInSec / 3600)} ч. назад`;
    return lastSeen.toLocaleDateString();
};

// Твоя функция иконок каналов
const getChannelIcon = (type?: string) => {
    switch (type) {
        case 'whatsapp': return <WhatsAppIcon sx={{ ml: 1, fontSize: 18, color: '#25D366' }} />;
        case 'telegram': return <TelegramIcon sx={{ ml: 1, fontSize: 18, color: '#0088cc' }} />;
        default: return <HubIcon sx={{ ml: 1, fontSize: 18, color: '#9c27b0' }} />;
    }
};

interface ChatHeaderProps {
    conversation: any;
    chatPartner: any;
    isContactOnline: boolean;
    isMobile?: boolean;
    onBack: () => void;
}

export const ChatHeader: React.FC<ChatHeaderProps> = ({
                                                          conversation,
                                                          chatPartner,
                                                          isContactOnline,
                                                          isMobile,
                                                          onBack
                                                      }) => {
    if (!conversation) return null;

    return (
        <Box sx={{ p: 2, bgcolor: 'white', borderBottom: 1, borderColor: 'divider', display: 'flex', alignItems: 'center', zIndex: 10 }}>
            {isMobile && (
                <IconButton onClick={onBack} sx={{ mr: 1 }}>
                    <ArrowBackIcon />
                </IconButton>
            )}

            <StyledBadge
                isOnline={chatPartner?.isOnline || isContactOnline}
                overlap="circular"
                anchorOrigin={{ vertical: 'bottom', horizontal: 'right' }}
                variant="dot"
            >
                <Avatar sx={{ bgcolor: conversation.type === 'internal' ? '#9c27b0' : 'primary.main' }}>
                    {conversation.contact?.mainName?.[0] || 'U'}
                </Avatar>
            </StyledBadge>

            <Box sx={{ ml: 2, minWidth: 0 }}>
                <Box sx={{ display: 'flex', alignItems: 'center' }}>
                    <Typography noWrap variant="subtitle1" sx={{ fontWeight: 'bold' }}>
                        {conversation.contact?.mainName || 'Беседа'}
                    </Typography>
                    {getChannelIcon(conversation.type)}
                </Box>
                <Typography
                    variant="caption"
                    color={chatPartner?.isOnline || isContactOnline ? "#44b700" : "text.secondary"}
                >
                    {chatPartner?.isOnline || isContactOnline
                        ? 'в сети'
                        : `был(а) ${formatLastSeen(chatPartner?.lastSeen)}`} • {conversation.type ? conversation.type.toUpperCase() : 'CHAT'}
                </Typography>
            </Box>
        </Box>
    );
};

