<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20241031164853 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE training_catalog (id INT AUTO_INCREMENT NOT NULL, sub_catalog_id INT DEFAULT NULL, name VARCHAR(255) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME DEFAULT NULL, INDEX IDX_C612A249E0F7F531 (sub_catalog_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE training_catalog ADD CONSTRAINT FK_C612A249E0F7F531 FOREIGN KEY (sub_catalog_id) REFERENCES training_catalog (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE subscription ADD type INT NOT NULL');
        $this->addSql('CREATE UNIQUE INDEX user_type ON subscription (user_id, type)');
        $this->addSql("UPDATE subscription SET type = 1 WHERE type != 1");
        $this->addSql("UPDATE setting SET value = 'Для покупки жми на кнопку и следуй инструкциям бота⬇️' WHERE name='methodMessage'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE training_catalog DROP FOREIGN KEY FK_C612A249E0F7F531');
        $this->addSql('DROP TABLE training_catalog');
        $this->addSql('DROP INDEX user_type ON subscription');
        $this->addSql('ALTER TABLE subscription DROP type');
    }
}
