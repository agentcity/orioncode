// src/pages/ChatPage.tsx
import React, { useEffect, useState, useRef } from 'react';
import { useParams } from 'react-router-dom';
import { Box, Typography, Paper, TextField, Button, AppBar, Toolbar, IconButton } from '@mui/material';
import ArrowBackIcon from '@mui/icons-material/ArrowBack';
import { useNavigate } from 'react-router-dom';
import axiosClient from '../api/axiosClient';
import { Conversation, Message, WebSocketMessagePayload } from '../types';
import { useAuth } from '../context/AuthContext';
import { useWebSocket } from '../hooks/useWebSocket';

const ChatPage: React.FC = () => {
    const { conversationId } = useParams<{ conversationId: string }>();
    const navigate = useNavigate();
    const { user } = useAuth();
    const { latestMessage } = useWebSocket();

    const [conversation, setConversation] = useState<Conversation | null>(null);
    const [messages, setMessages] = useState<Message[]>([]);
    const [newMessageText, setNewMessageText] = useState('');
    const messagesEndRef = useRef<HTMLDivElement>(null);

    useEffect(() => {
        if (conversationId) {
            fetchConversationAndMessages(conversationId);
        }
    }, [conversationId]);

    useEffect(() => {
        if (latestMessage && latestMessage.conversationId === conversationId) {
            // Добавляем новое сообщение, если оно относится к текущему чату
            // TODO: Fetch actual message from API or construct a Message object from payload
            const newMessage: Message = {
                id: latestMessage.messageId,
                conversationId: latestMessage.conversationId,
                senderType: latestMessage.type === 'incoming' ? 'contact' : 'user', // Упрощенно
                senderId: latestMessage.type === 'incoming' ? latestMessage.conversationId : user?.id, // Упрощенно
                text: latestMessage.text,
                isRead: false, // Или true, если это наше исходящее
                direction: latestMessage.type === 'incoming' ? 'inbound' : 'outbound',
                sentAt: new Date().toISOString(),
            };
            setMessages((prevMessages) => [...prevMessages, newMessage]);
            scrollToBottom();
        }
    }, [latestMessage, conversationId, user]);

    const fetchConversationAndMessages = async (id: string) => {
        try {
            const convResponse = await axiosClient.get<Conversation>(`/conversations/${id}`);
            setConversation(convResponse.data);
            const msgResponse = await axiosClient.get<Message[]>(`/conversations/${id}/messages`);
            setMessages(msgResponse.data);
            scrollToBottom();
        } catch (error) {
            console.error('Failed to fetch chat data:', error);
            // navigate('/dashboard'); // Вернуться на дашборд при ошибке
        }
    };

    const handleSendMessage = async () => {
        if (!newMessageText.trim() || !conversationId) return;

        try {
            const tempMessage: Message = { // Временное сообщение для UI
                id: `temp-${Date.now()}`,
                conversationId: conversationId,
                senderType: 'user',
                senderId: user?.id,
                text: newMessageText,
                isRead: true,
                direction: 'outbound',
                sentAt: new Date().toISOString(),
            };
            setMessages((prevMessages) => [...prevMessages, tempMessage]);
            setNewMessageText('');
            scrollToBottom();

            await axiosClient.post(`/conversations/${conversationId}/messages`, { text: newMessageText });
            // В реальном приложении, после успешной отправки, можно обновить tempMessage на реальное
            // или дождаться WS-уведомления о доставке.
        } catch (error) {
            console.error('Failed to send message:', error);
            // Можно показать ошибку пользователю и удалить tempMessage
        }
    };

    const scrollToBottom = () => {
        messagesEndRef.current?.scrollIntoView({ behavior: 'smooth' });
    };

    if (!conversation) {
        return <Typography>Loading chat...</Typography>;
    }

    return (
        <Box sx={{ display: 'flex', flexDirection: 'column', height: '100vh' }}>
            <AppBar position="static">
                <Toolbar>
                    <IconButton edge="start" color="inherit" aria-label="back" onClick={() => navigate('/dashboard')}>
                        <ArrowBackIcon />
                    </IconButton>
                    <Typography variant="h6" component="div" sx={{ flexGrow: 1 }}>
                        Chat with {conversation.contact.mainName} ({conversation.type})
                    </Typography>
                </Toolbar>
            </AppBar>
            <Box sx={{ flexGrow: 1, overflowY: 'auto', p: 2 }}>
                {messages.map((msg) => (
                    <Box
                        key={msg.id}
                        sx={{
                            display: 'flex',
                            justifyContent: msg.direction === 'inbound' ? 'flex-start' : 'flex-end',
                            mb: 1,
                        }}
                    >
                        <Paper
                            sx={{
                                p: 1,
                                bgcolor: msg.direction === 'inbound' ? 'grey.200' : 'primary.light',
                                color: msg.direction === 'inbound' ? 'text.primary' : 'white',
                                maxWidth: '70%',
                            }}
                        >
                            <Typography variant="body2">{msg.text}</Typography>
                            <Typography variant="caption" sx={{ display: 'block', textAlign: 'right', mt: 0.5 }}>
                                {new Date(msg.sentAt).toLocaleTimeString()}
                            </Typography>
                        </Paper>
                    </Box>
                ))}
                <div ref={messagesEndRef} />
            </Box>
            <Box sx={{ p: 2, borderTop: 1, borderColor: 'divider', display: 'flex' }}>
                <TextField
                    fullWidth
                    variant="outlined"
                    placeholder="Type a message..."
                    value={newMessageText}
                    onChange={(e) => setNewMessageText(e.target.value)}
                    onKeyPress={(e) => {
                        if (e.key === 'Enter') {
                            handleSendMessage();
                        }
                    }}
                    sx={{ mr: 2 }}
                />
                <Button variant="contained" onClick={handleSendMessage}>
                    Send
                </Button>
            </Box>
        </Box>
    );
};

export default ChatPage;