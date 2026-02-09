<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260109192506 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add creator_id to project table and set it for existing projects';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE container_user ADD CONSTRAINT FK_56836C6BC21F742 FOREIGN KEY (container_id) REFERENCES container (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE container_user ADD CONSTRAINT FK_56836C6A76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE container_project ADD CONSTRAINT FK_B1CB54A3BC21F742 FOREIGN KEY (container_id) REFERENCES container (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE container_project ADD CONSTRAINT FK_B1CB54A3166D1F9C FOREIGN KEY (project_id) REFERENCES project (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE message ADD CONSTRAINT FK_B6BD307FF624B39D FOREIGN KEY (sender_id) REFERENCES `user` (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE message ADD CONSTRAINT FK_B6BD307FCD53EDB6 FOREIGN KEY (receiver_id) REFERENCES `user` (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE notification ADD CONSTRAINT FK_BF5476CA79F37AE5 FOREIGN KEY (id_user_id) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE notification ADD CONSTRAINT FK_BF5476CA532BA8F6 FOREIGN KEY (id_task_id) REFERENCES task (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE notification ADD CONSTRAINT FK_BF5476CAF624B39D FOREIGN KEY (sender_id) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE notification ADD CONSTRAINT FK_BF5476CA166D1F9C FOREIGN KEY (project_id) REFERENCES project (id) ON DELETE CASCADE');
        
        // ✅ Ajouter creator_id avec valeur par défaut temporaire
        $this->addSql('ALTER TABLE project ADD creator_id INT NOT NULL DEFAULT 1, CHANGE evaluation evaluation VARCHAR(255) DEFAULT NULL');
        
        // ✅ Mettre à jour les projets existants avec le premier utilisateur du premier container
        $this->addSql('
            UPDATE project p
            LEFT JOIN container_project cp ON p.id = cp.project_id
            LEFT JOIN container_user cu ON cp.container_id = cu.container_id
            SET p.creator_id = COALESCE(
                (
                    SELECT cu2.user_id
                    FROM container_project cp2
                    INNER JOIN container_user cu2 ON cp2.container_id = cu2.container_id
                    WHERE cp2.project_id = p.id
                    ORDER BY cp2.container_id ASC, cu2.user_id ASC
                    LIMIT 1
                ),
                1
            )
        ');
        
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
        $this->addSql('ALTER TABLE message DROP FOREIGN KEY FK_B6BD307FF624B39D');
        $this->addSql('ALTER TABLE message DROP FOREIGN KEY FK_B6BD307FCD53EDB6');
        $this->addSql('ALTER TABLE notification DROP FOREIGN KEY FK_BF5476CA79F37AE5');
        $this->addSql('ALTER TABLE notification DROP FOREIGN KEY FK_BF5476CA532BA8F6');
        $this->addSql('ALTER TABLE notification DROP FOREIGN KEY FK_BF5476CAF624B39D');
        $this->addSql('ALTER TABLE notification DROP FOREIGN KEY FK_BF5476CA166D1F9C');
        $this->addSql('ALTER TABLE project DROP creator_id, CHANGE evaluation evaluation VARCHAR(255) NOT NULL');
        $this->addSql('ALTER TABLE task DROP FOREIGN KEY FK_527EDB2579F37AE5');
        $this->addSql('ALTER TABLE task DROP FOREIGN KEY FK_527EDB25B3E79F4B');
    }
}