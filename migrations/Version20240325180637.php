<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20240325180637 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create event store table';
    }

    public function up(Schema $schema): void
    {
        $table = $schema->createTable('event_store');
        $table->addColumn('id', 'integer', ['autoincrement' => true]);
        $table->addColumn('stream', 'string', ['length' => 128, 'notnull' => false]);
        $table->addColumn('aggregate_id', 'string', ['length' => 36, 'notnull' => true]);
        $table->addColumn('version', 'integer', ['notnull' => true]);
        $table->addColumn('event', 'string', ['length' => 255, 'notnull' => true]);
        $table->addColumn('payload', 'text', ['notnull' => true]);
        $table->addColumn('created_at', 'string', ['length' => 128, 'notnull' => true]);
        $table->setPrimaryKey(['id']);
    }

    public function down(Schema $schema): void
    {
        $schema->dropTable('event_store');
    }
}
