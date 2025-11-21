<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251117205116 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('
            CREATE TABLE IF NOT EXISTS `email_address` (
              `id` int unsigned NOT NULL AUTO_INCREMENT,
              `value` varchar(100) NOT NULL,
              `created_at` datetime DEFAULT NULL,
              `expired_at` datetime DEFAULT NULL,
              PRIMARY KEY (`id`),
              UNIQUE KEY `email_address_unique` (`value`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
        ');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS `email_address`');
    }
}
