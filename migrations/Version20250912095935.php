<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250912095935 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE ko_suivi (id INT AUTO_INCREMENT NOT NULL, suivi_id INT NOT NULL, statut VARCHAR(20) NOT NULL, traite TINYINT(1) DEFAULT 0 NOT NULL, cause VARCHAR(255) DEFAULT NULL, commentaire LONGTEXT DEFAULT NULL, auteur VARCHAR(100) DEFAULT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_801BD8387FEA59C0 (suivi_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE ko_suivi ADD CONSTRAINT FK_801BD8387FEA59C0 FOREIGN KEY (suivi_id) REFERENCES suividupreparationdujour (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE liste_produits ADD ref VARCHAR(255) DEFAULT NULL, ADD des VARCHAR(255) DEFAULT NULL, ADD pinkg VARCHAR(255) DEFAULT NULL, DROP reference, DROP designation, DROP picking, DROP des_uc, DROP pcb, DROP des_uv, DROP serie, DROP serie_flag, DROP sku, DROP famille_stockage, DROP statut_article, DROP longueur, DROP largeur, DROP hauteur, DROP poids, DROP poids_brut, DROP seuil_reappro, CHANGE uv_en_stock uv_en_stock VARCHAR(255) DEFAULT NULL, CHANGE nbruc_pal nbruc_pal VARCHAR(255) NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE ko_suivi DROP FOREIGN KEY FK_801BD8387FEA59C0');
        $this->addSql('DROP TABLE ko_suivi');
        $this->addSql('ALTER TABLE liste_produits ADD reference VARCHAR(255) DEFAULT NULL, ADD designation VARCHAR(255) DEFAULT NULL, ADD picking VARCHAR(255) DEFAULT NULL, ADD des_uc VARCHAR(255) DEFAULT NULL, ADD pcb INT DEFAULT NULL, ADD des_uv VARCHAR(255) DEFAULT NULL, ADD serie VARCHAR(255) DEFAULT NULL, ADD serie_flag TINYINT(1) DEFAULT NULL, ADD sku VARCHAR(255) DEFAULT NULL, ADD famille_stockage VARCHAR(20) DEFAULT NULL, ADD statut_article VARCHAR(50) DEFAULT NULL, ADD longueur NUMERIC(10, 2) DEFAULT NULL, ADD largeur NUMERIC(10, 2) DEFAULT NULL, ADD hauteur NUMERIC(10, 2) DEFAULT NULL, ADD poids NUMERIC(10, 3) DEFAULT NULL, ADD poids_brut NUMERIC(10, 3) DEFAULT NULL, ADD seuil_reappro INT DEFAULT NULL, DROP ref, DROP des, DROP pinkg, CHANGE uv_en_stock uv_en_stock INT DEFAULT NULL, CHANGE nbruc_pal nbruc_pal INT DEFAULT NULL');
    }
}
