<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261005105721 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE order_items (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, product_id CHAR(36) NOT NULL, quantity INTEGER NOT NULL, unit_price_amount_in_cents INTEGER NOT NULL, unit_price_currency VARCHAR(255) NOT NULL, order_id CHAR(36) NOT NULL, CONSTRAINT FK_62809DB08D9F6D38 FOREIGN KEY (order_id) REFERENCES orders (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_62809DB08D9F6D38 ON order_items (order_id)');
        $this->addSql('CREATE TABLE orders (status VARCHAR(255) NOT NULL, placed_at DATETIME DEFAULT NULL, id CHAR(36) NOT NULL, customer_id CHAR(36) NOT NULL, currency VARCHAR(255) NOT NULL, discount_amount_in_cents INTEGER NOT NULL, discount_currency VARCHAR(255) NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE TABLE stock_items (reservations CLOB NOT NULL, product_id CHAR(36) NOT NULL, on_hand INTEGER NOT NULL, PRIMARY KEY (product_id))');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE order_items');
        $this->addSql('DROP TABLE orders');
        $this->addSql('DROP TABLE stock_items');
    }
}
