import { useEffect, useState, useRef } from 'react';
import { io, Socket } from 'socket.io-client';
import { WS_URL } from '../api/config';

export const useWebSocket = (conversationId?: string, userId?: string) => {
    const [latestMessage, setLatestMessage] = useState<any>(null);
    const socketRef = useRef<Socket | null>(null);

    useEffect(() => {
        if (!socketRef.current) {
            socketRef.current = io(WS_URL, {
                transports: ["websocket"],
                reconnection: true,
            });
        }

        const socket = socketRef.current;

        const onConnect = () => {
            console.log('✅ WS: Connected');
            if (userId) socket.emit('authenticate', userId);
            if (conversationId) socket.emit('join_conversation', conversationId);
        };

        const onNewMessage = (payload: any) => {
            console.log('✉️ WS: New message received', payload);
            setLatestMessage(payload);
        };

        const onStatusChange = (data: any) => {
            setLatestMessage({ event: 'userStatusChanged', ...data });
        };

        socket.on('connect', onConnect);
        socket.on('newMessage', onNewMessage);
        socket.on('userStatusChanged', onStatusChange);

        // Если сокет уже подключен (при смене id чата)
        if (socket.connected && conversationId) {
            socket.emit('join_conversation', conversationId);
        }

        return () => {
            socket.off('connect', onConnect);
            socket.off('newMessage', onNewMessage);
            socket.off('userStatusChanged', onStatusChange);
        };
    }, [conversationId, userId]); // Реагирует на смену чата

    return { latestMessage, socket: socketRef.current };
};

