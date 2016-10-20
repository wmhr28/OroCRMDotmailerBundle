<?php

namespace OroCRM\Bundle\DotmailerBundle\Migrations\Schema\v1_3;

use Doctrine\DBAL\Schema\Schema;

use Oro\Bundle\MigrationBundle\Migration\Migration;
use Oro\Bundle\MigrationBundle\Migration\QueryBag;
use Oro\Bundle\MigrationBundle\Migration\OrderedMigrationInterface;

class AddDotmailerDataField implements Migration, OrderedMigrationInterface
{
    /**
     * {@inheritdoc}
     */
    public function getOrder()
    {
        return 0;
    }

    /**
     * {@inheritdoc}
     */
    public function up(Schema $schema, QueryBag $queries)
    {
        self::createOroDotmailerDataFieldTable($schema);
    }

    /**
     * Create orocrm_dm_address_book table
     *
     * @param Schema $schema
     */
    public static function createOroDotmailerDataFieldTable(Schema $schema)
    {
        $table = $schema->createTable('orocrm_dm_data_field');
        $table->addColumn('id', 'integer', ['autoincrement' => true]);
        $table->addColumn('owner_id', 'integer', ['notnull' => false]);
        $table->addColumn('channel_id', 'integer', ['notnull' => false]);
        $table->addColumn('name', 'string', ['length' => 255]);
        $table->addColumn('is_synced', 'boolean', ['notnull' => false]);
        $table->addColumn('default_value', 'string', ['length' => 255, 'notnull' => false]);
        $table->addColumn('notes', 'text', ['notnull' => false]);
        $table->addColumn('created_at', 'datetime', ['comment' => '(DC2Type:datetime)']);
        $table->setPrimaryKey(['id']);
        $table->addIndex(['owner_id'], 'IDX_9A9DD33F7E3C61F9', []);
        $table->addIndex(['channel_id'], 'IDX_9A9DD33F72F5A1AA', []);
        $table->addUniqueIndex(['name', 'channel_id'], 'orocrm_dm_data_field_unq');

        $table->addForeignKeyConstraint(
            $schema->getTable('oro_organization'),
            ['owner_id'],
            ['id'],
            ['onUpdate' => null, 'onDelete' => 'SET NULL']
        );
        $table->addForeignKeyConstraint(
            $schema->getTable('oro_integration_channel'),
            ['channel_id'],
            ['id'],
            ['onUpdate' => null, 'onDelete' => 'SET NULL']
        );
    }
}
