import React, { useEffect, useState } from 'react';
import {
    Box,
    List,
    ListItemButton,
    ListItemAvatar,
    ListItemText,
    Avatar,
    Typography,
    AppBar,
    Toolbar,
    useMediaQuery,
    useTheme,
    Badge,
    IconButton,
} from '@mui/material';
import { Routes, Route, useNavigate, useLocation } from 'react-router-dom';
import WhatsAppIcon from '@mui/icons-material/WhatsApp';
import TelegramIcon from '@mui/icons-material/Telegram';
import AddCommentIcon from '@mui/icons-material/AddComment'; // Иконка для нового чата
import HubIcon from '@mui/icons-material/Hub';
import ChatPage from './ChatPage';
import axiosClient from '../api/axiosClient';
import { Conversation} from '../types';
import { useWebSocket } from '../hooks/useWebSocket';
import { UserSelector } from '../components/UserSelector'; // Импорт нового компонента
import LogoutIcon from '@mui/icons-material/Logout';
import { useAuth } from '../context/AuthContext';
import {  styled, Theme  } from '@mui/material/styles';

const StyledBadge = styled(Badge)(({ theme }: { theme: Theme }) => ({
    '& .MuiBadge-badge': {
        backgroundColor: '#44b700',
        color: '#44b700',
        boxShadow: `0 0 0 2px ${theme.palette.background.paper}`,
        width: 10,
        height: 10,
        borderRadius: '50%',
    },
}));

// 2. Функция форматирования времени
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

