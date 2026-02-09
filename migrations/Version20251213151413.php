<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251213151413 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE container (id INT AUTO_INCREMENT NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8');
        $this->addSql('CREATE TABLE container_user (container_id INT NOT NULL, user_id INT NOT NULL, INDEX IDX_56836C6BC21F742 (container_id), INDEX IDX_56836C6A76ED395 (user_id), PRIMARY KEY (container_id, user_id)) DEFAULT CHARACTER SET utf8');
        $this->addSql('CREATE TABLE container_project (container_id INT NOT NULL, project_id INT NOT NULL, INDEX IDX_B1CB54A3BC21F742 (container_id), INDEX IDX_B1CB54A3166D1F9C (project_id), PRIMARY KEY (container_id, project_id)) DEFAULT CHARACTER SET utf8');
        $this->addSql('ALTER TABLE container_user ADD CONSTRAINT FK_56836C6BC21F742 FOREIGN KEY (container_id) REFERENCES container (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE container_user ADD CONSTRAINT FK_56836C6A76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE container_project ADD CONSTRAINT FK_B1CB54A3BC21F742 FOREIGN KEY (container_id) REFERENCES container (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE container_project ADD CONSTRAINT FK_B1CB54A3166D1F9C FOREIGN KEY (project_id) REFERENCES project (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE notification ADD CONSTRAINT FK_BF5476CA79F37AE5 FOREIGN KEY (id_user_id) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE notification ADD CONSTRAINT FK_BF5476CA532BA8F6 FOREIGN KEY (id_task_id) REFERENCES task (id)');
        $this->addSql('ALTER TABLE task ADD CONSTRAINT FK_527EDB2579F37AE5 FOREIGN KEY (id_user_id) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE task ADD CONSTRAINT FK_527EDB25B3E79F4B FOREIGN KEY (id_project_id) REFERENCES project (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE container_user DROP FOREIGN KEY FK_56836C6BC21F742');
        $this->addSql('ALTER TABLE container_user DROP FOREIGN KEY FK_56836C6A76ED395');
        $this->addSql('ALTER TABLE container_project DROP FOREIGN KEY FK_B1CB54A3BC21F742');
        $this->addSql('ALTER TABLE container_project DROP FOREIGN KEY FK_B1CB54A3166D1F9C');
        $this->addSql('DROP TABLE container');
        $this->addSql('DROP TABLE container_user');
        $this->addSql('DROP TABLE container_project');
        $this->addSql('ALTER TABLE notification DROP FOREIGN KEY FK_BF5476CA79F37AE5');
        $this->addSql('ALTER TABLE notification DROP FOREIGN KEY FK_BF5476CA532BA8F6');
        $this->addSql('ALTER TABLE task DROP FOREIGN KEY FK_527EDB2579F37AE5');
        $this->addSql('ALTER TABLE task DROP FOREIGN KEY FK_527EDB25B3E79F4B');
    }
}
