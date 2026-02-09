<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251231000853 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE container_user ADD CONSTRAINT FK_56836C6BC21F742 FOREIGN KEY (container_id) REFERENCES container (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE container_user ADD CONSTRAINT FK_56836C6A76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE container_project ADD CONSTRAINT FK_B1CB54A3BC21F742 FOREIGN KEY (container_id) REFERENCES container (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE container_project ADD CONSTRAINT FK_B1CB54A3166D1F9C FOREIGN KEY (project_id) REFERENCES project (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE notification ADD sender_id INT DEFAULT NULL, ADD project_id INT DEFAULT NULL, CHANGE message message LONGTEXT NOT NULL, CHANGE date date DATETIME NOT NULL, CHANGE id_user_id id_user_id INT NOT NULL');
        $this->addSql('ALTER TABLE notification ADD CONSTRAINT FK_BF5476CA79F37AE5 FOREIGN KEY (id_user_id) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE notification ADD CONSTRAINT FK_BF5476CA532BA8F6 FOREIGN KEY (id_task_id) REFERENCES task (id)');
        $this->addSql('ALTER TABLE notification ADD CONSTRAINT FK_BF5476CAF624B39D FOREIGN KEY (sender_id) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE notification ADD CONSTRAINT FK_BF5476CA166D1F9C FOREIGN KEY (project_id) REFERENCES project (id)');
        $this->addSql('CREATE INDEX IDX_BF5476CAF624B39D ON notification (sender_id)');
        $this->addSql('CREATE INDEX IDX_BF5476CA166D1F9C ON notification (project_id)');
        $this->addSql('ALTER TABLE task ADD CONSTRAINT FK_527EDB2579F37AE5 FOREIGN KEY (id_user_id) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE task ADD CONSTRAINT FK_527EDB25B3E79F4B FOREIGN KEY (id_project_id) REFERENCES project (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE container_project DROP FOREIGN KEY FK_B1CB54A3BC21F742');
        $this->addSql('ALTER TABLE container_project DROP FOREIGN KEY FK_B1CB54A3166D1F9C');
        $this->addSql('ALTER TABLE container_user DROP FOREIGN KEY FK_56836C6BC21F742');
        $this->addSql('ALTER TABLE container_user DROP FOREIGN KEY FK_56836C6A76ED395');
        $this->addSql('ALTER TABLE notification DROP FOREIGN KEY FK_BF5476CA79F37AE5');
        $this->addSql('ALTER TABLE notification DROP FOREIGN KEY FK_BF5476CA532BA8F6');
        $this->addSql('ALTER TABLE notification DROP FOREIGN KEY FK_BF5476CAF624B39D');
        $this->addSql('ALTER TABLE notification DROP FOREIGN KEY FK_BF5476CA166D1F9C');
        $this->addSql('DROP INDEX IDX_BF5476CAF624B39D ON notification');
        $this->addSql('DROP INDEX IDX_BF5476CA166D1F9C ON notification');
        $this->addSql('ALTER TABLE notification DROP sender_id, DROP project_id, CHANGE message message VARCHAR(255) NOT NULL, CHANGE date date DATE NOT NULL, CHANGE id_user_id id_user_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE task DROP FOREIGN KEY FK_527EDB2579F37AE5');
        $this->addSql('ALTER TABLE task DROP FOREIGN KEY FK_527EDB25B3E79F4B');
    }
}
