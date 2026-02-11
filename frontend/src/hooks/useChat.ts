import { useState, useEffect, useRef, useCallback } from 'react';
import { Camera, CameraResultType } from '@capacitor/camera';
import axiosClient from '../api/axiosClient';
import { useWebSocket } from './useWebSocket';
import { Conversation, Message } from '../types';

export const useChat = (id: string | undefined, currentUser: any) => {
    const { latestMessage, socket } = useWebSocket(id, currentUser?.id);
    const [conversation, setConversation] = useState<Conversation | null>(null);
    const [messages, setMessages] = useState<Message[]>([]);
    const [newMessageText, setNewMessageText] = useState('');
    const [isContactOnline, setIsContactOnline] = useState(false);
    const [isTyping, setIsTyping] = useState(false);
    const [chatPartner, setChatPartner] = useState<any>(null);
    const [imageErrors, setImageErrors] = useState<Record<string, boolean>>({});
    const [selectedImage, setSelectedImage] = useState<string | null>(null);

    const typingTimeoutRef = useRef<NodeJS.Timeout | null>(null);
    const messagesEndRef = useRef<HTMLDivElement>(null);

    const scrollToBottom = useCallback(() => {
        messagesEndRef.current?.scrollIntoView({ behavior: 'smooth' });
    }, []);

    const fetchChat = useCallback(async () => {
        if (!id) return;
        try {
            const [convRes, msgRes] = await Promise.all([
                axiosClient.get<Conversation>(`/conversations/${id}`),
                axiosClient.get<Message[]>(`/conversations/${id}/messages`)
            ]);
            setConversation(convRes.data);
            setMessages(msgRes.data);
            setTimeout(scrollToBottom, 50);
        } catch (err) { console.error(err); }
    }, [id, scrollToBottom]);

    // Синхронизация при загрузке
    useEffect(() => {
        if (conversation?.contact) {
            setChatPartner(conversation.contact);
            setIsContactOnline(!!conversation.contact.isOnline);
        }
    }, [conversation]);

    // Загрузка чата
    useEffect(() => {
        if (id) {
            fetchChat();
            axiosClient.post(`/conversations/${id}/read`).catch(() => {});
        }
    }, [id, fetchChat]);

    // Обработка входящих событий (сокетов)
    useEffect(() => {
        if (!latestMessage) return;

        if (latestMessage.event) {
            // Статус
            if (latestMessage.event === 'userStatusChanged') {
                const socketUserId = String(latestMessage.userId).toLowerCase();
                const currentContactId = String(conversation?.contact?.id || chatPartner?.id || '').toLowerCase();
                if (socketUserId === currentContactId && currentContactId !== '' && currentContactId !== 'null') {
                    const isOnline = latestMessage.status === 'online';
                    setIsContactOnline(isOnline);
                    setChatPartner((prev: any) => prev ? ({ ...prev, isOnline, lastSeen: latestMessage.lastSeen }) : prev);
                }
            }
            // Typing
            if (latestMessage.event === 'typing' && String(latestMessage.conversationId) === String(id)) {
                if (String(latestMessage.userId) !== String(currentUser?.id)) {
                    setIsTyping(true);
                    if (typingTimeoutRef.current) clearTimeout(typingTimeoutRef.current);
                    typingTimeoutRef.current = setTimeout(() => setIsTyping(false), 3000);
                }
            }
            return;
        }

        // Сообщения
        setIsTyping(false);
        setMessages(prev => {
            if (prev.some(m => m.id === latestMessage.id)) return prev;
            const isDuplicate = prev.some(m => m.text === latestMessage.text && m.direction === 'outbound' && (Date.now() - new Date(m.sentAt).getTime() < 3000));
            if (isDuplicate && latestMessage.direction === 'inbound') return prev;
            return [...prev, latestMessage as Message];
        });
        setTimeout(scrollToBottom, 50);
    }, [latestMessage, conversation, chatPartner?.id, id, currentUser?.id, scrollToBottom]);


    const [replyTo, setReplyTo] = useState<Message | null>(null); // Состояние для цитаты

    const handleSend = async () => {
        if (!newMessageText.trim() || !id) return;
        const text = newMessageText;
        const currentReply = replyTo; // Фиксируем текущую цитату

        setNewMessageText('');
        setReplyTo(null); // Сбрасываем превью над инпутом

        const tempId = `temp-${Date.now()}`;
        const newMessage = {
            id: tempId,
            text,
            direction: 'outbound',
            status: 'sent',
            sentAt: new Date().toISOString(),
            conversationId: id,
            isRead: true,
            senderType: 'user',
            payload: {
                senderId: currentUser?.id,
                replyTo: currentReply ? { id: currentReply.id, text: currentReply.text } : null // Добавляем в payload
            }
        } as Message;

        setMessages(prev => [...prev, newMessage]);
        setTimeout(scrollToBottom, 50);

        try {
            // Отправляем на бэкенд вместе с данными о цитате
            const res = await axiosClient.post(`/conversations/${id}/messages`, {
                text,
                replyToId: currentReply?.id // Бэкенд должен это сохранить
            });
            setMessages(prev => prev.map(m => m.id === tempId ? { ...m, id: res.data.id } : m));
        } catch (err) {
            setMessages(prev => prev.filter(m => m.id !== tempId));
        }
    };


    const takePhoto = async () => {
        try {
            const image = await Camera.getPhoto({ quality: 90, resultType: CameraResultType.Base64, webUseInput: true });
            if (image.base64String) {
                const base64Data = `data:image/jpeg;base64,${image.base64String}`;
                const tempId = `temp-img-${Date.now()}`;
                setMessages(prev => [...prev, { id: tempId, text: "📷 Фото", direction: 'outbound', status: 'sent', sentAt: new Date().toISOString(), conversationId: id!, preview: base64Data, isUploading: true, payload: { senderId: currentUser?.id } } as any]);
                setTimeout(scrollToBottom, 50);
                const res = await axiosClient.post(`/conversations/${id}/messages`, { text: "📷 Фото", attachment: image.base64String });
                setMessages(prev => prev.map(m => m.id === tempId ? { ...m, id: res.data.id, isUploading: false, payload: res.data.payload } : m));
            }
        } catch (e) { console.warn(e); }
    };

    return {
        conversation, messages, newMessageText, setNewMessageText, isContactOnline,
        isTyping, selectedImage, setSelectedImage, imageErrors, setImageErrors,
        messagesEndRef, chatPartner, takePhoto, handleSend, replyTo, setReplyTo, socket
    };
};
