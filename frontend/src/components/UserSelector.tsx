import React, { useEffect, useState } from 'react';
import { Dialog, DialogTitle, List, ListItemButton, ListItemAvatar, Avatar, ListItemText, CircularProgress } from '@mui/material';
import axiosClient from '../api/axiosClient';

interface User { id: string; fullName: string; email: string; }

export const UserSelector: React.FC<{ open: boolean; onClose: () => void; onSelect: (userId: string) => void }> = ({ open, onClose, onSelect }) => {
    const [users, setUsers] = useState<User[]>([]);
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        if (open) {
            axiosClient.get('/users').then(res => {
                setUsers(res.data);
                setLoading(false);
            });
        }
    }, [open]);

    return (
        <Dialog open={open} onClose={onClose} fullWidth maxWidth="xs">
            <DialogTitle>Начать внутренний чат</DialogTitle>
            {loading ? <CircularProgress sx={{ m: 2 }} /> : (
                <List sx={{ pt: 0 }}>
                    {users.map((user) => (
                        <ListItemButton key={user.id} onClick={() => onSelect(user.id)}>
                            <ListItemAvatar><Avatar>{user.fullName[0]}</Avatar></ListItemAvatar>
                            <ListItemText primary={user.fullName} secondary={user.email} />
                        </ListItemButton>
                    ))}
                </List>
            )}
        </Dialog>
    );
};
