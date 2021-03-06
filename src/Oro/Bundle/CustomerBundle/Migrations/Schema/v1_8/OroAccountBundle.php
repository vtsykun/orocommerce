<?php

namespace Oro\Bundle\CustomerBundle\Migrations\Schema\v1_8;

use Doctrine\DBAL\Schema\Schema;
use Oro\Bundle\ConfigBundle\Migration\RenameConfigSectionQuery;
use Oro\Bundle\EntityExtendBundle\Extend\RelationType;
use Oro\Bundle\FrontendBundle\Migration\UpdateExtendRelationQuery;
use Oro\Bundle\InstallerBundle\Migration\UpdateTableFieldQuery;
use Oro\Bundle\MigrationBundle\Migration\Extension\RenameExtension;
use Oro\Bundle\MigrationBundle\Migration\Extension\RenameExtensionAwareInterface;
use Oro\Bundle\MigrationBundle\Migration\Migration;
use Oro\Bundle\MigrationBundle\Migration\MigrationConstraintTrait;
use Oro\Bundle\MigrationBundle\Migration\QueryBag;

class OroAccountBundle implements Migration, RenameExtensionAwareInterface
{
    use MigrationConstraintTrait;

    /**
     * @var RenameExtension
     */
    private $renameExtension;

    /**
     * {@inheritdoc}
     */
    public function up(Schema $schema, QueryBag $queries)
    {
        $this->dropVisibilityTables($schema, $queries);

        $this->renameActivityTables($schema, $queries);
        $this->renameCustomerActivityTables($schema, $queries);
        $this->updateAttachments($schema, $queries);
        $this->updateNotes($schema, $queries);

        $this->renameOldActivityTables($queries);
        $this->updateOldAttachments($queries);
        $this->updateOldNotes($queries);

        $this->updateTableField($queries);
        $queries->addPostQuery(new RenameConfigSectionQuery('oro_account', 'oro_customer'));
    }

    /**
     * @param Schema $schema
     * @param QueryBag $queries
     */
    private function renameActivityTables(Schema $schema, QueryBag $queries)
    {
        $extension = $this->renameExtension;

        // email to account user association
        $extension->renameTable($schema, $queries, 'oro_rel_26535370a6adb604aeb863', 'oro_rel_26535370a6adb604264ef1');
        $queries->addQuery(new UpdateExtendRelationQuery(
            'Oro\Bundle\EmailBundle\Entity\Email',
            'Oro\Bundle\CustomerBundle\Entity\AccountUser',
            'account_user_795f990e',
            'account_user_741cdecd',
            RelationType::MANY_TO_MANY
        ));
    }

    /**
     * @param Schema $schema
     * @param QueryBag $queries
     */
    private function renameCustomerActivityTables(Schema $schema, QueryBag $queries)
    {
        if ($schema->hasTable('oro_rel_c3990ba6b28b6f38e2d624')) {
            $relTable = $schema->getTable('oro_rel_c3990ba6b28b6f38e2d624');
            $relTable->removeForeignKey('FK_139D9F729B6B5FBA');
            $relTable->removeForeignKey('FK_139D9F7296EB1108');
            $relTable->dropIndex('IDX_139D9F729B6B5FBA');
            $relTable->dropIndex('IDX_139D9F7296EB1108');
            $this->renameExtension->renameTable(
                $schema,
                $queries,
                'oro_rel_c3990ba6b28b6f38e2d624',
                'oro_rel_c3990ba6b28b6f382b5af2'
            );
            $queries->addQuery(new UpdateExtendRelationQuery(
                'Oro\Bundle\ActivityListBundle\Entity\ActivityList',
                'Oro\Bundle\CustomerBundle\Entity\Account',
                'account_a8bedd11',
                'account_32ea2fb3',
                RelationType::MANY_TO_MANY
            ));
            $this->renameExtension->addForeignKeyConstraint(
                $schema,
                $queries,
                'oro_rel_c3990ba6b28b6f382b5af2',
                'oro_account',
                ['account_id'],
                ['id'],
                ['onDelete' => 'CASCADE']
            );
            $this->renameExtension->addForeignKeyConstraint(
                $schema,
                $queries,
                'oro_rel_c3990ba6b28b6f382b5af2',
                'oro_activity_list',
                ['activitylist_id'],
                ['id'],
                ['onDelete' => 'CASCADE']
            );
            $this->renameExtension->addIndex(
                $schema,
                $queries,
                'oro_rel_c3990ba6b28b6f382b5af2',
                ['account_id']
            );
            $this->renameExtension->addIndex(
                $schema,
                $queries,
                'oro_rel_c3990ba6b28b6f382b5af2',
                ['activitylist_id']
            );
        }
    }

