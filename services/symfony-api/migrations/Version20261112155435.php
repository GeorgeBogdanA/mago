<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * First schema: product (spec docs/specs/products-list.md).
 */
final class Version20261112155435 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create product table with the partial index of the public list';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE product (id UUID NOT NULL, sku VARCHAR(64) NOT NULL, name VARCHAR(255) NOT NULL, slug VARCHAR(255) NOT NULL, description TEXT NOT NULL, type VARCHAR(16) NOT NULL, price INT NOT NULL, currency CHAR(3) NOT NULL, image_url VARCHAR(2048) DEFAULT NULL, quantity INT NOT NULL, status VARCHAR(16) NOT NULL, cost_price INT NOT NULL, download_url VARCHAR(2048) DEFAULT NULL, created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_D34A04ADF9038C4 ON product (sku)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_D34A04AD989D9B62 ON product (slug)');
        // Hand-written: DESC is not expressible in the mapping. Matches the only list query:
        // WHERE status = 'published' ORDER BY created_at DESC, id ASC (spec products-list, Performance).
        $this->addSql("CREATE INDEX idx_product_published_list ON product (created_at DESC, id ASC) WHERE status = 'published'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE product');
    }
}
