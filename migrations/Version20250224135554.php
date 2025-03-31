<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250224135554 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE inventairecomplet (id INT AUTO_INCREMENT NOT NULL, nopalinfo VARCHAR(255) NOT NULL, codeprod VARCHAR(255) NOT NULL, dsignprod VARCHAR(255) NOT NULL, emplacement VARCHAR(255) NOT NULL, nopal VARCHAR(255) NOT NULL, urdispo INT NOT NULL, ucdispo INT NOT NULL, uvtotal VARCHAR(255) NOT NULL, uvensortie INT NOT NULL, urbloquee INT NOT NULL, zone VARCHAR(255) NOT NULL, emplacementdoublon VARCHAR(255) NOT NULL, dateentree DATE NOT NULL COMMENT \'(DC2Type:date_immutable)\', PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE inventairecomplet');
    }
}
