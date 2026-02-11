<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Чистая миграция для функционала цитирования (ReplyTo)
 */
final class Version20260211155614 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add reply_to_id to messages and optimization indexes';
    }

    public function up(Schema $schema): void
    {
        // 1. Добавляем колонку для цитат (UUID для PostgreSQL)
        $this->addSql('ALTER TABLE messages ADD reply_to_id UUID DEFAULT NULL');

        // 2. Создаем внешний ключ (Связь сообщения с самим собой)
        $this->addSql('ALTER TABLE messages ADD CONSTRAINT FK_DB021E96FFDF7169 FOREIGN KEY (reply_to_id) REFERENCES messages (id) ON DELETE SET NULL NOT DEFERRABLE');

        // 3. Создаем индекс для ускорения поиска по цитатам
        $this->addSql('CREATE INDEX IDX_DB021E96FFDF7169 ON messages (reply_to_id)');

        // 4. Добавляем важные индексы для скорости чата (если их еще нет)
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_message_sender_type ON messages (sender_type)');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_message_direction ON messages (direction)');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_message_sent_at ON messages (sent_at)');

        // 5. Поправляем колонку unread_count в беседах (если нужно)
        $this->addSql('ALTER TABLE conversations ALTER unread_count SET DEFAULT 0');
    }

    public function down(Schema $schema): void
    {
        // Откат изменений
        $this->addSql('ALTER TABLE messages DROP CONSTRAINT FK_DB021E96FFDF7169');
        $this->addSql('DROP INDEX IF EXISTS IDX_DB021E96FFDF7169');
        $this->addSql('DROP INDEX IF EXISTS idx_message_sender_type');
        $this->addSql('DROP INDEX IF EXISTS idx_message_direction');
        $this->addSql('DROP INDEX IF EXISTS idx_message_sent_at');
        $this->addSql('ALTER TABLE messages DROP reply_to_id');
    }
}
