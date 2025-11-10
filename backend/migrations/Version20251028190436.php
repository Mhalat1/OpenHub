<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251028190436 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE conversation ADD conv_user_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE conversation ADD CONSTRAINT FK_8A8E26E91DA67974 FOREIGN KEY (conv_user_id) REFERENCES user (id)');
        $this->addSql('CREATE INDEX IDX_8A8E26E91DA67974 ON conversation (conv_user_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE conversation DROP FOREIGN KEY FK_8A8E26E91DA67974');
        $this->addSql('DROP INDEX IDX_8A8E26E91DA67974 ON conversation');
        $this->addSql('ALTER TABLE conversation DROP conv_user_id');
    }
}
