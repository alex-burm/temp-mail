<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251101162803 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('
            CREATE TABLE IF NOT EXISTS `email_message` (
              `id` binary(16) NOT NULL,
              `ip` varchar(100) DEFAULT NULL,
              `recipient` varchar(100) DEFAULT NULL,
              `data` longtext,
              `headers` json DEFAULT NULL,
              `html` longtext,
              `text` longtext,
              `spf` json DEFAULT NULL,
              `dkim` json DEFAULT NULL,
              `dmarc` json DEFAULT NULL,
              `created_at` datetime DEFAULT NULL,
              PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
        ');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('drop table if exists email_message');
    }
}
