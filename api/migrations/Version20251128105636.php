<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251128105636 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE "user" ADD birthdate DATE DEFAULT NULL');
        $this->addSql('ALTER TABLE "user" ADD languages JSON DEFAULT NULL');
        $this->addSql('ALTER TABLE "user" ADD exchange_format VARCHAR(50) DEFAULT NULL');
        $this->addSql('ALTER TABLE "user" ADD learning_styles JSON DEFAULT NULL');
        $this->addSql('ALTER TABLE "user" ADD is_mentor BOOLEAN NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE "user" DROP birthdate');
        $this->addSql('ALTER TABLE "user" DROP languages');
        $this->addSql('ALTER TABLE "user" DROP exchange_format');
        $this->addSql('ALTER TABLE "user" DROP learning_styles');
        $this->addSql('ALTER TABLE "user" DROP is_mentor');
    }
}