    /**
     * @param QueryBag $queries
     */
    private function renameOldActivityTables(QueryBag $queries)
    {
        // email to account user association
        $queries->addQuery(new UpdateExtendRelationQuery(
            'Oro\Bundle\EmailBundle\Entity\Email',
            'Oro\Bundle\CustomerBundle\Entity\AccountUser',
            'account_user_489123cf',
            'account_user_741cdecd',
            RelationType::MANY_TO_MANY
        ));
    }

    /**
     * @param Schema $schema
     * @param QueryBag $queries
     */
    private function updateAttachments(Schema $schema, QueryBag $queries)
    {
        $extension = $this->renameExtension;
        $attachments = $schema->getTable('oro_attachment');

        $attachments->removeForeignKey('FK_FA0FE081140E2435');
        if ($attachments->hasIndex('IDX_FA0FE081140E2435')) {
            $attachments->dropIndex('IDX_FA0FE081140E2435');
        }
        $extension->renameColumn(
            $schema,
            $queries,
            $attachments,
            'account_8d93c122_id',
            'account_8d1f63b9_id'
        );
        $extension->addForeignKeyConstraint(
            $schema,
            $queries,
            'oro_attachment',
            'oro_account',
            ['account_8d1f63b9_id'],
            ['id'],
            ['onDelete' => 'SET NULL']
        );
        $queries->addQuery(new UpdateExtendRelationQuery(
            'Oro\Bundle\AttachmentBundle\Entity\Attachment',
            'Oro\Bundle\CustomerBundle\Entity\Account',
            'account_8d93c122',
            'account_8d1f63b9',
            RelationType::MANY_TO_ONE
        ));

        $attachments->removeForeignKey('FK_FA0FE08135CF6547');
        $extension->renameColumn(
            $schema,
            $queries,
            $attachments,
            'account_user_7e92c4f1_id',
            'account_user_5919fc1d_id'
        );
        $extension->addForeignKeyConstraint(
            $schema,
            $queries,
            'oro_attachment',
            'oro_account_user',
            ['account_user_5919fc1d_id'],
            ['id'],
            ['onDelete' => 'SET NULL']
        );
        $queries->addQuery(new UpdateExtendRelationQuery(
            'Oro\Bundle\AttachmentBundle\Entity\Attachment',
            'Oro\Bundle\CustomerBundle\Entity\AccountUser',
            'account_user_7e92c4f1',
            'account_user_5919fc1d',
            RelationType::MANY_TO_ONE
        ));
    }

    /**
     * @param QueryBag $queries
     */
    private function updateOldAttachments(QueryBag $queries)
    {
        $queries->addQuery(new UpdateExtendRelationQuery(
            'Oro\Bundle\AttachmentBundle\Entity\Attachment',
            'Oro\Bundle\CustomerBundle\Entity\Account',
            'account_557018f',
            'account_8d1f63b9',
            RelationType::MANY_TO_ONE
        ));

        $queries->addQuery(new UpdateExtendRelationQuery(
            'Oro\Bundle\AttachmentBundle\Entity\Attachment',
            'Oro\Bundle\CustomerBundle\Entity\AccountUser',
            'account_user_1cc98a31',
            'account_user_5919fc1d',
            RelationType::MANY_TO_ONE
        ));
    }

