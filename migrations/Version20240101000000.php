<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Tabulky stare administrace (src/Legacy).
 *
 * Puvodne MySQL schema z let 2014–2018, prevedene na SQLite pri spojeni
 * s novym e-shopem. Tabulky orders, order_items a stock_items vytvari
 * migrace noveho e-shopu.
 */
final class Version20240101000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Legacy admin tables (products, customers, admin_users, invoices, audit_log, ...)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE suppliers (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, name VARCHAR(255) NOT NULL, email VARCHAR(255) DEFAULT NULL, ico VARCHAR(20) DEFAULT NULL, created_at DATETIME DEFAULT NULL)');

        $this->addSql('CREATE TABLE products (id CHAR(36) NOT NULL, sku VARCHAR(64) DEFAULT NULL, name VARCHAR(255) NOT NULL, description TEXT DEFAULT NULL, price_cents INTEGER NOT NULL DEFAULT 0, currency VARCHAR(3) NOT NULL DEFAULT \'CZK\', active INTEGER NOT NULL DEFAULT 1, supplier_id INTEGER DEFAULT NULL, created_at DATETIME DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX idx_products_sku ON products (sku)');
        $this->addSql('CREATE INDEX idx_products_active ON products (active)');

        $this->addSql('CREATE TABLE customers (id CHAR(36) NOT NULL, email VARCHAR(255) NOT NULL, name VARCHAR(255) DEFAULT NULL, phone VARCHAR(32) DEFAULT NULL, created_at DATETIME DEFAULT NULL, newsletter INTEGER NOT NULL DEFAULT 0, note TEXT DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX idx_customers_email ON customers (email)');

        $this->addSql('CREATE TABLE admin_users (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, login VARCHAR(100) NOT NULL, password_md5 CHAR(32) NOT NULL, role VARCHAR(20) NOT NULL DEFAULT \'obchod\', last_login DATETIME DEFAULT NULL)');
        $this->addSql('CREATE UNIQUE INDEX uniq_admin_users_login ON admin_users (login)');

        $this->addSql('CREATE TABLE order_notes (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, order_id CHAR(36) NOT NULL, author VARCHAR(100) NOT NULL, note TEXT NOT NULL, created_at DATETIME NOT NULL)');
        $this->addSql('CREATE INDEX idx_order_notes_order ON order_notes (order_id)');

        $this->addSql('CREATE TABLE invoices (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, order_id CHAR(36) NOT NULL, number VARCHAR(20) NOT NULL, issued_at DATETIME NOT NULL, total_cents INTEGER NOT NULL, currency VARCHAR(3) NOT NULL)');
        $this->addSql('CREATE UNIQUE INDEX uniq_invoices_number ON invoices (number)');
        $this->addSql('CREATE INDEX idx_invoices_order ON invoices (order_id)');

        $this->addSql('CREATE TABLE audit_log (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, entity VARCHAR(50) NOT NULL, entity_id VARCHAR(255) NOT NULL, action VARCHAR(50) NOT NULL, payload TEXT DEFAULT NULL, created_at DATETIME NOT NULL)');
        $this->addSql('CREATE INDEX idx_audit_log_entity ON audit_log (entity, entity_id)');

        $this->addSql('CREATE TABLE exchange_rates (currency VARCHAR(3) NOT NULL, rate_to_czk REAL NOT NULL, updated_at DATETIME DEFAULT NULL, PRIMARY KEY (currency))');
        $this->addSql("INSERT INTO exchange_rates (currency, rate_to_czk, updated_at) VALUES ('EUR', 25.0, '2019-03-01 00:00:00')");
        $this->addSql("INSERT INTO exchange_rates (currency, rate_to_czk, updated_at) VALUES ('USD', 23.0, '2019-03-01 00:00:00')");

        $this->addSql('CREATE TABLE settings (name VARCHAR(100) NOT NULL, value TEXT DEFAULT NULL, PRIMARY KEY (name))');
        $this->addSql("INSERT INTO settings (name, value) VALUES ('shop_open', '1')");
        $this->addSql("INSERT INTO settings (name, value) VALUES ('bank_account', '123456789/0100')");

        $this->addSql('CREATE TABLE newsletter_queue (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, customer_id CHAR(36) DEFAULT NULL, email VARCHAR(255) NOT NULL, subject VARCHAR(255) NOT NULL, body TEXT NOT NULL, created_at DATETIME NOT NULL, sent_at DATETIME DEFAULT NULL)');
        $this->addSql('CREATE INDEX idx_newsletter_queue_sent ON newsletter_queue (sent_at)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE newsletter_queue');
        $this->addSql('DROP TABLE settings');
        $this->addSql('DROP TABLE exchange_rates');
        $this->addSql('DROP TABLE audit_log');
        $this->addSql('DROP TABLE invoices');
        $this->addSql('DROP TABLE order_notes');
        $this->addSql('DROP TABLE admin_users');
        $this->addSql('DROP TABLE customers');
        $this->addSql('DROP TABLE products');
        $this->addSql('DROP TABLE suppliers');
    }
}
