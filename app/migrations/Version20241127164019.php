<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20241127164019 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE training_catalog_subscription (id INT AUTO_INCREMENT NOT NULL, subscription_id INT DEFAULT NULL, training_catalog_id INT DEFAULT NULL, step INT NOT NULL, INDEX IDX_D473B4529A1887DC (subscription_id), INDEX IDX_D473B4527682DC9A (training_catalog_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE training_catalog_subscription ADD CONSTRAINT FK_D473B4529A1887DC FOREIGN KEY (subscription_id) REFERENCES subscription (id)');
        $this->addSql('ALTER TABLE training_catalog_subscription ADD CONSTRAINT FK_D473B4527682DC9A FOREIGN KEY (training_catalog_id) REFERENCES training_catalog (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE training_catalog_subscription DROP FOREIGN KEY FK_D473B4529A1887DC');
        $this->addSql('ALTER TABLE training_catalog_subscription DROP FOREIGN KEY FK_D473B4527682DC9A');
        $this->addSql('DROP TABLE training_catalog_subscription');
    }
}
