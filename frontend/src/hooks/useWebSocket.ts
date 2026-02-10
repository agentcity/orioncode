import { useEffect, useState, useRef } from 'react';
import { io, Socket } from 'socket.io-client';
import { WS_URL } from '../api/config';

export const useWebSocket = (conversationId?: string, userId?: string) => {
    const [latestMessage, setLatestMessage] = useState<any>(null);
    const socketRef = useRef<Socket | null>(null);

    // Используем useRef для безопасного хранения аудио
    const audioRef = useRef<HTMLAudioElement | null>(null);

    useEffect(() => {
        // Инициализируем аудио только внутри useEffect (безопасно для мобилок)
        if (!audioRef.current) {
            audioRef.current = new Audio('/assets/sounds/notification.mp3');
        }

        if (!socketRef.current) {
            socketRef.current = io(WS_URL, {
                transports: ["websocket"],
                reconnection: true,
            });
        }

        const socket = socketRef.current;

        const onConnect = () => {
            if (userId) socket.emit('authenticate', userId);
            if (conversationId) socket.emit('join_conversation', conversationId);
        };

        const onNewMessage = (payload: any) => {
            // Проверяем: сообщение не наше
            const isNotMe = String(payload.senderId) !== String(userId);
            const isRealMessage = payload.text || payload.content || payload.filePath;


            if (isNotMe && isRealMessage) {
                // Играем звук через ref и ловим ошибки (для iOS критично)
                audioRef.current?.play().catch(() => {
                    console.log('Автовоспроизведение звука заблокировано (нужен клик)');
                });
                // Вибрация (работает в Android APK и PWA на Android)
                if ('vibrate' in navigator) {
                    navigator.vibrate(200); // Вибрируем 200мс
                }
            }

            setLatestMessage(payload);
        };

        const onStatusChange = (data: any) => {
            setLatestMessage({ event: 'userStatusChanged', ...data });
        };

        socket.on('connect', onConnect);
        socket.on('newMessage', onNewMessage);
        socket.on('userStatusChanged', onStatusChange);

        // --- ДОБАВЛЯЕМ HEARTBEAT ---
        const heartbeatInterval = setInterval(() => {
            if (socket.connected && userId) {
                socket.emit('heartbeat', { userId }); // Сообщаем серверу, что мы живы
            }
        }, 30000); // Раз в 30 секунд

        // Если сокет уже подключен (при смене id чата)
        if (socket.connected && conversationId) {
            socket.emit('join_conversation', conversationId);
        }

        return () => {
            socket.off('connect', onConnect);
            socket.off('newMessage', onNewMessage);
            socket.off('userStatusChanged', onStatusChange);
            clearInterval(heartbeatInterval);
        };
    }, [conversationId, userId]); // Реагирует на смену чата

    return { latestMessage, socket: socketRef.current };
};

