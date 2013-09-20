<?php

namespace Icap\DropzoneBundle\Migrations\ibm_db2;

use Doctrine\DBAL\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

/**
 * Auto-generated migration based on mapping information: modify it with caution
 *
 * Generation date: 2013/09/19 05:30:24
 */
class Version20130919173022 extends AbstractMigration
{
    public function up(Schema $schema)
    {
        $this->addSql("
            CREATE TABLE icap__dropzonebundle_correction (
                id INTEGER GENERATED BY DEFAULT AS IDENTITY NOT NULL, 
                user_id INTEGER NOT NULL, 
                drop_id INTEGER DEFAULT NULL, 
                drop_zone_id INTEGER NOT NULL, 
                total_grade NUMERIC(10, 2) DEFAULT NULL, 
                \"comment\" CLOB(1M) DEFAULT NULL, 
                valid SMALLINT NOT NULL, 
                start_date TIMESTAMP(0) NOT NULL, 
                last_open_date TIMESTAMP(0) NOT NULL, 
                end_date TIMESTAMP(0) DEFAULT NULL, 
                finished SMALLINT NOT NULL, 
                editable SMALLINT NOT NULL, 
                reporter SMALLINT NOT NULL, 
                reportComment CLOB(1M) DEFAULT NULL, 
                PRIMARY KEY(id)
            )
        ");
        $this->addSql("
            CREATE INDEX IDX_CDA81F40A76ED395 ON icap__dropzonebundle_correction (user_id)
        ");
        $this->addSql("
            CREATE INDEX IDX_CDA81F404D224760 ON icap__dropzonebundle_correction (drop_id)
        ");
        $this->addSql("
            CREATE INDEX IDX_CDA81F40A8C6E7BD ON icap__dropzonebundle_correction (drop_zone_id)
        ");
        $this->addSql("
            CREATE TABLE icap__dropzonebundle_criterion (
                id INTEGER GENERATED BY DEFAULT AS IDENTITY NOT NULL, 
                drop_zone_id INTEGER NOT NULL, 
                instruction CLOB(1M) NOT NULL, 
                PRIMARY KEY(id)
            )
        ");
        $this->addSql("
            CREATE INDEX IDX_F94B3BA7A8C6E7BD ON icap__dropzonebundle_criterion (drop_zone_id)
        ");
        $this->addSql("
            CREATE TABLE icap__dropzonebundle_document (
                id INTEGER GENERATED BY DEFAULT AS IDENTITY NOT NULL, 
                resource_node_id INTEGER DEFAULT NULL, 
                drop_id INTEGER NOT NULL, 
                url VARCHAR(255) DEFAULT NULL, 
                \"path\" VARCHAR(255) DEFAULT NULL, 
                PRIMARY KEY(id)
            )
        ");
        $this->addSql("
            CREATE INDEX IDX_744084241BAD783F ON icap__dropzonebundle_document (resource_node_id)
        ");
        $this->addSql("
            CREATE INDEX IDX_744084244D224760 ON icap__dropzonebundle_document (drop_id)
        ");
        $this->addSql("
            CREATE TABLE icap__dropzonebundle_drop (
                id INTEGER GENERATED BY DEFAULT AS IDENTITY NOT NULL, 
                drop_zone_id INTEGER NOT NULL, 
                user_id INTEGER NOT NULL, 
                drop_date TIMESTAMP(0) NOT NULL, 
                reported SMALLINT NOT NULL, 
                finished SMALLINT NOT NULL, 
                PRIMARY KEY(id)
            )
        ");
        $this->addSql("
            CREATE INDEX IDX_3AD19BA6A8C6E7BD ON icap__dropzonebundle_drop (drop_zone_id)
        ");
        $this->addSql("
            CREATE INDEX IDX_3AD19BA6A76ED395 ON icap__dropzonebundle_drop (user_id)
        ");
        $this->addSql("
            CREATE UNIQUE INDEX unique_drop_for_user_in_drop_zone ON icap__dropzonebundle_drop (drop_zone_id, user_id)
        ");
        $this->addSql("
            CREATE TABLE icap__dropzonebundle_dropzone (
                id INTEGER GENERATED BY DEFAULT AS IDENTITY NOT NULL, 
                edition_state SMALLINT NOT NULL, 
                instruction CLOB(1M) DEFAULT NULL, 
                allow_workspace_resource SMALLINT NOT NULL, 
                allow_upload SMALLINT NOT NULL, 
                allow_url SMALLINT NOT NULL, 
                allow_rich_text SMALLINT NOT NULL, 
                peer_review SMALLINT NOT NULL, 
                expected_total_correction SMALLINT NOT NULL, 
                display_notation_to_learners SMALLINT NOT NULL, 
                display_notation_message_to_learners SMALLINT NOT NULL, 
                minimum_score_to_pass SMALLINT NOT NULL, 
                manual_planning SMALLINT NOT NULL, 
                manual_state VARCHAR(255) NOT NULL, 
                start_allow_drop TIMESTAMP(0) DEFAULT NULL, 
                end_allow_drop TIMESTAMP(0) DEFAULT NULL, 
                start_review TIMESTAMP(0) DEFAULT NULL, 
                end_review TIMESTAMP(0) DEFAULT NULL, 
                allow_comment_in_correction SMALLINT NOT NULL, 
                total_criteria_column SMALLINT NOT NULL, 
                resourceNode_id INTEGER DEFAULT NULL, 
                PRIMARY KEY(id)
            )
        ");
        $this->addSql("
            CREATE UNIQUE INDEX UNIQ_6782FC23B87FAB32 ON icap__dropzonebundle_dropzone (resourceNode_id)
        ");
        $this->addSql("
            CREATE TABLE icap__dropzonebundle_grade (
                id INTEGER GENERATED BY DEFAULT AS IDENTITY NOT NULL, 
                criterion_id INTEGER NOT NULL, 
                correction_id INTEGER NOT NULL, 
                \"value\" SMALLINT NOT NULL, 
                PRIMARY KEY(id)
            )
        ");
        $this->addSql("
            CREATE INDEX IDX_B3C52D9397766307 ON icap__dropzonebundle_grade (criterion_id)
        ");
        $this->addSql("
            CREATE INDEX IDX_B3C52D9394AE086B ON icap__dropzonebundle_grade (correction_id)
        ");
        $this->addSql("
            CREATE UNIQUE INDEX unique_grade_for_criterion_and_correction ON icap__dropzonebundle_grade (criterion_id, correction_id)
        ");
        $this->addSql("
            ALTER TABLE icap__dropzonebundle_correction 
            ADD CONSTRAINT FK_CDA81F40A76ED395 FOREIGN KEY (user_id) 
            REFERENCES claro_user (id)
        ");
        $this->addSql("
            ALTER TABLE icap__dropzonebundle_correction 
            ADD CONSTRAINT FK_CDA81F404D224760 FOREIGN KEY (drop_id) 
            REFERENCES icap__dropzonebundle_drop (id) 
            ON DELETE SET NULL
        ");
        $this->addSql("
            ALTER TABLE icap__dropzonebundle_correction 
            ADD CONSTRAINT FK_CDA81F40A8C6E7BD FOREIGN KEY (drop_zone_id) 
            REFERENCES icap__dropzonebundle_dropzone (id) 
            ON DELETE CASCADE
        ");
        $this->addSql("
            ALTER TABLE icap__dropzonebundle_criterion 
            ADD CONSTRAINT FK_F94B3BA7A8C6E7BD FOREIGN KEY (drop_zone_id) 
            REFERENCES icap__dropzonebundle_dropzone (id) 
            ON DELETE CASCADE
        ");
        $this->addSql("
            ALTER TABLE icap__dropzonebundle_document 
            ADD CONSTRAINT FK_744084241BAD783F FOREIGN KEY (resource_node_id) 
            REFERENCES claro_resource_node (id) 
            ON DELETE SET NULL
        ");
        $this->addSql("
            ALTER TABLE icap__dropzonebundle_document 
            ADD CONSTRAINT FK_744084244D224760 FOREIGN KEY (drop_id) 
            REFERENCES icap__dropzonebundle_drop (id) 
            ON DELETE CASCADE
        ");
        $this->addSql("
            ALTER TABLE icap__dropzonebundle_drop 
            ADD CONSTRAINT FK_3AD19BA6A8C6E7BD FOREIGN KEY (drop_zone_id) 
            REFERENCES icap__dropzonebundle_dropzone (id) 
            ON DELETE CASCADE
        ");
        $this->addSql("
            ALTER TABLE icap__dropzonebundle_drop 
            ADD CONSTRAINT FK_3AD19BA6A76ED395 FOREIGN KEY (user_id) 
            REFERENCES claro_user (id)
        ");
        $this->addSql("
            ALTER TABLE icap__dropzonebundle_dropzone 
            ADD CONSTRAINT FK_6782FC23B87FAB32 FOREIGN KEY (resourceNode_id) 
            REFERENCES claro_resource_node (id) 
            ON DELETE CASCADE
        ");
        $this->addSql("
            ALTER TABLE icap__dropzonebundle_grade 
            ADD CONSTRAINT FK_B3C52D9397766307 FOREIGN KEY (criterion_id) 
            REFERENCES icap__dropzonebundle_criterion (id) 
            ON DELETE CASCADE
        ");
        $this->addSql("
            ALTER TABLE icap__dropzonebundle_grade 
            ADD CONSTRAINT FK_B3C52D9394AE086B FOREIGN KEY (correction_id) 
            REFERENCES icap__dropzonebundle_correction (id) 
            ON DELETE CASCADE
        ");
    }

    public function down(Schema $schema)
    {
        $this->addSql("
            ALTER TABLE icap__dropzonebundle_grade 
            DROP FOREIGN KEY FK_B3C52D9394AE086B
        ");
        $this->addSql("
            ALTER TABLE icap__dropzonebundle_grade 
            DROP FOREIGN KEY FK_B3C52D9397766307
        ");
        $this->addSql("
            ALTER TABLE icap__dropzonebundle_correction 
            DROP FOREIGN KEY FK_CDA81F404D224760
        ");
        $this->addSql("
            ALTER TABLE icap__dropzonebundle_document 
            DROP FOREIGN KEY FK_744084244D224760
        ");
        $this->addSql("
            ALTER TABLE icap__dropzonebundle_correction 
            DROP FOREIGN KEY FK_CDA81F40A8C6E7BD
        ");
        $this->addSql("
            ALTER TABLE icap__dropzonebundle_criterion 
            DROP FOREIGN KEY FK_F94B3BA7A8C6E7BD
        ");
        $this->addSql("
            ALTER TABLE icap__dropzonebundle_drop 
            DROP FOREIGN KEY FK_3AD19BA6A8C6E7BD
        ");
        $this->addSql("
            DROP TABLE icap__dropzonebundle_correction
        ");
        $this->addSql("
            DROP TABLE icap__dropzonebundle_criterion
        ");
        $this->addSql("
            DROP TABLE icap__dropzonebundle_document
        ");
        $this->addSql("
            DROP TABLE icap__dropzonebundle_drop
        ");
        $this->addSql("
            DROP TABLE icap__dropzonebundle_dropzone
        ");
        $this->addSql("
            DROP TABLE icap__dropzonebundle_grade
        ");
    }
}