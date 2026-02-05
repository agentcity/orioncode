import { useEffect, useState, useRef } from 'react';
import { io, Socket } from 'socket.io-client';
import { WS_URL } from '../api/config'

export const useWebSocket = (conversationId?: string, userId?: string) => {
    const [latestMessage, setLatestMessage] = useState<any>(null);
    const socketRef = useRef<Socket | null>(null);

    useEffect(() => {
        // Создаем сокет только если его еще нет
        if (!socketRef.current) {
            socketRef.current = io(WS_URL, {
                transports: ["websocket"],
                reconnection: true,
                reconnectionAttempts: 5
            });
        }

        const socket = socketRef.current;

        socket.on('connect', () => {
            console.log('WS: Connected');
            if (userId) socket.emit('authenticate', userId);
            if (conversationId) socket.emit('join_conversation', conversationId);
        });

        socket.on('newMessage', (payload) => {
            setLatestMessage(payload);
        });

        // Слушаем смену статуса пользователя
        socket.on('userStatusChanged', (data) => {
            setLatestMessage({ event: 'userStatusChanged', ...data });
        });

        return () => {
            // ВАЖНО: Не дисконнектим сразу, чтобы избежать ошибок React Strict Mode
            // или проверяем, если это окончательный демонтаж компонента
        };
    }, [conversationId, userId]);

    return { latestMessage, socket: socketRef.current };
};