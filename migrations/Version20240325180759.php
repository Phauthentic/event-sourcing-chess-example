<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20240325180759 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create event store snapshots table';
    }

    public function up(Schema $schema): void
    {
        $table = $schema->createTable('event_store_snapshots');
        $table->addColumn('id', 'integer', ['autoincrement' => true]);
        $table->addColumn('aggregate_type', 'string', ['length' => 255, 'notnull' => true]);
        $table->addColumn('aggregate_id', 'string', ['length' => 36, 'notnull' => true]);
        $table->addColumn('aggregate_version', 'integer', ['notnull' => true]);
        $table->addColumn('aggregate_root', 'text', ['notnull' => true]);
        $table->addColumn('created_at', 'datetime', ['default' => 'CURRENT_TIMESTAMP']);
        $table->setPrimaryKey(['id']);
    }

    public function down(Schema $schema): void
    {
        $schema->dropTable('event_store_snapshots');
    }
}
