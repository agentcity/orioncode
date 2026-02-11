import React from 'react';
import { ChatHeader } from '../components/Chat/ChatHeader';
import { MessageList } from '../components/Chat/MessageList';
import { MessageInput } from '../components/Chat/MessageInput';
import { useParams, useNavigate } from 'react-router-dom';
import {
    Box, Typography, CircularProgress, Dialog, IconButton
} from '@mui/material';
import CloseIcon from '@mui/icons-material/Close';
import { useChat } from '../hooks/useChat';
import { useAuth } from '../context/AuthContext';


export const ChatPage: React.FC<{ isMobile?: boolean }> = ({ isMobile }) => {
    const { id } = useParams<{ id: string }>();
    const navigate = useNavigate();
    const { user: currentUser } = useAuth();
    
    // Вся магия теперь тут
    const chat = useChat(id, currentUser);

    if (!chat.conversation) return <Box sx={{ p: 3 }}><CircularProgress /></Box>;


    return (
        <Box sx={{ display: 'flex', flexDirection: 'column', height: '100%', position: 'relative', minWidth: 0 }}>
            {/* Header */}
            <ChatHeader
                conversation={chat.conversation}
                chatPartner={chat.chatPartner}
                isContactOnline={chat.isContactOnline}
                isMobile={isMobile}
                onBack={() => navigate('/dashboard')}
            />


            {/* Messages Body */}
            <MessageList 
                messages={chat.messages}
                conversation={chat.conversation}
                currentUser={currentUser}
                imageErrors={chat.imageErrors}
                setImageErrors={chat.setImageErrors}
                setSelectedImage={chat.setSelectedImage}
                messagesEndRef={chat.messagesEndRef}
                setNewMessageText = {chat.setNewMessageText}
                replyTo = {chat.replyTo}
                setReplyTo = {chat.setReplyTo}
            />


            {/* Индикатор печати */}
            {chat.isTyping && (
                <Box sx={{ px: 2, py: 0.5, bgcolor: 'rgba(255,255,255,0.8)', position: 'absolute', bottom: 80, left: 20, borderRadius: '10px', zIndex: 5 }}>
                    <Typography variant="caption" sx={{ fontStyle: 'italic', color: 'primary.main' }}>
                        {chat.conversation.contact?.mainName} печатает...
                    </Typography>
                </Box>
            )}

            {chat.replyTo && (
                <Box sx={{
                    p: 1,
                    display: 'flex',
                    alignItems: 'center',
                    bgcolor: 'rgba(0,0,0,0.03)',
                    borderLeft: '4px solid',
                    borderColor: 'primary.main'
                }}>
                    <Box sx={{ flex: 1, overflow: 'hidden' }}>
                        <Typography variant="caption" color="primary" fontWeight="bold">
                            Ответ на сообщение
                        </Typography>
                        <Typography variant="body2" noWrap sx={{ opacity: 0.8 }}>
                            {chat.replyTo.text}
                        </Typography>
                    </Box>
                    <IconButton size="small" onClick={() => chat.setReplyTo(null)}>
                        <CloseIcon fontSize="small" />
                    </IconButton>
                </Box>
            )}

            <MessageInput 
                newMessageText={chat.newMessageText}
                setNewMessageText={chat.setNewMessageText}
                handleSend={chat.handleSend}
                takePhoto={chat.takePhoto}
                isMobile={isMobile}
                socket={chat.socket}
                id={id}
                currentUser={currentUser}
            />


            {/* FULLSCREEN IMAGE DIALOG - Закрытие по клику на фото */}
            <Dialog
                open={!!chat.selectedImage}
                onClose={() => chat.setSelectedImage(null)}
                maxWidth="xl"
                PaperProps={{ sx: { bgcolor: 'transparent', boxShadow: 'none', overflow: 'hidden' } }}
            >
                <Box
                    onClick={() => chat.setSelectedImage(null)} // Клик по фото закрывает его
                    sx={{ width: '100%', height: '100%', display: 'flex', alignItems: 'center', justifyContent: 'center', cursor: 'zoom-out' }}
                >
                    <img
                        src={chat.selectedImage || ''}
                        alt="full size"
                        style={{ maxWidth: '100vw', maxHeight: '100vh', objectFit: 'contain' }}
                    />
                </Box>
            </Dialog>
        </Box>
    );
};

export default ChatPage;


