<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260205202926 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE conversations DROP CONSTRAINT fk_c2521bf1f4bd7827');
        $this->addSql('ALTER TABLE conversations DROP CONSTRAINT fk_c2521bf1e7a1254a');
        $this->addSql('ALTER TABLE conversations DROP CONSTRAINT fk_c2521bf19b6b5fba');
        $this->addSql('ALTER TABLE conversations DROP metadata');
        $this->addSql('ALTER TABLE conversations ALTER external_id DROP NOT NULL');
        $this->addSql('ALTER TABLE conversations ALTER status TYPE VARCHAR(50)');
        $this->addSql('ALTER TABLE conversations ALTER last_message_at SET NOT NULL');
        $this->addSql('ALTER TABLE conversations ALTER contact_id DROP NOT NULL');
        $this->addSql('ALTER TABLE conversations ADD CONSTRAINT FK_C2521BF1F4BD7827 FOREIGN KEY (assigned_to_id) REFERENCES users (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE conversations ADD CONSTRAINT FK_C2521BF1E7A1254A FOREIGN KEY (contact_id) REFERENCES contacts (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE conversations ADD CONSTRAINT FK_C2521BF19B6B5FBA FOREIGN KEY (account_id) REFERENCES accounts (id) NOT DEFERRABLE');
        $this->addSql('DROP INDEX idx_user_email');
        $this->addSql('DROP INDEX idx_user_is_active');
        $this->addSql('ALTER TABLE users ALTER first_name TYPE VARCHAR(100)');
        $this->addSql('ALTER TABLE users ALTER first_name SET NOT NULL');
        $this->addSql('ALTER TABLE users ALTER last_name TYPE VARCHAR(100)');
        $this->addSql('ALTER TABLE users ALTER last_name SET NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE conversations DROP CONSTRAINT FK_C2521BF19B6B5FBA');
        $this->addSql('ALTER TABLE conversations DROP CONSTRAINT FK_C2521BF1E7A1254A');
        $this->addSql('ALTER TABLE conversations DROP CONSTRAINT FK_C2521BF1F4BD7827');
        $this->addSql('ALTER TABLE conversations ADD metadata JSON DEFAULT NULL');
        $this->addSql('ALTER TABLE conversations ALTER external_id SET NOT NULL');
        $this->addSql('ALTER TABLE conversations ALTER status TYPE VARCHAR(20)');
        $this->addSql('ALTER TABLE conversations ALTER last_message_at DROP NOT NULL');
        $this->addSql('ALTER TABLE conversations ALTER contact_id SET NOT NULL');
        $this->addSql('ALTER TABLE conversations ADD CONSTRAINT fk_c2521bf19b6b5fba FOREIGN KEY (account_id) REFERENCES accounts (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE conversations ADD CONSTRAINT fk_c2521bf1e7a1254a FOREIGN KEY (contact_id) REFERENCES contacts (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE conversations ADD CONSTRAINT fk_c2521bf1f4bd7827 FOREIGN KEY (assigned_to_id) REFERENCES users (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE users ALTER first_name TYPE VARCHAR(255)');
        $this->addSql('ALTER TABLE users ALTER first_name DROP NOT NULL');
        $this->addSql('ALTER TABLE users ALTER last_name TYPE VARCHAR(255)');
        $this->addSql('ALTER TABLE users ALTER last_name DROP NOT NULL');
        $this->addSql('CREATE INDEX idx_user_email ON users (email)');
        $this->addSql('CREATE INDEX idx_user_is_active ON users (is_active)');
    }
}
