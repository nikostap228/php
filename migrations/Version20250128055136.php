<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250128055136 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE application (id INT AUTO_INCREMENT NOT NULL, portfolio_id INT NOT NULL, stock_id INT NOT NULL, quantity INT NOT NULL, cost DOUBLE PRECISION NOT NULL, action VARCHAR(10) NOT NULL, INDEX IDX_A45BDDC1B96B5643 (portfolio_id), INDEX IDX_A45BDDC1DCD6110 (stock_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE application ADD CONSTRAINT FK_A45BDDC1B96B5643 FOREIGN KEY (portfolio_id) REFERENCES portfolio (id)');
        $this->addSql('ALTER TABLE application ADD CONSTRAINT FK_A45BDDC1DCD6110 FOREIGN KEY (stock_id) REFERENCES stock (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE application DROP FOREIGN KEY FK_A45BDDC1B96B5643');
        $this->addSql('ALTER TABLE application DROP FOREIGN KEY FK_A45BDDC1DCD6110');
        $this->addSql('DROP TABLE application');
    }
}
