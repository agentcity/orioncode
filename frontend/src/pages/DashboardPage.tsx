import React, { useEffect, useState } from 'react';
import {
    Box,
    List,
    ListItemButton,
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
import { Conversation } from '../types';
import { useWebSocket } from '../hooks/useWebSocket';
import { UserSelector } from '../components/UserSelector'; // Импорт нового компонента
import LogoutIcon from '@mui/icons-material/Logout';
import { useAuth } from '../context/AuthContext';
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
        if (latestMessage?.conversationId) {
            setConversations((prev) => {
                const list = [...prev];
                const idx = list.findIndex((c) => c.id === latestMessage.conversationId);
                if (idx !== -1) {
                    const item = { ...list[idx], lastMessageAt: latestMessage.sentAt };
                    list.splice(idx, 1);
                    return [item, ...list];
                }
                return list;
            });
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
        if (t === 'internal') return <HubIcon sx={{ fontSize: 14, color: '#666' }} />;
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
                                }}
                            >
                                <Badge
                                    overlap="circular"
                                    anchorOrigin={{ vertical: 'bottom', horizontal: 'right' }}
                                    badgeContent={getChannelIcon(conv.type)}
                                >
                                    <Avatar sx={{ bgcolor: conv.type === 'internal' ? '#9c27b0' : 'primary.main' }}>
                                        {conv.contact?.mainName?.[0] || 'U'}
                                    </Avatar>
                                </Badge>
                                <ListItemText
                                    sx={{ ml: 2 }}
                                    primary={
                                        <Typography variant="subtitle2" sx={{ fontWeight: activeChatId === conv.id ? 'bold' : 'medium' }}>
                                            {conv.contact?.mainName || 'Неизвестно'}
                                        </Typography>
                                    }
                                    secondary={conv.type?.toUpperCase() || 'CHAT'}
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
