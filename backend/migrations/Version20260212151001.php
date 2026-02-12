<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260212151001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // 1. Удаляем старый индекс (уже есть в файле)
        $this->addSql('DROP INDEX idx_message_sender_id');

        // 2. Создаем новую колонку для контакта и переименовываем старую (уже есть в файле)
        $this->addSql('ALTER TABLE messages ADD contact_id UUID DEFAULT NULL');
        $this->addSql('ALTER TABLE messages RENAME COLUMN sender_id TO manager_id');

        // 3. ПЕРЕРАСПРЕДЕЛЕНИЕ ДАННЫХ (Добавь это вручную! 🚀)
        // Те сообщения, которые были от клиентов (contact), переносим в contact_id
        // и обнуляем их в manager_id
        $this->addSql("UPDATE messages SET contact_id = manager_id, manager_id = NULL WHERE sender_type = 'contact'");

        // Те, что от бота — обнуляем в manager_id (т.к. бот не юзер)
        $this->addSql("UPDATE messages SET manager_id = NULL WHERE sender_type = 'bot'");

        // 4. ОЧИСТКА БИТЫХ ССЫЛОК (Чтобы FK не ругался)
        // Если в manager_id остались ID юзеров, которых нет в таблице users — обнуляем
        $this->addSql("UPDATE messages SET manager_id = NULL WHERE manager_id NOT IN (SELECT id FROM users)");

        // 5. Создаем связи и новые индексы (уже есть в файле)
        $this->addSql('ALTER TABLE messages ADD CONSTRAINT FK_DB021E96783E3463 FOREIGN KEY (manager_id) REFERENCES users (id) ON DELETE SET NULL NOT DEFERRABLE');
        $this->addSql('ALTER TABLE messages ADD CONSTRAINT FK_DB021E96E7A1254A FOREIGN KEY (contact_id) REFERENCES contacts (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('CREATE INDEX idx_message_manager ON messages (manager_id)');
        $this->addSql('CREATE INDEX idx_message_contact ON messages (contact_id)');
    }


    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE messages DROP CONSTRAINT FK_DB021E96783E3463');
        $this->addSql('ALTER TABLE messages DROP CONSTRAINT FK_DB021E96E7A1254A');
        $this->addSql('DROP INDEX idx_message_manager');
        $this->addSql('DROP INDEX idx_message_contact');
        $this->addSql('ALTER TABLE messages ADD sender_id UUID DEFAULT NULL');
        $this->addSql('ALTER TABLE messages DROP manager_id');
        $this->addSql('ALTER TABLE messages DROP contact_id');
        $this->addSql('CREATE INDEX idx_message_sender_id ON messages (sender_id)');
    }
}
