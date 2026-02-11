<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260211212437 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE contacts ADD source VARCHAR(50) DEFAULT NULL');
        $this->addSql('ALTER TABLE contacts ADD external_id VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE contacts ADD account_id UUID DEFAULT NULL');
        $this->addSql('ALTER TABLE contacts ADD CONSTRAINT FK_334015739B6B5FBA FOREIGN KEY (account_id) REFERENCES accounts (id) ON DELETE SET NULL NOT DEFERRABLE');
        $this->addSql('CREATE INDEX IDX_334015739B6B5FBA ON contacts (account_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE contacts DROP CONSTRAINT FK_334015739B6B5FBA');
        $this->addSql('DROP INDEX IDX_334015739B6B5FBA');
        $this->addSql('ALTER TABLE contacts DROP source');
        $this->addSql('ALTER TABLE contacts DROP external_id');
        $this->addSql('ALTER TABLE contacts DROP account_id');
    }
}
