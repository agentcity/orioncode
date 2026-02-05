import React, { useEffect, useState } from 'react';
import { useAuth } from '../context/AuthContext';
import { useNavigate, Routes, Route, useParams, useLocation } from 'react-router-dom';
import { Box, Typography, AppBar, Toolbar, Button, List, ListItemButton, ListItemText, Avatar, Badge, useMediaQuery, useTheme } from '@mui/material';
import WhatsAppIcon from '@mui/icons-material/WhatsApp';
import TelegramIcon from '@mui/icons-material/Telegram';
import HubIcon from '@mui/icons-material/Hub';
import axiosClient from '../api/axiosClient';
import { Conversation } from '../types';
import { useWebSocket } from '../hooks/useWebSocket';
import ChatPage from './ChatPage';

const DashboardPage: React.FC = () => {
    const { user, isAuthenticated, logout, loading } = useAuth();
    const navigate = useNavigate();
    const location = useLocation();
    const theme = useTheme();

    const isMobile = useMediaQuery(theme.breakpoints.down('md'));

    // Исправленное извлечение ID (берем последний сегмент)
    const pathSegments = location.pathname.split('/');
    const activeChatId = pathSegments.includes('chat') ? pathSegments[pathSegments.indexOf('chat') + 1] : null;
    const isChatOpen = !!activeChatId;

    const [conversations, setConversations] = useState<Conversation[]>([]);
    const { latestMessage, socket } = useWebSocket(undefined, user?.id);

    useEffect(() => {
        if (socket && user?.id && isAuthenticated) {
            socket.emit("authenticate", user.id);
        }
    }, [socket, user, isAuthenticated]);

    useEffect(() => {
        if (!isAuthenticated && !loading) navigate('/login');
        else if (isAuthenticated) fetchConversations();
    }, [isAuthenticated, loading]);

    useEffect(() => {
        if (latestMessage && latestMessage.conversationId) {
            setConversations(prev => {
                const index = prev.findIndex(c => c.id === latestMessage.conversationId);
                const updatedList = [...prev];
                if (index !== -1) {
                    const targetChat = { ...updatedList[index] };
                    targetChat.lastMessageAt = latestMessage.sentAt;
                    if (latestMessage.direction === 'incoming' && activeChatId !== latestMessage.conversationId) {
                        targetChat.unreadCount = (targetChat.unreadCount || 0) + 1;
                    }
                    updatedList.splice(index, 1);
                    return [targetChat, ...updatedList];
                }
                return updatedList;
            });
        }
    }, [latestMessage, activeChatId]);

    useEffect(() => {
        if (activeChatId && isChatOpen) {
            setConversations(prev => prev.map(c => c.id === activeChatId ? { ...c, unreadCount: 0 } : c));
        }
    }, [activeChatId, isChatOpen]);

    const fetchConversations = async () => {
        try {
            const res = await axiosClient.get<Conversation[]>('/conversations');
            setConversations(res.data.sort((a, b) => new Date(b.lastMessageAt).getTime() - new Date(a.lastMessageAt).getTime()));
        } catch (err) { console.error(err); }
    };

    if (loading) return <Typography sx={{p: 2}}>Загрузка...</Typography>;

    return (
        <Box sx={{ display: 'flex', flexDirection: 'column', height: '100vh', overflow: 'hidden' }}>
            {(!isMobile || !isChatOpen) && (
                <AppBar position="static" elevation={0} sx={{ borderBottom: 1, borderColor: 'divider' }}>
                    <Toolbar>
                        <Typography variant="h6" sx={{ flexGrow: 1, fontWeight: 'bold' }}>ORION</Typography>
                        <Button color="inherit" onClick={logout}>Выход</Button>
                    </Toolbar>
                </AppBar>
            )}

            <Box sx={{ display: 'flex', flexGrow: 1, overflow: 'hidden' }}>
                {(!isMobile || !isChatOpen) && (
                    <Box sx={{
                        width: isMobile ? '100%' : 350,
                        flexShrink: 0,
                        borderRight: isMobile ? 0 : 1,
                        borderColor: 'divider',
                        overflowY: 'auto',
                        bgcolor: 'white'
                    }}>
                        <List sx={{ p: 0 }}>
                            {conversations.map((conv) => (
                                <ListItemButton
                                    key={conv.id}
                                    selected={activeChatId === conv.id}
                                    // ИСПРАВЛЕНО: Абсолютный путь
                                    onClick={() => navigate(`/dashboard/chat/${conv.id}`)}
                                    sx={{ borderBottom: '1px solid #f5f5f5', py: 1.5 }}
                                >
                                    <Badge
                                        overlap="circular"
                                        anchorOrigin={{ vertical: 'bottom', horizontal: 'right' }}
                                        badgeContent={<Box sx={{ bgcolor: 'white', borderRadius: '50%', display: 'flex', boxShadow: 1, p: '1px' }}>
                                            {conv.type === 'telegram' ? <TelegramIcon sx={{ fontSize: 16, color: '#24A1DE' }} /> : <WhatsAppIcon sx={{ fontSize: 16, color: '#25D366' }} />}
                                        </Box>}
                                    >
                                        <Badge color="error" badgeContent={conv.unreadCount} invisible={conv.unreadCount === 0}>
                                            <Avatar sx={{ bgcolor: '#eee', color: '#555' }}>{conv.contact.mainName[0]}</Avatar>
                                        </Badge>
                                    </Badge>
                                    <ListItemText sx={{ ml: 2 }} primary={conv.contact.mainName} secondary={conv.type.toUpperCase()} />
                                    <Typography variant="caption" color="text.secondary">
                                        {new Date(conv.lastMessageAt).toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'})}
                                    </Typography>
                                </ListItemButton>
                            ))}
                        </List>
                    </Box>
                )}

                {(!isMobile || isChatOpen) && (
                    <Box sx={{ flexGrow: 1, minWidth: 0, height: '100%', bgcolor: '#f0f2f5' }}>
                        <Routes>
                            <Route path="chat/:id" element={<ChatPage isMobile={isMobile} />} />
                            <Route path="*" element={!isMobile && <Box sx={{ display: 'flex', alignItems: 'center', justifyContent: 'center', height: '100%' }}><Typography color="text.secondary">Выберите чат</Typography></Box>} />
                        </Routes>
                    </Box>
                )}
            </Box>
        </Box>
    );
};

export default DashboardPage;