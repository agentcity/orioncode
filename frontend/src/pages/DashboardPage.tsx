// src/pages/DashboardPage.tsx
import React, { useEffect, useState } from 'react';
import { useAuth } from '../context/AuthContext';
import { useNavigate } from 'react-router-dom';
import { Box, Typography, AppBar, Toolbar, Button, List, ListItem, ListItemText, Divider } from '@mui/material';
import axiosClient from '../api/axiosClient';
import { Conversation, WebSocketMessagePayload } from '../types';
import { useWebSocket } from '../hooks/useWebSocket';

const DashboardPage: React.FC = () => {
    const { user, isAuthenticated, logout, loading } = useAuth();
    const navigate = useNavigate();
    const [conversations, setConversations] = useState<Conversation[]>([]);
    const { latestMessage } = useWebSocket();

    useEffect(() => {
        if (!isAuthenticated && !loading) {
            navigate('/login');
        } else if (isAuthenticated) {
            fetchConversations();
        }
    }, [isAuthenticated, loading, navigate]);

    useEffect(() => {
        if (latestMessage) {
            // Обновить список диалогов при получении нового сообщения через WS
            setConversations(prevConversations =>
                prevConversations.map(conv =>
                    conv.id === latestMessage.conversationId
                        ? { ...conv, lastMessageAt: new Date().toISOString(), unreadCount: conv.unreadCount + 1 } // Упрощенно
                        : conv
                )
            );
            // Опционально: отсортировать диалоги по lastMessageAt
            setConversations(prev => [...prev].sort((a, b) => new Date(b.lastMessageAt).getTime() - new Date(a.lastMessageAt).getTime()));
        }
    }, [latestMessage]);

    const fetchConversations = async () => {
        try {
            const response = await axiosClient.get<Conversation[]>('/conversations');
            setConversations(response.data.sort((a, b) => new Date(b.lastMessageAt).getTime() - new Date(a.lastMessageAt).getTime()));
        } catch (error) {
            console.error('Failed to fetch conversations:', error);
        }
    };

    if (loading) {
        return <Typography>Loading dashboard...</Typography>;
    }

    return (
        <Box sx={{ flexGrow: 1 }}>
            <AppBar position="static">
                <Toolbar>
                    <Typography variant="h6" component="div" sx={{ flexGrow: 1 }}>
                        Messenger Aggregator
                    </Typography>
                    {user && (
                        <Typography variant="body1" sx={{ mr: 2 }}>
                            Welcome, {user.firstName || user.email}!
                        </Typography>
                    )}
                    <Button color="inherit" onClick={logout}>
                        Logout
                    </Button>
                </Toolbar>
            </AppBar>
            <Box sx={{ display: 'flex', height: 'calc(100vh - 64px)' }}> {/* Высота без AppBar */}
                {/* Панель со списком чатов */}
                <Box sx={{ width: 300, borderRight: 1, borderColor: 'divider', overflowY: 'auto' }}>
                    <Typography variant="h6" sx={{ p: 2 }}>Conversations</Typography>
                    <List>
                        {conversations.map((conv) => (
                            <React.Fragment key={conv.id}>
                                <ListItem
                                    button
                                    onClick={() => navigate(/chat/${conv.id})}
                                    secondaryAction={
                                        conv.unreadCount > 0 && (
                                            <Box sx={{ bgcolor: 'primary.main', color: 'white', borderRadius: '50%', width: 24, height: 24, display: 'flex', alignItems: 'center', justifyContent: 'center', fontSize: '0.8rem' }}>
                                                {conv.unreadCount}
                                            </Box>
                                        )
                                    }
                                >
                                    <ListItemText
                                        primary={${conv.contact.mainName} (${conv.type})}
                                        secondary={new Date(conv.lastMessageAt).toLocaleString()}
                                    />
                                </ListItem>
                                <Divider />
                            </React.Fragment>
                        ))}
                    </List>
                </Box>
                {/* Основная область контента (например, приветствие или пустой экран) */}
                <Box sx={{ flexGrow: 1, p: 3 }}>
                    <Typography variant="h5">Select a conversation to start chatting</Typography>
                </Box>
            </Box>
        </Box>
    );
};

export default DashboardPage;

