<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260115120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add token balance to users and token settlement marker to sessions';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE "user" ADD token_balance INT DEFAULT 0 NOT NULL');
        $this->addSql('ALTER TABLE session ADD token_processed_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE session DROP token_processed_at');
        $this->addSql('ALTER TABLE "user" DROP token_balance');
    }
}
