import React, { useEffect, useState, useRef } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import {
    Box, Typography, Paper, TextField, Button, Avatar, Badge,
    styled, IconButton, CircularProgress, Dialog
} from '@mui/material';
import { Camera, CameraResultType } from '@capacitor/camera';
import ArrowBackIcon from '@mui/icons-material/ArrowBack';
import PhotoCameraIcon from '@mui/icons-material/PhotoCamera';
import DoneIcon from '@mui/icons-material/Done';
import DoneAllIcon from '@mui/icons-material/DoneAll';
import WhatsAppIcon from '@mui/icons-material/WhatsApp';
import TelegramIcon from '@mui/icons-material/Telegram';
import HubIcon from '@mui/icons-material/Hub';
import axiosClient from '../api/axiosClient';
import { Conversation, Message } from '../types';
import { useWebSocket } from '../hooks/useWebSocket';
import { useAuth } from '../context/AuthContext';
import ImageNotSupportedIcon from '@mui/icons-material/ImageNotSupported';

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

const ChatPage: React.FC<{ isMobile?: boolean }> = ({ isMobile }) => {
    const { id } = useParams<{ id: string }>();
    const navigate = useNavigate();
    const { user: currentUser } = useAuth();
    const { latestMessage, socket } = useWebSocket(id, currentUser?.id);
    const [conversation, setConversation] = useState<Conversation | null>(null);
    const [messages, setMessages] = useState<Message[]>([]);
    const [newMessageText, setNewMessageText] = useState('');
    const [isContactOnline, setIsContactOnline] = useState(false);

    const [isTyping, setIsTyping] = useState(false);
    const typingTimeoutRef = useRef<NodeJS.Timeout | null>(null);

    // Состояние для полноэкранного просмотра фото
    const [selectedImage, setSelectedImage] = useState<string | null>(null);

    // Состояние для ошибок загрузки фото
    const [imageErrors, setImageErrors] = useState<Record<string, boolean>>({});

    const messagesEndRef = useRef<HTMLDivElement>(null);

    // состояние после загрузки conversation
    const [chatPartner, setChatPartner] = useState<any>(null);

    useEffect(() => {
        if (conversation && conversation.contact) {
            setChatPartner(conversation.contact);
            // Сразу синхронизируем статус из загруженных данных
            setIsContactOnline(!!conversation.contact.isOnline);
        }
    }, [conversation]);


    useEffect(() => {
        if (id) {
            fetchChat();
            axiosClient.post(`/conversations/${id}/read`).catch(() => {});
        }
    }, [id]);


    useEffect(() => {
        if (!latestMessage) return;

        // Если это СОБЫТИЕ (typing или статус)
        if (latestMessage.event) {
            // 1. Статус "В сети"
            if (latestMessage.event == 'userStatusChanged') {

                const socketUserId = String(latestMessage.userId).toLowerCase();
                const isOnline = latestMessage.status === 'online';

                // 1. Пытаемся найти ID контакта (учитывая разные уровни вложенности)
                const currentContactId = String(conversation?.contact?.id || chatPartner?.id || '').toLowerCase();


                // 2. Если ID совпали ИЛИ у нас еще нет данных (на всякий случай запоминаем)
                if (socketUserId === currentContactId && currentContactId !== 'null' && currentContactId !== '') {
                    setIsContactOnline(isOnline);

                    setChatPartner((prev: any) => {
                        if (!prev) return prev;
                        return {
                            ...prev,
                            isOnline: isOnline,
                            lastSeen: latestMessage.lastSeen || new Date().toISOString()
                        };
                    });
                }
            }

            // 2. Индикатор "Печатает..."
            if (latestMessage.event === 'typing' && String(latestMessage.conversationId) === String(id)) {
                // Показываем только если печатает собеседник (не мы)
                if (String(latestMessage.userId) !== String(currentUser?.id)) {
                    setIsTyping(true);
                    if (typingTimeoutRef.current) clearTimeout(typingTimeoutRef.current);
                    // Скрываем через 3 секунды, если новых событий нет
                    typingTimeoutRef.current = setTimeout(() => setIsTyping(false), 3000);
                }
                return;
            }
            return;
        }

        // 3. Новое сообщение (твой текущий код)
        if (!latestMessage.event) {
            setIsTyping(false); // Сразу скрываем "печатает", если пришло сообщение

            setMessages(prev => {
                // 1. Проверяем по ID (уже есть в базе)
                if (prev.some(m => m.id === latestMessage.id)) return prev;

                // 2. Проверяем "оптимистичные" сообщения (те, что мы отправили сами)
                // Если в списке есть сообщение с таким же текстом, отправленное менее 2 секунд назад
                // и оно помечено как 'outbound', заменяем его или игнорируем дубль
                const isDuplicate = prev.some(m =>
                    m.text === latestMessage.text &&
                    m.direction === 'outbound' &&
                    (new Date().getTime() - new Date(m.sentAt).getTime() < 2000)
                );

                if (isDuplicate && latestMessage.direction === 'inbound') {
                    // Если это пришло наше же сообщение из сокета, просто игнорируем его,
                    // так как мы его уже отрисовали через handleSend/takePhoto
                    return prev;
                }

                return [...prev, latestMessage as Message];
            });

            setTimeout(scrollToBottom, 50);
        }
    }, [latestMessage, conversation?.contact?.id, id, currentUser?.id, chatPartner?.id]);

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

    const takePhoto = async () => {
        try {
            const image = await Camera.getPhoto({
                quality: 90,
                resultType: CameraResultType.Base64,
                webUseInput: true
            });

            if (image.base64String) {
                const base64Data = `data:image/jpeg;base64,${image.base64String}`;
                const tempId = `temp-img-${Date.now()}`;

                setMessages(prev => [...prev, {
                    id: tempId, text: "📷 Фото", direction: 'outbound', status: 'sent',
                    sentAt: new Date().toISOString(), conversationId: id!,
                    preview: base64Data, isUploading: true, payload: { senderId: currentUser?.id }
                } as any]);
                setTimeout(scrollToBottom, 50);

                const res = await axiosClient.post(`/conversations/${id}/messages`, {
                    text: "📷 Фото",
                    attachment: image.base64String
                });

                setMessages(prev => prev.map(m => m.id === tempId ? {
                    ...m, id: res.data.id, isUploading: false, payload: res.data.payload
                } : m));
            }
        } catch (e) { console.warn(e); }
    };

    const handleSend = async () => {
        if (!newMessageText.trim() || !id) return;
        const text = newMessageText;
        setNewMessageText('');
        const tempId = `temp-${Date.now()}`;
        setMessages(prev => [...prev, {
            id: tempId, text, direction: 'outbound', status: 'sent',
            sentAt: new Date().toISOString(), conversationId: id,
            senderType: 'user', isRead: true, payload: { senderId: currentUser?.id }
        } as Message]);
        setTimeout(scrollToBottom, 50);

        try {
            const res = await axiosClient.post(`/conversations/${id}/messages`, { text });
            setMessages(prev => prev.map(m => m.id === tempId ? { ...m, id: res.data.id } : m));
        } catch (err) {
            setMessages(prev => prev.filter(m => m.id !== tempId));
        }
    };

    const scrollToBottom = () => messagesEndRef.current?.scrollIntoView({ behavior: 'smooth' });

    const getChannelIcon = (type: string) => {
        switch (type?.toLowerCase()) {
            case 'whatsapp': return <WhatsAppIcon sx={{ fontSize: 18, color: '#25D366', ml: 1 }} />;
            case 'telegram': return <TelegramIcon sx={{ fontSize: 18, color: '#24A1DE', ml: 1 }} />;
            case 'orion': return <HubIcon sx={{ fontSize: 18, color: '#666', ml: 1 }} />;
            default: return null;
        }
    };

    if (!conversation) return <Box sx={{ p: 3 }}><CircularProgress /></Box>;


    return (
        <Box sx={{ display: 'flex', flexDirection: 'column', height: '100%', position: 'relative', minWidth: 0 }}>
            {/* Header */}
            <Box sx={{ p: 2, bgcolor: 'white', borderBottom: 1, borderColor: 'divider', display: 'flex', alignItems: 'center', zIndex: 10 }}>
                {isMobile && <IconButton onClick={() => navigate('/dashboard')} sx={{ mr: 1 }}><ArrowBackIcon /></IconButton>}
                <StyledBadge isOnline={isContactOnline} overlap="circular" anchorOrigin={{ vertical: 'bottom', horizontal: 'right' }} variant="dot">
                    <Avatar sx={{ bgcolor: 'primary.main' }}>{conversation.contact?.mainName?.[0] || 'U'}</Avatar>
                </StyledBadge>
                <Box sx={{ ml: 2, minWidth: 0 }}>
                    <Box sx={{ display: 'flex', alignItems: 'center' }}>
                        <Typography noWrap variant="subtitle1" sx={{ fontWeight: 'bold' }}>
                            {/* Берем имя контакта, которое мы настроили в контроллере */}
                            {conversation.contact?.mainName || 'Беседа'}
                        </Typography>
                        {getChannelIcon(conversation.type)}
                    </Box>
                    <Typography variant="caption" color={chatPartner?.isOnline || isContactOnline ? "#44b700" : "text.secondary"}>
                        {chatPartner?.isOnline|| isContactOnline ? 'в сети' : `был(а) ${formatLastSeen(chatPartner?.lastSeen)}`} • {conversation.type ? conversation.type.toUpperCase() : 'CHAT'}
                    </Typography>
                </Box>
            </Box>

            {/* Messages Body */}
            <Box sx={{
                flexGrow: 1, overflowY: 'auto', p: 3, display: 'flex', flexDirection: 'column', position: 'relative', bgcolor: '#5c7bb0',
                backgroundImage: `url("data:image/svg+xml,%3Csvg width='80' height='80' viewBox='0 0 80 80' xmlns='http://www.w3.org fill='%23ffffff' fill-opacity='0.15'%3E%3Ccircle cx='40' cy='40' r='2'/%3E%3C/g%3E%3C/svg%3E")`,
            }}>
                {messages.map((msg: any) => {
                    const serverBase = (process.env.REACT_APP_API_URL || 'http://localhost:8080/api').replace(/\/api$/, '');
                    const imageSrc = msg.preview ||
                        (msg.payload?.filePath ? `${serverBase}${msg.payload.filePath}` : null) ||
                        (msg.attachments?.[0]?.url ? `${serverBase}${msg.attachments[0].url}` : null);
                    const isMine = conversation.type === 'orion'
                        ? (String(msg.payload?.senderId).toLowerCase() === String(currentUser?.id).toLowerCase())
                        : (msg.direction === 'outbound' || msg.direction === 'outgoing');

                    // Проверяем, помечена ли эта картинка как "битая"
                    const hasError = imageErrors[msg.id] || false;

                    return (
                        <Box key={msg.id} sx={{ display: 'flex', flexDirection: 'column', alignItems: isMine ? 'flex-end' : 'flex-start', mb: 2 }}>
                            <Paper elevation={2} sx={{
                                p: imageSrc ? 1 : 1.5,
                                bgcolor: isMine ? '#d1e4ff' : '#ffffff',
                                maxWidth: '85%', overflow: 'hidden',
                                borderRadius: isMine ?  '18px 18px 4px 18px' : '18px 18px 18px 4px',
                                wordBreak: 'break-word'
                            }}>
                                {imageSrc && (
                                    <Box
                                        sx={{ position: 'relative', lineHeight: 0, cursor: 'pointer' }}
                                        onClick={() => !msg.isUploading && !hasError  && setSelectedImage(imageSrc)}
                                    >
                                        {hasError ? (
                                            // КРАСИВАЯ ЗАГЛУШКА
                                            <Box sx={{
                                                width: '200px', height: '150px',
                                                bgcolor: 'rgba(0,0,0,0.05)', borderRadius: '12px',
                                                display: 'flex', flexDirection: 'column', alignItems: 'center', justifyContent: 'center',
                                                border: '1px dashed rgba(0,0,0,0.2)'
                                            }}>
                                                <ImageNotSupportedIcon sx={{ fontSize: 40, opacity: 0.3, mb: 1 }} />
                                                <Typography variant="caption" sx={{ opacity: 0.5 }}>Фото недоступно</Typography>
                                            </Box>
                                        ) : (
                                        <img
                                            src={imageSrc}
                                            alt="attachment"
                                            onError={() => {
                                                // Мы говорим: "Для сообщения с этим ID картинка битая"
                                                setImageErrors(prev => ({
                                                    ...prev,
                                                    [msg.id]: true
                                                }));
                                            }}
                                            style={{
                                                width: '100%', maxWidth: '300px', maxHeight: '400px',
                                                objectFit: 'cover', borderRadius: '12px',
                                                filter: msg.isUploading ? 'blur(4px) grayscale(50%)' : 'none'
                                            }}
                                        />
                                        )}
                                        {msg.isUploading && (
                                            <Box sx={{ position: 'absolute', top: '50%', left: '50%', transform: 'translate(-50%, -50%)' }}>
                                                <CircularProgress size={24} color="inherit" />
                                            </Box>
                                        )}
                                    </Box>
                                )}
                                {msg.text && (msg.text !== '📷 Фото' || !imageSrc) && (
                                    <Typography variant="body1" sx={{ mt: imageSrc ? 1 : 0, px: 0.5 }}>{msg.text}</Typography>
                                )}
                                <Box sx={{ display: 'flex', justifyContent: 'flex-end', alignItems: 'center', mt: 0.5, px: 0.5 }}>
                                    <Typography variant="caption" sx={{ opacity: 0.6, mr: 0.5, fontSize: '0.75rem' }}>
                                        {new Date(msg.sentAt).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}
                                    </Typography>
                                    {isMine && (
                                        msg.status === 'read' || msg.isRead
                                            ? <DoneAllIcon sx={{ fontSize: 18, color: '#2196f3' }} />
                                            : <DoneIcon sx={{ fontSize: 18, opacity: 0.4 }} />
                                    )}
                                </Box>
                            </Paper>
                        </Box>
                    );
                })}
                <div ref={messagesEndRef} />
            </Box>

            {/* Индикатор печати */}
            {isTyping && (
                <Box sx={{ px: 2, py: 0.5, bgcolor: 'rgba(255,255,255,0.8)', position: 'absolute', bottom: 80, left: 20, borderRadius: '10px', zIndex: 5 }}>
                    <Typography variant="caption" sx={{ fontStyle: 'italic', color: 'primary.main' }}>
                        {conversation.contact?.mainName} печатает...
                    </Typography>
                </Box>
            )}

            {/* Input Area */}
            <Box sx={{ p: 2, bgcolor: 'white', borderTop: 1, borderColor: 'divider', display: 'flex', alignItems: 'center' }}>
                <IconButton onClick={takePhoto} color="primary" sx={{ mr: 1 }}><PhotoCameraIcon /></IconButton>
                <TextField fullWidth size="small" placeholder="Напишите сообщение..." value={newMessageText}
                           onChange={(e) => {
                               setNewMessageText(e.target.value)
                               // Отправляем событие "печатает", если сокет готов
                               if (socket && id && e.target.value.length > 0) {
                                   // Мы просто шлем событие, Node.js сам разбросает его по комнате
                                   socket.emit('typing', {
                                       conversationId: id,
                                       userId: currentUser?.id
                                   });
                               }
                           }}
                           onKeyDown={(e) => e.key === 'Enter' && handleSend()}
                           sx={{ '& .MuiOutlinedInput-root': { borderRadius: '25px', bgcolor: '#f1f3f4' } }}
                />
                <Button variant="contained" onClick={handleSend} sx={{ ml: 2, borderRadius: '25px', px: 4 }}>
                    {isMobile ? '🚀' : 'ОТПРАВИТЬ'}
                </Button>
            </Box>

            {/* FULLSCREEN IMAGE DIALOG - Закрытие по клику на фото */}
            <Dialog
                open={!!selectedImage}
                onClose={() => setSelectedImage(null)}
                maxWidth="xl"
                PaperProps={{ sx: { bgcolor: 'transparent', boxShadow: 'none', overflow: 'hidden' } }}
            >
                <Box
                    onClick={() => setSelectedImage(null)} // Клик по фото закрывает его
                    sx={{ width: '100%', height: '100%', display: 'flex', alignItems: 'center', justifyContent: 'center', cursor: 'zoom-out' }}
                >
                    <img
                        src={selectedImage || ''}
                        alt="full size"
                        style={{ maxWidth: '100vw', maxHeight: '100vh', objectFit: 'contain' }}
                    />
                </Box>
            </Dialog>
        </Box>
    );
};

export default ChatPage;


