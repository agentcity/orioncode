// src/hooks/useWebSocket.ts
import { useEffect, useState, useRef } from 'react';
import { io, Socket } from 'socket.io-client';
import { WebSocketMessagePayload } from '../types';

const WS_URL = process.env.REACT_APP_WS_URL || 'http://localhost:3000';

export const useWebSocket = () => {
    const [isConnected, setIsConnected] = useState(false);
    const [latestMessage, setLatestMessage] = useState<WebSocketMessagePayload | null>(null);
    const socketRef = useRef<Socket | null>(null);

    useEffect(() => {
        const socket = io(WS_URL);
        socketRef.current = socket;

        socket.on('connect', () => {
            setIsConnected(true);
            console.log('Connected to WebSocket server');
            // TODO: Отправить токен для аутентификации на WS-сервере, если требуется
            // socket.emit('authenticate', localStorage.getItem('jwt_token'));
        });

        socket.on('disconnect', () => {
            setIsConnected(false);
            console.log('Disconnected from WebSocket server');
        });

        socket.on('newMessage', (payload: WebSocketMessagePayload) => {
            console.log('New message via WS:', payload);
            setLatestMessage(payload);
        });

        socket.on('connect_error', (error) => {
            console.error('WebSocket connection error:', error);
        });

        return () => {
            socket.disconnect();
        };
    }, []);

    // Функция для отправки сообщений через WebSocket (если нужно)
    const sendMessage = (event: string, data: any) => {
        if (socketRef.current && isConnected) {
            socketRef.current.emit(event, data);
        } else {
            console.warn('WebSocket not connected, cannot send message.');
        }
    };

    return { isConnected, latestMessage, sendMessage };
};