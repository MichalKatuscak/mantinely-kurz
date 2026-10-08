<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261005115235 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE orders ADD COLUMN cancellation_note CLOB DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TEMPORARY TABLE __temp__orders AS SELECT status, placed_at, id, customer_id, currency, discount_amount_in_cents, discount_currency FROM orders');
        $this->addSql('DROP TABLE orders');
        $this->addSql('CREATE TABLE orders (status VARCHAR(255) NOT NULL, placed_at DATETIME DEFAULT NULL, id CHAR(36) NOT NULL, customer_id CHAR(36) NOT NULL, currency VARCHAR(255) NOT NULL, discount_amount_in_cents INTEGER NOT NULL, discount_currency VARCHAR(255) NOT NULL, PRIMARY KEY (id))');
        $this->addSql('INSERT INTO orders (status, placed_at, id, customer_id, currency, discount_amount_in_cents, discount_currency) SELECT status, placed_at, id, customer_id, currency, discount_amount_in_cents, discount_currency FROM __temp__orders');
        $this->addSql('DROP TABLE __temp__orders');
    }
}
