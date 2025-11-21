<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251117224703 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE email_message ADD COLUMN from_addr VARCHAR(100) DEFAULT NULL AFTER ip');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE email_message DROP COLUMN from_addr');
    }
}
