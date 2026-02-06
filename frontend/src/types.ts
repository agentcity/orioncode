// src/types.ts

export interface User {
    id: string;
    email: string;
    firstName?: string;
    lastName?: string;
    // ... другие поля User
}

export interface Account {
    id: string;
    type: 'telegram' | 'whatsapp' | 'max';
    name: string;
    status: 'active' | 'inactive' | 'error';
    // ... другие поля Account
}

export interface Contact {
    id: string;
    mainName: string;
    avatarUrl?: string;
    // ... другие поля Contact
}

export interface Conversation {
    id: string;
    accountId: string;
    contact: Contact;
    type: 'telegram' | 'whatsapp' | 'internal' | 'max';
    status: 'open' | 'closed' | 'pending';
    lastMessageAt: string;
    unreadCount: number;
    assignedToId?: string;
    // ... другие поля Conversation
}

export interface Message {
    id: string;
    conversationId: string;
    senderType: 'user' | 'contact' | 'bot';
    senderId?: string; // ID User или Contact
    text?: string;
    isRead: boolean;
    direction: 'inbound' | 'outbound';
    payload?: {
        filePath?: string;
        [key: string]: any;
    };
    sentAt: string;
    status: 'sent' | 'delivered' | 'read' | 'replied' | 'failed' | string;
}

// Для WebSocket уведомлений
export interface WebSocketMessagePayload {
    conversationId: string;
    messageId: string;
    text?: string;
    senderName: string;
    type: 'incoming' | 'outgoing';
    messengerType: 'telegram' | 'whatsapp' | 'max';
    // ...
}
