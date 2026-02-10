import React from 'react';
import { Box, IconButton, TextField, Button } from '@mui/material';
import PhotoCameraIcon from '@mui/icons-material/PhotoCamera';

interface MessageInputProps {
    newMessageText: string;
    setNewMessageText: (text: string) => void;
    handleSend: () => void;
    takePhoto: () => void;
    isMobile?: boolean;
    socket: any;
    id?: string;
    currentUser?: any;
}

export const MessageInput: React.FC<MessageInputProps> = ({
    newMessageText,
    setNewMessageText,
    handleSend,
    takePhoto,
    isMobile,
    socket,
    id,
    currentUser
}) => {
    return (
        <Box sx={{ p: 2, bgcolor: 'white', borderTop: 1, borderColor: 'divider', display: 'flex', alignItems: 'center' }}>
            <IconButton onClick={takePhoto} color="primary" sx={{ mr: 1 }}>
                <PhotoCameraIcon />
            </IconButton>
            <TextField 
                fullWidth 
                size="small" 
                placeholder="Напишите сообщение..." 
                value={newMessageText}
                onChange={(e) => {
                    setNewMessageText(e.target.value);
                    // Отправляем событие "печатает"
                    if (socket && id && e.target.value.length > 0) {
                        socket.emit('typing', {
                            conversationId: id,
                            userId: currentUser?.id
                        });
                    }
                }}
                onKeyDown={(e) => e.key === 'Enter' && handleSend()}
                sx={{ '& .MuiOutlinedInput-root': { borderRadius: '25px', bgcolor: '#f1f3f4' } }}
            />
            <Button 
                variant="contained" 
                onClick={handleSend} 
                sx={{ ml: 2, borderRadius: '25px', px: 4 }}
            >
                {isMobile ? '🚀' : 'ОТПРАВИТЬ'}
            </Button>
        </Box>
    );
};
