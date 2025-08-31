<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250829200054 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE competence (id INT AUTO_INCREMENT NOT NULL, competence_id INT DEFAULT NULL, description VARCHAR(255) NOT NULL, nom VARCHAR(25) NOT NULL, categorie VARCHAR(25) NOT NULL, niveau INT NOT NULL, duree_de_pratique INT NOT NULL, INDEX IDX_94D4687F15761DAB (competence_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE contribution (id INT AUTO_INCREMENT NOT NULL, contribution_id INT DEFAULT NULL, contributionprojet_id INT DEFAULT NULL, nom VARCHAR(25) NOT NULL, description VARCHAR(255) NOT NULL, competences_necessaires VARCHAR(255) NOT NULL, INDEX IDX_EA351E15FE5E5FBD (contribution_id), INDEX IDX_EA351E15C2231444 (contributionprojet_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE message (id INT AUTO_INCREMENT NOT NULL, message_id INT DEFAULT NULL, titre VARCHAR(25) NOT NULL, contenu VARCHAR(255) NOT NULL, date_envoi DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', expediteur VARCHAR(255) NOT NULL, destinataire VARCHAR(255) NOT NULL, INDEX IDX_B6BD307F537A1329 (message_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE projet (id INT AUTO_INCREMENT NOT NULL, projet_id INT DEFAULT NULL, nom VARCHAR(25) NOT NULL, description VARCHAR(255) NOT NULL, competences_necessaires VARCHAR(255) NOT NULL, date_de_creation DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', date_de_fin DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_50159CA9C18272 (projet_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE utilisateur (id INT AUTO_INCREMENT NOT NULL, nom VARCHAR(25) NOT NULL, prenom VARCHAR(25) NOT NULL, courriel VARCHAR(25) NOT NULL, mot_de_passe VARCHAR(255) NOT NULL, telephone INT NOT NULL, type_utilisateur VARCHAR(255) NOT NULL, niveau_acces INT DEFAULT NULL, projet_cree VARCHAR(25) DEFAULT NULL, projet_participe VARCHAR(25) DEFAULT NULL, ApiToken VARCHAR(255) DEFAULT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE competence ADD CONSTRAINT FK_94D4687F15761DAB FOREIGN KEY (competence_id) REFERENCES utilisateur (id)');
        $this->addSql('ALTER TABLE contribution ADD CONSTRAINT FK_EA351E15FE5E5FBD FOREIGN KEY (contribution_id) REFERENCES utilisateur (id)');
        $this->addSql('ALTER TABLE contribution ADD CONSTRAINT FK_EA351E15C2231444 FOREIGN KEY (contributionprojet_id) REFERENCES projet (id)');
        $this->addSql('ALTER TABLE message ADD CONSTRAINT FK_B6BD307F537A1329 FOREIGN KEY (message_id) REFERENCES utilisateur (id)');
        $this->addSql('ALTER TABLE projet ADD CONSTRAINT FK_50159CA9C18272 FOREIGN KEY (projet_id) REFERENCES utilisateur (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE competence DROP FOREIGN KEY FK_94D4687F15761DAB');
        $this->addSql('ALTER TABLE contribution DROP FOREIGN KEY FK_EA351E15FE5E5FBD');
        $this->addSql('ALTER TABLE contribution DROP FOREIGN KEY FK_EA351E15C2231444');
        $this->addSql('ALTER TABLE message DROP FOREIGN KEY FK_B6BD307F537A1329');
        $this->addSql('ALTER TABLE projet DROP FOREIGN KEY FK_50159CA9C18272');
        $this->addSql('DROP TABLE competence');
        $this->addSql('DROP TABLE contribution');
        $this->addSql('DROP TABLE message');
        $this->addSql('DROP TABLE projet');
        $this->addSql('DROP TABLE utilisateur');
    }
}
