import React from 'react';
import { Box, Paper, Typography, CircularProgress } from '@mui/material';
import DoneIcon from '@mui/icons-material/Done';
import DoneAllIcon from '@mui/icons-material/DoneAll';
import ImageNotSupportedIcon from '@mui/icons-material/ImageNotSupported';

interface MessageListProps {
    messages: any[];
    conversation: any;
    currentUser: any;
    imageErrors: Record<string, boolean>;
    setImageErrors: React.Dispatch<React.SetStateAction<Record<string, boolean>>>;
    setSelectedImage: (url: string | null) => void;
    messagesEndRef: React.RefObject<HTMLDivElement | null>;
}

export const MessageList: React.FC<MessageListProps> = ({
    messages,
    conversation,
    currentUser,
    imageErrors,
    setImageErrors,
    setSelectedImage,
    messagesEndRef
}) => {
    const serverBase = (process.env.REACT_APP_API_URL || 'http://localhost:8080/api').replace(/\/api$/, '');

    return (
        <Box sx={{
            flexGrow: 1, overflowY: 'auto', p: 3, display: 'flex', flexDirection: 'column', 
            position: 'relative', bgcolor: '#5c7bb0',
            backgroundImage: `url("data:image/svg+xml,%3Csvg width='80' height='80' viewBox='0 0 80 80' xmlns='http://www.w3.org fill='%23ffffff' fill-opacity='0.15'%3E%3Ccircle cx='40' cy='40' r='2'/%3E%3C/g%3E%3C/svg%3E")`,
        }}>
            {messages.map((msg: any) => {
                const imageSrc = msg.preview ||
                    (msg.payload?.filePath ? `${serverBase}${msg.payload.filePath}` : null) ||
                    (msg.attachments?.[0]?.url ? `${serverBase}${msg.attachments[0].url}` : null);
                
                const isMine = conversation?.type === 'orion'
                    ? (String(msg.payload?.senderId).toLowerCase() === String(currentUser?.id).toLowerCase())
                    : (msg.direction === 'outbound' || msg.direction === 'outgoing');

                const hasError = imageErrors[msg.id] || false;

                return (
                    <Box key={msg.id} sx={{ display: 'flex', flexDirection: 'column', alignItems: isMine ? 'flex-end' : 'flex-start', mb: 2 }}>
                        <Paper elevation={2} sx={{
                            p: imageSrc ? 1 : 1.5,
                            bgcolor: isMine ? '#d1e4ff' : '#ffffff',
                            maxWidth: '85%', overflow: 'hidden',
                            borderRadius: isMine ? '18px 18px 4px 18px' : '18px 18px 18px 4px',
                            wordBreak: 'break-word'
                        }}>
                            {imageSrc && (
                                <Box
                                    sx={{ position: 'relative', lineHeight: 0, cursor: 'pointer' }}
                                    onClick={() => !msg.isUploading && !hasError && setSelectedImage(imageSrc)}
                                >
                                    {hasError ? (
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
                                            onError={() => setImageErrors(prev => ({ ...prev, [msg.id]: true }))}
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
    );
};
