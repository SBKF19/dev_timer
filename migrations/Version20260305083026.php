<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260305083026 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE holiday (id INT AUTO_INCREMENT NOT NULL, date DATE NOT NULL, name VARCHAR(50) NOT NULL, UNIQUE INDEX UNIQ_DC9AB234AA9E377A (date), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE reset_password_request (id INT AUTO_INCREMENT NOT NULL, selector VARCHAR(20) NOT NULL, hashed_token VARCHAR(100) NOT NULL, requested_at DATETIME NOT NULL, expires_at DATETIME NOT NULL, user_id INT NOT NULL, INDEX IDX_7CE748AA76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE schedule (id INT AUTO_INCREMENT NOT NULL, start_time TIME NOT NULL, end_time TIME NOT NULL, day_of_week INT NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE reset_password_request ADD CONSTRAINT FK_7CE748AA76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE hour_entry DROP FOREIGN KEY `FK_2DFCEC3E2A4DB562`');
        $this->addSql('DROP INDEX IDX_2DFCEC3E2A4DB562 ON hour_entry');
        $this->addSql('ALTER TABLE hour_entry DROP activities_id');
        $this->addSql('ALTER TABLE hour_entry ADD CONSTRAINT FK_2DFCEC3E81C06096 FOREIGN KEY (activity_id) REFERENCES activities (id)');
        $this->addSql('ALTER TABLE hour_entry ADD CONSTRAINT FK_2DFCEC3EA76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE hour_entry ADD CONSTRAINT FK_2DFCEC3E166D1F9C FOREIGN KEY (project_id) REFERENCES project (id)');
        $this->addSql('ALTER TABLE hour_entry ADD CONSTRAINT FK_2DFCEC3EDE12AB56 FOREIGN KEY (created_by) REFERENCES `user` (id)');
        $this->addSql('CREATE INDEX IDX_2DFCEC3E81C06096 ON hour_entry (activity_id)');
        $this->addSql('CREATE INDEX IDX_2DFCEC3EA76ED395 ON hour_entry (user_id)');
        $this->addSql('CREATE INDEX IDX_2DFCEC3E166D1F9C ON hour_entry (project_id)');
        $this->addSql('CREATE INDEX IDX_2DFCEC3EDE12AB56 ON hour_entry (created_by)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE reset_password_request DROP FOREIGN KEY FK_7CE748AA76ED395');
        $this->addSql('DROP TABLE holiday');
        $this->addSql('DROP TABLE reset_password_request');
        $this->addSql('DROP TABLE schedule');
        $this->addSql('ALTER TABLE hour_entry DROP FOREIGN KEY FK_2DFCEC3E81C06096');
        $this->addSql('ALTER TABLE hour_entry DROP FOREIGN KEY FK_2DFCEC3EA76ED395');
        $this->addSql('ALTER TABLE hour_entry DROP FOREIGN KEY FK_2DFCEC3E166D1F9C');
        $this->addSql('ALTER TABLE hour_entry DROP FOREIGN KEY FK_2DFCEC3EDE12AB56');
        $this->addSql('DROP INDEX IDX_2DFCEC3E81C06096 ON hour_entry');
        $this->addSql('DROP INDEX IDX_2DFCEC3EA76ED395 ON hour_entry');
        $this->addSql('DROP INDEX IDX_2DFCEC3E166D1F9C ON hour_entry');
        $this->addSql('DROP INDEX IDX_2DFCEC3EDE12AB56 ON hour_entry');
        $this->addSql('ALTER TABLE hour_entry ADD activities_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE hour_entry ADD CONSTRAINT `FK_2DFCEC3E2A4DB562` FOREIGN KEY (activities_id) REFERENCES activities (id)');
        $this->addSql('CREATE INDEX IDX_2DFCEC3E2A4DB562 ON hour_entry (activities_id)');
    }
}
