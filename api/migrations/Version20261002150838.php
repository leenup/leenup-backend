<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261002150838 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add user.review_count and backfill review_count/average_rating from existing reviews';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE "user" ADD review_count INT DEFAULT 0 NOT NULL');

        // average_rating n'était recalculée qu'à la création d'une review : on la remet d'aplomb en même temps.
        $this->addSql(<<<'SQL'
            UPDATE "user" u
            SET review_count = COALESCE(stats.review_count, 0),
                average_rating = stats.average_rating
            FROM (
                SELECT u2.id AS user_id, COUNT(r.id) AS review_count, ROUND(AVG(r.rating), 2) AS average_rating
                FROM "user" u2
                LEFT JOIN session s ON s.mentor_id = u2.id
                LEFT JOIN review r ON r.session_id = s.id
                GROUP BY u2.id
            ) stats
            WHERE stats.user_id = u.id
            SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE "user" DROP review_count');
    }
}
