import React, { useRef, useEffect } from 'react';
import { Box, IconButton, TextField, Button } from '@mui/material';
import PhotoCameraIcon from '@mui/icons-material/PhotoCamera';
import SendIcon from '@mui/icons-material/Send';


interface MessageInputProps {
    handleSend: (text: string) => void; // Теперь передаем текст прямо в функцию
    takePhoto: () => void;
    isMobile?: boolean;
    socket: any;
    id?: string;
    currentUser?: any;
}

// 1. Используем React.memo, чтобы ввод не зависел от рендеров списка сообщений
export const MessageInput: React.FC<MessageInputProps> = React.memo(({
                                                                         handleSend,
                                                                         takePhoto,
                                                                         isMobile,
                                                                         socket,
                                                                         id,
                                                                         currentUser
                                                                     }) => {
    // 2. Используем реф вместо стейта для мгновенного отклика букв
    const inputRef = useRef<HTMLTextAreaElement>(null);
    const lastTypingTime = useRef<number>(0);

    const onSendClick = () => {
        const text = inputRef.current?.value.trim();
        if (text) {
            handleSend(text);
            if (inputRef.current) inputRef.current.value = ''; // Очищаем мгновенно
        }
    };

    return (
        <Box sx={{
            p: 1,
            // 🚀 ДОБАВЛЯЕМ ОТСТУП СНИЗУ:
            // env(safe-area-inset-bottom) - это стандарт для iPhone/Android
            // Если браузер его не знает, подставим 16px или 24px для мобилок
            pb: isMobile ? 'calc(env(safe-area-inset-bottom) + 16px)' : 1,
            bgcolor: 'white',
            borderTop: 1,
            borderColor: 'divider',
            display: 'flex',
            alignItems: 'flex-end',
            // Плавное поднятие при появлении клавиатуры (на некоторых браузерах)
            transition: 'padding 0.2s ease-in-out'
        }}>
            <IconButton onClick={takePhoto} color="primary" sx={{ mb: 0.5, mr: 0.5 }}>
                <PhotoCameraIcon />
            </IconButton>

            <TextField
                fullWidth
                multiline
                minRows={1}
                maxRows={5}
                inputRef={inputRef} // Привязываем реф
                size="small"
                placeholder="Сообщение..."
                onChange={(e) => {
                    // Тайпинг шлем не чаще чем раз в 2 секунды
                    const now = Date.now();
                    if (socket && id && now - lastTypingTime.current > 2000) {
                        socket.emit('typing', { conversationId: id, userId: currentUser?.id });
                        lastTypingTime.current = now;
                    }
                }}
                onKeyDown={(e) => {
                    if (e.key === 'Enter' && !e.shiftKey && !isMobile) {
                        e.preventDefault();
                        onSendClick();
                    }
                }}
                sx={{
                    ml: 1, mb: 1,
                    '& .MuiOutlinedInput-root': {
                        borderRadius: '20px',
                        bgcolor: '#f1f3f4',
                        padding: '8px 12px',
                        fontSize: '16px' // Важно против зума!
                    }
                }}
            />
            <Button
                variant="contained"
                onClick={onSendClick}
                sx={{ ml: 2, mb: 1, borderRadius: '25px', px: 4 }}
            >
                {isMobile ? (
                    <SendIcon sx={{
                        fontSize: '24px',
                        ml: '3px',
                        color: 'white'
                    }} />
                ) : (
                    'ОТПРАВИТЬ'
                )}
            </Button>
        </Box>
    );
});
