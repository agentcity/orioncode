<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260211155614 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE INDEX idx_account_type ON accounts (type)');
        $this->addSql('CREATE INDEX idx_account_status ON accounts (status)');
        $this->addSql('ALTER INDEX idx_cac89eaca76ed395 RENAME TO idx_account_user');
        $this->addSql('ALTER INDEX idx_47c4fad6537a1329 RENAME TO idx_attachment_message');
        $this->addSql('CREATE INDEX idx_contact_main_name ON contacts (main_name)');
        $this->addSql('ALTER TABLE conversations ALTER type TYPE VARCHAR(255)');
        $this->addSql('ALTER TABLE conversations ALTER unread_count SET DEFAULT 0');
        $this->addSql('ALTER TABLE conversations ALTER account_id DROP NOT NULL');
        $this->addSql('CREATE INDEX idx_conversation_external_id ON conversations (external_id)');
        $this->addSql('CREATE INDEX idx_conversation_type ON conversations (type)');
        $this->addSql('CREATE INDEX idx_conversation_status ON conversations (status)');
        $this->addSql('CREATE INDEX idx_conversation_last_message ON conversations (last_message_at)');
        $this->addSql('CREATE INDEX idx_conversation_unread_count ON conversations (unread_count)');
        $this->addSql('CREATE UNIQUE INDEX uniq_conversation_external ON conversations (account_id, type, external_id)');
        $this->addSql('ALTER INDEX idx_c2521bf19b6b5fba RENAME TO idx_conversation_account');
        $this->addSql('ALTER INDEX idx_c2521bf1e7a1254a RENAME TO idx_conversation_contact');
        $this->addSql('ALTER INDEX idx_c2521bf1f4bd7827 RENAME TO idx_conversation_assigned_to');
        $this->addSql('ALTER TABLE messages ADD reply_to_id UUID DEFAULT NULL');
        $this->addSql('ALTER TABLE messages ADD CONSTRAINT FK_DB021E96FFDF7169 FOREIGN KEY (reply_to_id) REFERENCES messages (id) ON DELETE SET NULL NOT DEFERRABLE');
        $this->addSql('CREATE INDEX IDX_DB021E96FFDF7169 ON messages (reply_to_id)');
        $this->addSql('CREATE INDEX idx_message_sender_type ON messages (sender_type)');
        $this->addSql('CREATE INDEX idx_message_sender_id ON messages (sender_id)');
        $this->addSql('CREATE INDEX idx_message_direction ON messages (direction)');
        $this->addSql('CREATE INDEX idx_message_sent_at ON messages (sent_at)');
        $this->addSql('ALTER INDEX idx_db021e969ac0396 RENAME TO idx_message_conversation');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP INDEX idx_account_type');
        $this->addSql('DROP INDEX idx_account_status');
        $this->addSql('ALTER INDEX idx_account_user RENAME TO idx_cac89eaca76ed395');
        $this->addSql('ALTER INDEX idx_attachment_message RENAME TO idx_47c4fad6537a1329');
        $this->addSql('DROP INDEX idx_contact_main_name');
        $this->addSql('DROP INDEX idx_conversation_external_id');
        $this->addSql('DROP INDEX idx_conversation_type');
        $this->addSql('DROP INDEX idx_conversation_status');
        $this->addSql('DROP INDEX idx_conversation_last_message');
        $this->addSql('DROP INDEX idx_conversation_unread_count');
        $this->addSql('DROP INDEX uniq_conversation_external');
        $this->addSql('ALTER TABLE conversations ALTER type TYPE VARCHAR(50)');
        $this->addSql('ALTER TABLE conversations ALTER unread_count DROP DEFAULT');
        $this->addSql('ALTER TABLE conversations ALTER account_id SET NOT NULL');
        $this->addSql('ALTER INDEX idx_conversation_account RENAME TO idx_c2521bf19b6b5fba');
        $this->addSql('ALTER INDEX idx_conversation_assigned_to RENAME TO idx_c2521bf1f4bd7827');
        $this->addSql('ALTER INDEX idx_conversation_contact RENAME TO idx_c2521bf1e7a1254a');
        $this->addSql('ALTER TABLE messages DROP CONSTRAINT FK_DB021E96FFDF7169');
        $this->addSql('DROP INDEX IDX_DB021E96FFDF7169');
        $this->addSql('DROP INDEX idx_message_sender_type');
        $this->addSql('DROP INDEX idx_message_sender_id');
        $this->addSql('DROP INDEX idx_message_direction');
        $this->addSql('DROP INDEX idx_message_sent_at');
        $this->addSql('ALTER TABLE messages DROP reply_to_id');
        $this->addSql('ALTER INDEX idx_message_conversation RENAME TO idx_db021e969ac0396');
    }
}
