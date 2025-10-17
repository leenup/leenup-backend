<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251017191928 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE "user" ADD first_name VARCHAR(100) NOT NULL');
        $this->addSql('ALTER TABLE "user" ADD last_name VARCHAR(100) NOT NULL');
        $this->addSql('ALTER TABLE "user" ADD avatar_url VARCHAR(500) DEFAULT NULL');
        $this->addSql('ALTER TABLE "user" ADD bio TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE "user" ADD location VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE "user" ADD timezone VARCHAR(50) DEFAULT NULL');
        $this->addSql('ALTER TABLE "user" ADD locale VARCHAR(5) DEFAULT NULL');
        $this->addSql('ALTER TABLE "user" ADD is_active BOOLEAN NOT NULL');
        $this->addSql('ALTER TABLE "user" ADD last_login_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE "user" DROP first_name');
        $this->addSql('ALTER TABLE "user" DROP last_name');
        $this->addSql('ALTER TABLE "user" DROP avatar_url');
        $this->addSql('ALTER TABLE "user" DROP bio');
        $this->addSql('ALTER TABLE "user" DROP location');
        $this->addSql('ALTER TABLE "user" DROP timezone');
        $this->addSql('ALTER TABLE "user" DROP locale');
        $this->addSql('ALTER TABLE "user" DROP is_active');
        $this->addSql('ALTER TABLE "user" DROP last_login_at');
    }
}
