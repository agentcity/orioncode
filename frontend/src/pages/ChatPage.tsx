import React, { useEffect, useState, useRef } from 'react';
import { useParams } from 'react-router-dom';
import { Box, Typography, Paper, TextField, Button, Avatar, Badge, styled } from '@mui/material';
import DoneIcon from '@mui/icons-material/Done';
import DoneAllIcon from '@mui/icons-material/DoneAll';
import WhatsAppIcon from '@mui/icons-material/WhatsApp';
import TelegramIcon from '@mui/icons-material/Telegram';
import HubIcon from '@mui/icons-material/Hub';
import axiosClient from '../api/axiosClient';
import { Conversation, Message } from '../types';
import { useWebSocket } from '../hooks/useWebSocket';

const StyledBadge = styled(Badge, {
    shouldForwardProp: (prop) => prop !== 'isOnline',
})<{ isOnline?: boolean }>(({ theme, isOnline }) => ({
    '& .MuiBadge-badge': {
        backgroundColor: isOnline ? '#44b700' : '#bdbdbd',
        color: isOnline ? '#44b700' : '#bdbdbd',
        boxShadow: `0 0 0 2px ${theme.palette.background.paper}`,
        '&::after': isOnline ? {
            position: 'absolute',
            top: 0, left: 0, width: '100%', height: '100%',
            borderRadius: '50%', animation: 'ripple 1.2s infinite ease-in-out',
            border: '1px solid currentColor', content: '""',
        } : {},
    },
    '@keyframes ripple': {
        '0%': { transform: 'scale(.8)', opacity: 1 },
        '100%': { transform: 'scale(2.4)', opacity: 0 },
    },
}));