const DashboardPage: React.FC = () => {
    const theme = useTheme();
    const isMobile = useMediaQuery(theme.breakpoints.down('md'));
    const navigate = useNavigate();
    const location = useLocation();

    const activeChatId = location.pathname.split('/').pop();
    const isChatOpen = location.pathname.includes('/chat/');

    const [conversations, setConversations] = useState<Conversation[]>([]);
    const [isUserSelectorOpen, setIsUserSelectorOpen] = useState(false);
    const { latestMessage } = useWebSocket();
    const { logout } = useAuth();


    useEffect(() => {
        fetchConversations();
    }, []);

    useEffect(() => {
        if (latestMessage?.event === 'userStatusChanged') {
            const { userId, status, lastSeen } = latestMessage;

            setConversations(prev => prev.map(conv => {
                // Проверяем, относится ли статус к контакту в этом чате
                if (conv.contact && conv.contact.id === userId) {
                    return {
                        ...conv,
                        contact: {
                            ...conv.contact,
                            isOnline: status === 'online',
                            lastSeen: lastSeen || new Date().toISOString()
                        }
                    };
                }
                return conv;
            }));
        }
    }, [latestMessage]);

    const fetchConversations = async () => {
        try {
            const res = await axiosClient.get<Conversation[]>('/conversations');
            setConversations(res.data);
        } catch (err) {
            console.error('Failed to fetch conversations', err);
        }
    };

    // Метод создания внутреннего чата
    const handleStartInternalChat = async (userId: string) => {
        try {
            const res = await axiosClient.post('/conversations/internal', { userId });
            setIsUserSelectorOpen(false);
            await fetchConversations();
            navigate(`/dashboard/chat/${res.data.id}`);
        } catch (err) {
            console.error('Failed to start internal chat', err);
        }
    };

    const getChannelIcon = (type: string | undefined) => {
        const t = type?.toLowerCase();
        if (t === 'whatsapp') return <WhatsAppIcon sx={{ fontSize: 14, color: '#25D366' }} />;
        if (t === 'telegram') return <TelegramIcon sx={{ fontSize: 14, color: '#24A1DE' }} />;
        if (t === 'orion') return <HubIcon sx={{ fontSize: 14, color: '#666' }} />;
        return null;
    };

    return (
        <Box sx={{ display: 'flex', height: '100vh', overflow: 'hidden', bgcolor: 'background.default' }}>
            {(!isMobile || !isChatOpen) && (
                <Box
                    sx={{
                        width: isMobile ? '100%' : 350,
                        flexShrink: 0,
                        borderRight: 1,
                        borderColor: 'divider',
                        display: 'flex',
                        flexDirection: 'column',
                        bgcolor: 'white',
                    }}
                >
                    <AppBar position="static" elevation={0} sx={{ bgcolor: 'white', borderBottom: 1, borderColor: 'divider' }}>
                        <Toolbar sx={{ justifyContent: 'space-between' }}>
                            <Typography variant="h6" color="primary" sx={{ fontWeight: 'bold' }}>
                                ORION
                            </Typography>
                            <Box>
                                <IconButton color="primary" onClick={() => setIsUserSelectorOpen(true)}>
                                    <AddCommentIcon />
                                </IconButton>
                                <IconButton color="error" onClick={logout} title="Выйти" sx={{ ml: 0.5 }}>
                                    <LogoutIcon fontSize="small" />
                                </IconButton>
                            </Box>
                        </Toolbar>
                    </AppBar>

                    <List sx={{ flexGrow: 1, overflowY: 'auto', p: 0 }}>
                        {conversations.map((conv) => (
                            <ListItemButton
                                key={conv.id}
                                selected={activeChatId === conv.id}
                                onClick={() => navigate(`/dashboard/chat/${conv.id}`)}
                                sx={{
                                    borderBottom: '1px solid #f0f0f0',
                                    '&.Mui-selected': { bgcolor: '#e3f2fd' },
                                    py: 1.5 // Немного увеличим отступ для двух строк статуса
                                }}
                            >
                                {/* СТАТУС ОНЛАЙН (Сверху) */}
                                <StyledBadge
                                    overlap="circular"
                                    anchorOrigin={{ vertical: 'bottom', horizontal: 'right' }}
                                    variant="dot"
                                    invisible={!conv.contact?.isOnline}
                                >

                                    <Avatar sx={{ bgcolor: conv.type === 'orion' ? '#9c27b0' : 'primary.main' }}>
                                        {conv.contact?.mainName?.[0] || 'U'}
                                    </Avatar>
                                </StyledBadge>

                                <ListItemText
                                    sx={{ ml: 2 }}
                                    primary={
                                        <Typography variant="subtitle2" sx={{ fontWeight: activeChatId === conv.id ? 'bold' : 'medium' }}>
                                            {conv.contact?.mainName || 'Неизвестно'}
                                        </Typography>
                                    }
                                    secondary={
                                        <Box component="span" sx={{ display: 'flex', flexDirection: 'column' }}>
                                            {/* ТЕКСТ СТАТУСА */}
                                            <Typography
                                                variant="caption"
                                                sx={{
                                                    color: conv.contact?.isOnline ? '#44b700' : 'text.secondary',
                                                    fontWeight: conv.contact?.isOnline ? 600 : 400,
                                                    fontSize: '0.7rem'
                                                }}
                                            >
                                                {conv.contact?.isOnline ? 'в сети' : `был(а) ${formatLastSeen(conv.contact?.lastSeen)}`}
                                            </Typography>
                                            <Typography variant="caption" sx={{ opacity: 0.8, textTransform: 'uppercase' }}>
                                                {conv.type || 'ORION'}  {getChannelIcon(conv.type)}
                                            </Typography>
                                        </Box>
                                    }
                                />

                                {conv.unreadCount > 0 && (
                                    <Badge badgeContent={conv.unreadCount} color="error" sx={{ mr: 2 }} />
                                )}
                            </ListItemButton>
                        ))}
                    </List>
                </Box>
            )}

            {(!isMobile || isChatOpen) && (
                <Box sx={{ flexGrow: 1, height: '100%', minWidth: 0 }}>
                    <Routes>
                        <Route path="chat/:id" element={<ChatPage isMobile={isMobile} />} />
                        <Route
                            path="/"
                            element={
                                <Box sx={{ display: 'flex', alignItems: 'center', justifyContent: 'center', height: '100%', color: 'text.secondary' }}>
                                    <Typography>Выберите чат для начала общения</Typography>
                                </Box>
                            }
                        />
                    </Routes>
                </Box>
            )}

            {/* Модальное окно выбора пользователя */}
            <UserSelector
                open={isUserSelectorOpen}
                onClose={() => setIsUserSelectorOpen(false)}
                onSelect={handleStartInternalChat}
            />
        </Box>
    );
};

export default DashboardPage;
