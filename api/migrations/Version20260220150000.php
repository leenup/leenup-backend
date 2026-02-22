<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260220150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add avatar file name column for Vich profile image uploads';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE "user" ADD avatar_file_name VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE "user" DROP avatar_file_name');
    }
}
