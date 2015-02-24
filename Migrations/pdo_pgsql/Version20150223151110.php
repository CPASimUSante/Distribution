<?php

namespace HeVinci\CompetencyBundle\Migrations\pdo_pgsql;

use Doctrine\DBAL\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

/**
 * Auto-generated migration based on mapping information: modify it with caution
 *
 * Generation date: 2015/02/23 03:11:11
 */
class Version20150223151110 extends AbstractMigration
{
    public function up(Schema $schema)
    {
        $this->addSql("
            CREATE TABLE hevinci_competency_ability (
                id SERIAL NOT NULL, 
                competency_id INT NOT NULL, 
                ability_id INT NOT NULL, 
                level_id INT NOT NULL, 
                PRIMARY KEY(id)
            )
        ");
        $this->addSql("
            CREATE INDEX IDX_38178A41FB9F58C ON hevinci_competency_ability (competency_id)
        ");
        $this->addSql("
            CREATE INDEX IDX_38178A418016D8B2 ON hevinci_competency_ability (ability_id)
        ");
        $this->addSql("
            CREATE INDEX IDX_38178A415FB14BA7 ON hevinci_competency_ability (level_id)
        ");
        $this->addSql("
            CREATE TABLE hevinci_ability (
                id SERIAL NOT NULL, 
                name VARCHAR(255) NOT NULL, 
                PRIMARY KEY(id)
            )
        ");
        $this->addSql("
            ALTER TABLE hevinci_competency_ability 
            ADD CONSTRAINT FK_38178A41FB9F58C FOREIGN KEY (competency_id) 
            REFERENCES hevinci_competency (id) 
            ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE
        ");
        $this->addSql("
            ALTER TABLE hevinci_competency_ability 
            ADD CONSTRAINT FK_38178A418016D8B2 FOREIGN KEY (ability_id) 
            REFERENCES hevinci_ability (id) 
            ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE
        ");
        $this->addSql("
            ALTER TABLE hevinci_competency_ability 
            ADD CONSTRAINT FK_38178A415FB14BA7 FOREIGN KEY (level_id) 
            REFERENCES hevinci_level (id) 
            ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE
        ");
    }

    public function down(Schema $schema)
    {
        $this->addSql("
            ALTER TABLE hevinci_competency_ability 
            DROP CONSTRAINT FK_38178A418016D8B2
        ");
        $this->addSql("
            DROP TABLE hevinci_competency_ability
        ");
        $this->addSql("
            DROP TABLE hevinci_ability
        ");
    }
}