    /**
     * @param Schema $schema
     * @param QueryBag $queries
     *
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     */
    private function updateNotes(Schema $schema, QueryBag $queries)
    {
        $extension = $this->renameExtension;
        $notes = $schema->getTable('oro_note');

        $notes->removeForeignKey('FK_BA066CE135CF6547');
        $extension->renameColumn(
            $schema,
            $queries,
            $notes,
            'account_user_7e92c4f1_id',
            'account_user_5919fc1d_id'
        );
        $extension->addForeignKeyConstraint(
            $schema,
            $queries,
            'oro_note',
            'oro_account_user',
            ['account_user_5919fc1d_id'],
            ['id'],
            ['onDelete' => 'SET NULL']
        );
        $queries->addQuery(new UpdateExtendRelationQuery(
            'Oro\Bundle\NoteBundle\Entity\Note',
            'Oro\Bundle\CustomerBundle\Entity\AccountUser',
            'account_user_7e92c4f1',
            'account_user_5919fc1d',
            RelationType::MANY_TO_ONE
        ));

        $notes->removeForeignKey('FK_BA066CE17017C4');
        $extension->renameColumn(
            $schema,
            $queries,
            $notes,
            'account_user_role_abeddea9_id',
            'account_user_role_604160ea_id'
        );
        $extension->addForeignKeyConstraint(
            $schema,
            $queries,
            'oro_note',
            'oro_account_user_role',
            ['account_user_role_604160ea_id'],
            ['id'],
            ['onDelete' => 'SET NULL']
        );
        $queries->addQuery(new UpdateExtendRelationQuery(
            'Oro\Bundle\NoteBundle\Entity\Note',
            'Oro\Bundle\CustomerBundle\Entity\AccountUserRole',
            'account_user_role_abeddea9',
            'account_user_role_604160ea',
            RelationType::MANY_TO_ONE
        ));

        if ($notes->hasForeignKey('FK_BA066CE1140E2435')) {
            $notes->removeForeignKey('FK_BA066CE1140E2435');
        } elseif ($notes->hasForeignKey('fk_oro_note_account_8d93c122_id')) {
            $notes->removeForeignKey('fk_oro_note_account_8d93c122_id');
        }
        if ($notes->hasIndex('IDX_BA066CE1140E2435')) {
            $notes->dropIndex('IDX_BA066CE1140E2435');
        }
        $extension->renameColumn(
            $schema,
            $queries,
            $notes,
            'account_8d93c122_id',
            'account_8d1f63b9_id'
        );
        $extension->addForeignKeyConstraint(
            $schema,
            $queries,
            'oro_note',
            'oro_account',
            ['account_8d1f63b9_id'],
            ['id'],
            ['onDelete' => 'SET NULL']
        );

        $queries->addQuery(new UpdateExtendRelationQuery(
            'Oro\Bundle\NoteBundle\Entity\Note',
            'Oro\Bundle\CustomerBundle\Entity\Account',
            'account_8d93c122',
            'account_8d1f63b9',
            RelationType::MANY_TO_ONE
        ));

        $notes->removeForeignKey('FK_BA066CE1254D1CEE');
        $extension->renameColumn(
            $schema,
            $queries,
            $notes,
            'account_group_a8897e69_id',
            'account_group_1125b02_id'
        );
        $extension->addForeignKeyConstraint(
            $schema,
            $queries,
            'oro_note',
            'oro_account_group',
            ['account_group_1125b02_id'],
            ['id'],
            ['onDelete' => 'SET NULL']
        );
        $queries->addQuery(new UpdateExtendRelationQuery(
            'Oro\Bundle\NoteBundle\Entity\Note',
            'Oro\Bundle\CustomerBundle\Entity\AccountGroup',
            'account_group_a8897e69',
            'account_group_1125b02',
            RelationType::MANY_TO_ONE
        ));
    }

