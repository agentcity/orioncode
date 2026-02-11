import React, { useState } from 'react';
import ReactMarkdown from 'react-markdown';
import remarkGfm from 'remark-gfm';
import { Box, IconButton, Tooltip, Typography } from '@mui/material';
import ContentCopyIcon from '@mui/icons-material/ContentCopy';
import CheckIcon from '@mui/icons-material/Check';
import ReplyIcon from '@mui/icons-material/Reply';

interface Props {
    msg: any; // Объект сообщения целиком
    isMine: boolean;
    onReply?: (message: any) => void; // Функция для активации цитирования
}

export const MessageTxt: React.FC<Props> = ({ msg, isMine, onReply }) => {
    const [copied, setCopied] = useState(false);

    // Достаем данные о цитате из payload (если они там есть)
    const replyData = msg.payload?.replyTo;

    const handleCopy = async () => {
        try {
            await navigator.clipboard.writeText(msg.text);
            setCopied(true);
            setTimeout(() => setCopied(false), 2000);
        } catch (err) {
            console.error('Ошибка копирования:', err);
        }
    };

    return (
        <Box sx={{
            display: 'flex',
            flexDirection: 'column',
            position: 'relative',
            minWidth: '60px'
        }}>
            {/* БЛОК ЦИТАТЫ (Reply Preview внутри облачка) */}
            {replyData && (
                <Box sx={{
                    mb: 1,
                    p: '4px 8px',
                    bgcolor: isMine ? 'rgba(255,255,255,0.15)' : 'rgba(0,0,0,0.05)',
                    borderLeft: '3px solid',
                    borderColor: isMine ? '#fff' : 'primary.main',
                    borderRadius: '4px',
                    cursor: 'pointer',
                    '&:hover': { opacity: 0.8 }
                }}>
                    <Typography
                        variant="caption"
                        sx={{
                            fontWeight: 'bold',
                            display: 'block',
                            color: isMine ? '#fff' : 'primary.main'
                        }}
                    >
                        {isMine ? 'Вы' : (msg.conversation?.contact?.mainName || 'Собеседник')}
                    </Typography>
                    <Typography
                        variant="caption"
                        noWrap
                        sx={{
                            display: 'block',
                            opacity: 0.9,
                            fontStyle: 'italic',
                            maxWidth: '200px'
                        }}
                    >
                        {replyData.text}
                    </Typography>
                </Box>
            )}

            {/* ОСНОВНОЙ ТЕКСТ (Markdown) */}
            <Box sx={{
                '& p': { m: 0, wordBreak: 'break-word', whiteSpace: 'pre-wrap' },
                '& code': { bgcolor: 'rgba(0,0,0,0.08)', px: 0.5, borderRadius: '4px', fontFamily: 'monospace' },
                '& ul, & ol': { pl: 2, m: 0 }
            }}>
                <ReactMarkdown remarkPlugins={[remarkGfm]}>
                    {msg.text}
                </ReactMarkdown>
            </Box>

            {/* НИЖНЯЯ ПАНЕЛЬ С КНОПКАМИ */}
            <Box sx={{
                display: 'flex',
                alignItems: 'center',
                justifyContent: 'flex-end',
                mt: 0.5,
                opacity: 0.4,
                '&:hover': { opacity: 1 },
                transition: 'opacity 0.2s'
            }}>
                {/* Кнопка ОТВЕТИТЬ */}
                <Tooltip title="Ответить">
                    <IconButton
                        onClick={() => onReply && onReply(msg)}
                        size="small"
                        sx={{ p: 0, ml: 1.5 }}
                    >
                        <ReplyIcon sx={{ fontSize: 16, transform: 'scaleX(-1)', color: isMine ? '#fff' : 'inherit' }} />
                    </IconButton>
                </Tooltip>

                {/* Кнопка КОПИРОВАТЬ */}
                <Tooltip title={copied ? "Скопировано!" : "Копировать"}>
                    <IconButton
                        onClick={handleCopy}
                        size="small"
                        sx={{ p: 0, ml: 1.2, mr: 0.5 }}
                    >
                        {copied ? (
                            <CheckIcon sx={{ fontSize: 14, color: isMine ? '#fff' : 'success.main' }} />
                        ) : (
                            <ContentCopyIcon sx={{ fontSize: 14, color: isMine ? '#fff' : 'inherit' }} />
                        )}
                    </IconButton>
                </Tooltip>
            </Box>
        </Box>
    );
};

