<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260205165003 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE contacts DROP is_online');
        $this->addSql('ALTER TABLE conversations ADD target_user_id UUID DEFAULT NULL');
        $this->addSql('ALTER TABLE conversations ADD CONSTRAINT FK_C2521BF16C066AFE FOREIGN KEY (target_user_id) REFERENCES users (id) NOT DEFERRABLE');
        $this->addSql('CREATE INDEX IDX_C2521BF16C066AFE ON conversations (target_user_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE contacts ADD is_online BOOLEAN DEFAULT false');
        $this->addSql('ALTER TABLE conversations DROP CONSTRAINT FK_C2521BF16C066AFE');
        $this->addSql('DROP INDEX IDX_C2521BF16C066AFE');
        $this->addSql('ALTER TABLE conversations DROP target_user_id');
    }
}
