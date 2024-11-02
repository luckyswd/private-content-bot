<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20241101172503 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('DROP INDEX UNIQ_DFEC3F395E237E06 ON rate');
        $this->addSql('ALTER TABLE rate ADD subscription_type INT NOT NULL');
        $this->addSql("UPDATE rate SET subscription_type = 1 WHERE subscription_type != 1");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('CREATE UNIQUE INDEX UNIQ_DFEC3F395E237E06 ON rate (name)');
        $this->addSql('ALTER TABLE rate DROP type');
    }
}
