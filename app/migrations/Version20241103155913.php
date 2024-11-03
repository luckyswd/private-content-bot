<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20241103155913 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE post_training (id INT AUTO_INCREMENT NOT NULL, training_catalog_id INT NOT NULL, message_id INT NOT NULL, bot_name VARCHAR(255) DEFAULT NULL, algorithm_number INT NOT NULL, post_order INT NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME DEFAULT NULL, INDEX IDX_6AFF032E7682DC9A (training_catalog_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE post_training ADD CONSTRAINT FK_6AFF032E7682DC9A FOREIGN KEY (training_catalog_id) REFERENCES training_catalog (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE post_training DROP FOREIGN KEY FK_6AFF032E7682DC9A');
        $this->addSql('DROP TABLE post_training');
    }
}
