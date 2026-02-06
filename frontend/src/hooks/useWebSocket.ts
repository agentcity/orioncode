import { useEffect, useState, useRef } from 'react';
import { io, Socket } from 'socket.io-client';
import { WS_URL } from '../api/config';

export const useWebSocket = (conversationId?: string, userId?: string) => {
    const [latestMessage, setLatestMessage] = useState<any>(null);
    const socketRef = useRef<Socket | null>(null);

    useEffect(() => {
        const socket = socketRef.current;
        if (!socket) return;

        // Если мы уже подключены, но сменили чат - заходим в новую комнату сразу
        if (socket.connected && conversationId) {
            console.log('WS: Re-joining room', conversationId);
            socket.emit('join_conversation', conversationId);
        }

        // Слушаем коннект
        const onConnect = () => {
            console.log('WS: Connected');
            if (conversationId) socket.emit('join_conversation', conversationId);
        };

        socket.on('connect', onConnect);
        // ... остальные обработчики

        return () => {
            socket.off('connect', onConnect);
            // Не закрывай сокет совсем, просто сними слушатели
        };
    }, [conversationId]);

    return { latestMessage, socket: socketRef.current };
};
