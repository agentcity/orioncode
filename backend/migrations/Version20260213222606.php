<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260213222606 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE billing_payments (id UUID NOT NULL, amount NUMERIC(12, 2) NOT NULL, status VARCHAR(20) NOT NULL, external_id VARCHAR(255) DEFAULT NULL, bank_response JSON DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, confirmed_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, organization_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_7D0EF2CE32C8A3DE ON billing_payments (organization_id)');
        $this->addSql('CREATE INDEX idx_payment_external_id ON billing_payments (external_id)');
        $this->addSql('CREATE INDEX idx_payment_status ON billing_payments (status)');
        $this->addSql('CREATE TABLE organizations (id UUID NOT NULL, name VARCHAR(255) NOT NULL, balance NUMERIC(12, 2) DEFAULT 0 NOT NULL, subscription_plan VARCHAR(20) DEFAULT \'trial\' NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE TABLE organization_users (organization_id UUID NOT NULL, user_id UUID NOT NULL, PRIMARY KEY (organization_id, user_id))');
        $this->addSql('CREATE INDEX IDX_9A04432E32C8A3DE ON organization_users (organization_id)');
        $this->addSql('CREATE INDEX IDX_9A04432EA76ED395 ON organization_users (user_id)');
        $this->addSql('ALTER TABLE billing_payments ADD CONSTRAINT FK_7D0EF2CE32C8A3DE FOREIGN KEY (organization_id) REFERENCES organizations (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE organization_users ADD CONSTRAINT FK_9A04432E32C8A3DE FOREIGN KEY (organization_id) REFERENCES organizations (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE organization_users ADD CONSTRAINT FK_9A04432EA76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE accounts DROP CONSTRAINT fk_cac89eaca76ed395');
        $this->addSql('ALTER TABLE accounts ADD organization_id UUID DEFAULT NULL');
        $this->addSql('ALTER TABLE accounts ALTER user_id DROP NOT NULL');
        $this->addSql('ALTER TABLE accounts ADD CONSTRAINT FK_CAC89EACA76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL NOT DEFERRABLE');
        $this->addSql('ALTER TABLE accounts ADD CONSTRAINT FK_CAC89EAC32C8A3DE FOREIGN KEY (organization_id) REFERENCES organizations (id) ON DELETE SET NULL NOT DEFERRABLE');
        $this->addSql('CREATE INDEX IDX_CAC89EAC32C8A3DE ON accounts (organization_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE billing_payments DROP CONSTRAINT FK_7D0EF2CE32C8A3DE');
        $this->addSql('ALTER TABLE organization_users DROP CONSTRAINT FK_9A04432E32C8A3DE');
        $this->addSql('ALTER TABLE organization_users DROP CONSTRAINT FK_9A04432EA76ED395');
        $this->addSql('DROP TABLE billing_payments');
        $this->addSql('DROP TABLE organizations');
        $this->addSql('DROP TABLE organization_users');
        $this->addSql('ALTER TABLE accounts DROP CONSTRAINT FK_CAC89EACA76ED395');
        $this->addSql('ALTER TABLE accounts DROP CONSTRAINT FK_CAC89EAC32C8A3DE');
        $this->addSql('DROP INDEX IDX_CAC89EAC32C8A3DE');
        $this->addSql('ALTER TABLE accounts DROP organization_id');
        $this->addSql('ALTER TABLE accounts ALTER user_id SET NOT NULL');
        $this->addSql('ALTER TABLE accounts ADD CONSTRAINT fk_cac89eaca76ed395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }
}