    /**
     * @param QueryBag $queries
     */
    private function updateOldNotes(QueryBag $queries)
    {
        $queries->addQuery(new UpdateExtendRelationQuery(
            'Oro\Bundle\NoteBundle\Entity\Note',
            'Oro\Bundle\CustomerBundle\Entity\AccountUser',
            'account_user_1cc98a31',
            'account_user_5919fc1d',
            RelationType::MANY_TO_ONE
        ));

        $queries->addQuery(new UpdateExtendRelationQuery(
            'Oro\Bundle\NoteBundle\Entity\Note',
            'Oro\Bundle\CustomerBundle\Entity\AccountUserRole',
            'account_user_role_5d57148e',
            'account_user_role_604160ea',
            RelationType::MANY_TO_ONE
        ));

        $queries->addQuery(new UpdateExtendRelationQuery(
            'Oro\Bundle\NoteBundle\Entity\Note',
            'Oro\Bundle\CustomerBundle\Entity\Account',
            'account_557018f',
            'account_8d1f63b9',
            RelationType::MANY_TO_ONE
        ));

        $queries->addQuery(new UpdateExtendRelationQuery(
            'Oro\Bundle\NoteBundle\Entity\Note',
            'Oro\Bundle\CustomerBundle\Entity\AccountGroup',
            'account_group_338fe797',
            'account_group_1125b02',
            RelationType::MANY_TO_ONE
        ));
    }

    /**
     * @param QueryBag $queries
     */
    private function updateTableField(QueryBag $queries)
    {
        $queries->addQuery(new UpdateTableFieldQuery(
            'oro_migrations_data',
            'class_name',
            'AccountBundle',
            'CustomerBundle'
        ));
        $queries->addQuery(new UpdateTableFieldQuery(
            'oro_email_template',
            'entityname',
            'AccountBundle',
            'CustomerBundle'
        ));

        $queries->addQuery(new UpdateTableFieldQuery(
            'oro_email_template',
            'content',
            'oro_account_frontend_account_user_confirmation',
            'oro_customer_frontend_account_user_confirmation'
        ));

        $queries->addQuery(new UpdateTableFieldQuery(
            'oro_email_template',
            'content',
            'oro_account_account_user_security_login',
            'oro_customer_account_user_security_login'
        ));

        $queries->addQuery(new UpdateTableFieldQuery(
            'oro_email_template',
            'content',
            'oro_account_frontend_account_user_password_reset',
            'oro_customer_frontend_account_user_password_reset'
        ));
    }

    /**
     * {@inheritdoc}
     */
    public function setRenameExtension(RenameExtension $renameExtension)
    {
        $this->renameExtension = $renameExtension;
    }

    /**
     * @param Schema $schema
     * @param QueryBag $queries
     */
    private function dropVisibilityTables(Schema $schema, QueryBag $queries)
    {
        $schema->dropTable('oro_category_visibility');
        $schema->dropTable('oro_acc_category_visibility');
        $schema->dropTable('oro_acc_grp_ctgr_visibility');
        $schema->dropTable('oro_product_visibility');
        $schema->dropTable('oro_acc_product_visibility');
        $schema->dropTable('oro_acc_grp_prod_visibility');

        $schema->dropTable('oro_ctgr_vsb_resolv');
        $schema->dropTable('oro_acc_ctgr_vsb_resolv');
        $schema->dropTable('oro_acc_grp_ctgr_vsb_resolv');
        $schema->dropTable('oro_prod_vsb_resolv');
        $schema->dropTable('oro_acc_prod_vsb_resolv');
        $schema->dropTable('oro_acc_grp_prod_vsb_resolv');

        $queries->addQuery(new RemoveVisibilityFromEntityConfigQuery());
    }
}
