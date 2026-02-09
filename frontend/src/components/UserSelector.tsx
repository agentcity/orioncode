import React, { useEffect, useState, useMemo } from 'react';
import {
    Dialog, DialogTitle, List, ListItemButton, ListItemAvatar,
    Avatar, ListItemText, CircularProgress, Box, Typography,
    IconButton, Fade, TextField, InputAdornment
} from '@mui/material';
import { Search, Close, PersonAdd, HighlightOff } from '@mui/icons-material';
import axiosClient from '../api/axiosClient';

interface User { id: string; fullName: string; email: string; }

export const UserSelector: React.FC<{ open: boolean; onClose: () => void; onSelect: (userId: string) => void }> = ({ open, onClose, onSelect }) => {
    const [users, setUsers] = useState<User[]>([]);
    const [loading, setLoading] = useState(true);
    const [searchQuery, setSearchQuery] = useState(''); // Состояние поиска

    useEffect(() => {
        if (open) {
            setLoading(true);
            setSearchQuery(''); // Сбрасываем поиск при открытии
            axiosClient.get('/users').then(res => {
                setUsers(res.data);
                setLoading(false);
            }).catch(() => setLoading(false));
        }
    }, [open]);

    // Фильтрация пользователей "на лету"
    const filteredUsers = useMemo(() => {
        return users.filter(user =>
            user.fullName.toLowerCase().includes(searchQuery.toLowerCase()) ||
            user.email.toLowerCase().includes(searchQuery.toLowerCase())
        );
    }, [users, searchQuery]);

    return (
        <Dialog
            open={open}
            onClose={onClose}
            fullWidth
            maxWidth="xs"
            TransitionComponent={Fade}
            PaperProps={{
                sx: {
                    borderRadius: 4,
                    bgcolor: '#f4f7f6',
                    maxHeight: '80vh', // Ограничиваем высоту для мобилок
                    display: 'flex',
                    flexDirection: 'column'
                }
            }}
        >
            <DialogTitle sx={{ p: 2, bgcolor: 'white', borderBottom: '1px solid rgba(0,0,0,0.05)' }}>
                <Box sx={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', mb: 2 }}>
                    <Box sx={{ display: 'flex', alignItems: 'center', gap: 1 }}>
                        <PersonAdd sx={{ color: '#1976d2' }} />
                        <Typography variant="h6" sx={{ fontWeight: 700 }}>Новый чат</Typography>
                    </Box>
                    <IconButton onClick={onClose} size="small"><Close /></IconButton>
                </Box>

                {/* ПОЛЕ ПОИСКА */}
                <TextField
                    fullWidth
                    size="small"
                    placeholder="Поиск по имени или email..."
                    value={searchQuery}
                    onChange={(e) => setSearchQuery(e.target.value)}
                    InputProps={{
                        startAdornment: (
                            <InputAdornment position="start">
                                <Search sx={{ color: 'text.secondary', fontSize: 20 }} />
                            </InputAdornment>
                        ),
                        endAdornment: searchQuery && (
                            <InputAdornment position="end">
                                <IconButton size="small" onClick={() => setSearchQuery('')}>
                                    <HighlightOff sx={{ fontSize: 18 }} />
                                </IconButton>
                            </InputAdornment>
                        ),
                        sx: { borderRadius: 3, bgcolor: '#f0f2f5', border: 'none', '& fieldset': { border: 'none' } }
                    }}
                />
            </DialogTitle>

            <Box sx={{ flexGrow: 1, overflowY: 'auto', p: 1 }}>
                {loading ? (
                    <Box sx={{ display: 'flex', justifyContent: 'center', py: 8 }}><CircularProgress size={30} /></Box>
                ) : (
                    <List sx={{ pt: 0 }}>
                        {filteredUsers.map((user) => (
                            <ListItemButton
                                key={user.id}
                                onClick={() => onSelect(user.id)}
                                sx={{ borderRadius: 3, mb: 0.5, '&:hover': { bgcolor: 'rgba(25, 118, 210, 0.08)' } }}
                            >
                                <ListItemAvatar>
                                    <Avatar sx={{ bgcolor: '#1976d2', fontWeight: 600 }}>{user.fullName[0].toUpperCase()}</Avatar>
                                </ListItemAvatar>
                                <ListItemText
                                    primary={user.fullName}
                                    secondary={user.email}
                                    primaryTypographyProps={{ fontWeight: 600 }}
                                    secondaryTypographyProps={{ variant: 'caption' }}
                                />
                            </ListItemButton>
                        ))}

                        {filteredUsers.length === 0 && (
                            <Typography align="center" sx={{ py: 6, opacity: 0.5, fontSize: 14 }}>
                                {searchQuery ? 'Никого не нашли...' : 'Список пуст'}
                            </Typography>
                        )}
                    </List>
                )}
            </Box>
        </Dialog>
    );
};