const ChatPage: React.FC = () => {
    const { id } = useParams<{ id: string }>();
    const { latestMessage } = useWebSocket(id);
    const [conversation, setConversation] = useState<Conversation | null>(null);
    const [messages, setMessages] = useState<Message[]>([]);
    const [newMessageText, setNewMessageText] = useState('');
    const [isContactOnline, setIsContactOnline] = useState(false);
    const messagesEndRef = useRef<HTMLDivElement>(null);
    // Исправлена ссылка на звук (тестовый звук уведомления)
    const audioRef = useRef(new Audio('https://assets.mixkit.co'));

    useEffect(() => {
        if (id) {
            fetchChat();
            axiosClient.post(`/conversations/${id}/read`).catch(() => {});
        }
    }, [id]);

    useEffect(() => {
        if (!latestMessage) return;

        if (latestMessage.event === 'userStatusChanged' && latestMessage.userId === conversation?.contact.id) {
            setIsContactOnline(latestMessage.status);
        }

        if (latestMessage.conversationId === id && !latestMessage.event) {
            if (latestMessage.direction === 'incoming') {
                audioRef.current.play().catch(() => {});
            }
            setMessages(prev => {
                // Если сообщение уже добавлено оптимистично (по ID), не дублируем
                if (prev.some(m => m.id === latestMessage.id)) return prev;
                return [...prev, { ...latestMessage } as Message];
            });
            setTimeout(scrollToBottom, 50);
        }

        if (latestMessage.event === 'statusUpdate') {
            setMessages(prev => prev.map(m => m.id === latestMessage.id ? { ...m, status: latestMessage.status } : m));
        }
    }, [latestMessage, id, conversation]);

    const fetchChat = async () => {
        try {
            const [convRes, msgRes] = await Promise.all([
                axiosClient.get<Conversation>(`/conversations/${id}`),
                axiosClient.get<Message[]>(`/conversations/${id}/messages`)
            ]);
            setConversation(convRes.data);
            setMessages(msgRes.data);
            setTimeout(scrollToBottom, 50);
        } catch (err) { console.error(err); }
    };

    const handleSend = async () => {
        if (!newMessageText.trim()) return;
        const text = newMessageText;
        setNewMessageText('');

        // 1. Оптимистичное обновление: сразу добавляем сообщение в список
        const tempId = `temp-${Date.now()}`;
        const tempMsg: Message = {
            id: tempId,
            text: text,
            direction: 'outgoing',
            status: 'sent',
            sentAt: new Date().toISOString(),
            conversationId: id!
        };
        setMessages(prev => [...prev, tempMsg]);
        setTimeout(scrollToBottom, 50);

        try {
            // 2. Отправка на сервер
            const response = await axiosClient.post(`/conversations/${id}/messages`, { text });
            // 3. Обновляем ID временного сообщения на реальный из БД
            setMessages(prev => prev.map(m => m.id === tempId ? { ...m, id: response.data.id } : m));
        } catch (err) {
            console.error(err);
            // Если ошибка — удаляем временное сообщение
            setMessages(prev => prev.filter(m => m.id !== tempId));
        }
    };

    const scrollToBottom = () => messagesEndRef.current?.scrollIntoView({ behavior: 'smooth' });

    const getChannelIcon = (type: string) => {
        switch (type?.toLowerCase()) {
            case 'whatsapp': return <WhatsAppIcon sx={{ fontSize: 18, color: '#25D366', ml: 1 }} />;
            case 'telegram': return <TelegramIcon sx={{ fontSize: 18, color: '#24A1DE', ml: 1 }} />;
            case 'max': return <HubIcon sx={{ fontSize: 18, color: '#0052cc', ml: 1 }} />;
            default: return null;
        }
    };

    if (!conversation) return <Box sx={{ p: 3 }}><Typography>Загрузка...</Typography></Box>;

    return (
        <Box sx={{ display: 'flex', flexDirection: 'column', height: '100%', position: 'relative', minWidth: 0 }}>
            {/* ШАПКА */}
            <Box sx={{ p: 2, bgcolor: 'white', borderBottom: 1, borderColor: 'divider', display: 'flex', alignItems: 'center', zIndex: 10 }}>
                <StyledBadge isOnline={isContactOnline} overlap="circular" anchorOrigin={{ vertical: 'bottom', horizontal: 'right' }} variant="dot">
                    <Avatar sx={{ bgcolor: 'primary.main' }}>{conversation.contact.mainName[0]}</Avatar>
                </StyledBadge>
                <Box sx={{ ml: 2, minWidth: 0 }}>
                    <Box sx={{ display: 'flex', alignItems: 'center' }}>
                        <Typography noWrap variant="subtitle1" sx={{ fontWeight: 'bold' }}>{conversation.contact.mainName}</Typography>
                        {getChannelIcon(conversation.type)}
                    </Box>
                    <Typography variant="caption" color={isContactOnline ? "#44b700" : "text.secondary"} sx={{fontWeight: 'bold'}}>
                        {isContactOnline ? 'в сети' : 'был(а) недавно'} • {conversation.type.toUpperCase()}
                    </Typography>
                </Box>
            </Box>

            {/* ТЕЛО ЧАТА */}
            <Box sx={{
                flexGrow: 1, overflowY: 'auto', p: 3, display: 'flex', flexDirection: 'column', position: 'relative', bgcolor: '#5c7bb0',
                backgroundImage: `url("data:image/svg+xml,%3Csvg width='80' height='80' viewBox='0 0 80 80' xmlns='http://www.w3.org fill='%23ffffff' fill-opacity='0.15' fill-rule='evenodd'%3E%3Ccircle cx='40' cy='40' r='2'/%3E%3Ccircle cx='10' cy='10' r='2'/%3E%3C/g%3E%3C/svg%3E")`,
            }}>
                {messages.map((msg) => (
                    <Box key={msg.id} sx={{ display: 'flex', flexDirection: 'column', alignItems: msg.direction === 'incoming' ? 'flex-start' : 'flex-end', mb: 2, zIndex: 1 }}>
                        <Paper elevation={2} sx={{
                            p: 1.5,
                            bgcolor: msg.direction === 'incoming' ? '#ffffff' : '#d1e4ff',
                            color: 'black',
                            maxWidth: '85%',
                            borderRadius: msg.direction === 'incoming' ? '18px 18px 18px 4px' : '18px 18px 4px 18px',
                            wordBreak: 'break-word'
                        }}>
                            <Typography variant="body1">{msg.text}</Typography>
                            <Box sx={{ display: 'flex', justifyContent: 'flex-end', alignItems: 'center', mt: 0.5 }}>
                                <Typography variant="caption" sx={{ opacity: 0.6, mr: 0.5, fontSize: '0.75rem' }}>
                                    {new Date(msg.sentAt).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}
                                </Typography>
                                {msg.direction === 'outgoing' && (msg.status === 'read' ? <DoneAllIcon sx={{ fontSize: 18, color: '#2196f3' }} /> : <DoneIcon sx={{ fontSize: 18, opacity: 0.4 }} />)}
                            </Box>
                        </Paper>
                    </Box>
                ))}
                <div ref={messagesEndRef} />
            </Box>

            <Box sx={{ p: 2, bgcolor: 'white', borderTop: 1, borderColor: 'divider', display: 'flex', alignItems: 'center', zIndex: 10 }}>
                <TextField
                    fullWidth
                    size="small"
                    placeholder="Напишите сообщение..."
                    value={newMessageText}
                    onChange={(e) => setNewMessageText(e.target.value)}
                    onKeyDown={(e) => e.key === 'Enter' && handleSend()}
                    sx={{ '& .MuiOutlinedInput-root': { borderRadius: '25px', bgcolor: '#f1f3f4' } }}
                />
                <Button variant="contained" onClick={handleSend} sx={{ ml: 2, borderRadius: '25px', textTransform: 'none', px: 4 }}>
                    Отправить
                </Button>
            </Box>
        </Box>
    );
};

export default ChatPage;