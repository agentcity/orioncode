import { useEffect, useState, useRef } from 'react';
import { io, Socket } from 'socket.io-client';
import { WS_URL } from '../api/config';

export const useWebSocket = (conversationId?: string, userId?: string) => {
    const [latestMessage, setLatestMessage] = useState<any>(null);
    const socketRef = useRef<Socket | null>(null);

    useEffect(() => {
        if (!socketRef.current) {
            console.log('Попытка подключения к WS:', WS_URL);
            socketRef.current = io(WS_URL, {
                transports: ["websocket"]
            });

            socketRef.current.on('connect', () => {
                console.log('✅ WS подключен к серверу!');
            });

            socketRef.current.on('connect_error', (err) => {
                console.error('❌ Ошибка подключения к WS:', err.message);
            });
        }

        // ПЕРЕПОДПИСКА при смене чата
        if (conversationId && socketRef.current?.connected) {
            console.log('WS: Joining conversation room:', conversationId);
            socketRef.current.emit('join_conversation', conversationId);
        }

    }, [conversationId, userId]); // Следим за сменой чата и юзера

    return { latestMessage, socket: socketRef.current };
};
