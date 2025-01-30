<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250129070114 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE portfolio_stock (id INT AUTO_INCREMENT NOT NULL, portfolio_id INT NOT NULL, stock_id INT NOT NULL, quantity INT NOT NULL, frozen INT NOT NULL, INDEX IDX_166E7C25B96B5643 (portfolio_id), INDEX IDX_166E7C25DCD6110 (stock_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE portfolio_stock ADD CONSTRAINT FK_166E7C25B96B5643 FOREIGN KEY (portfolio_id) REFERENCES portfolio (id)');
        $this->addSql('ALTER TABLE portfolio_stock ADD CONSTRAINT FK_166E7C25DCD6110 FOREIGN KEY (stock_id) REFERENCES stock (id)');
        $this->addSql('ALTER TABLE portfolio ADD cash DOUBLE PRECISION NOT NULL, ADD frozen_cash DOUBLE PRECISION NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE portfolio_stock DROP FOREIGN KEY FK_166E7C25B96B5643');
        $this->addSql('ALTER TABLE portfolio_stock DROP FOREIGN KEY FK_166E7C25DCD6110');
        $this->addSql('DROP TABLE portfolio_stock');
        $this->addSql('ALTER TABLE portfolio DROP cash, DROP frozen_cash');
    }
}
