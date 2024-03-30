<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20240325172204 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create the initial app tables';
    }

    public function up(Schema $schema): void
    {
        $table = $schema->createTable('games');
        $table->addColumn('id', 'integer', ['autoincrement' => true]);
        $table->addColumn('player_black_id', 'integer');
        $table->addColumn('player_white_id', 'integer');
        $table->addColumn('status', 'string');

        $table = $schema->createTable('moves');
        $table->addColumn('id', 'integer', ['autoincrement' => true]);
        $table->addColumn('game_id', 'integer');
        $table->addColumn('player_id', 'integer');
        $table->addColumn('from', 'string');
        $table->addColumn('to', 'string');
        $table->addColumn('piece_type', 'string');

        $table = $schema->createTable('players');
        $table->addColumn('id', 'integer', ['autoincrement' => true]);
        $table->addColumn('game_id', 'integer');
        $table->addColumn('name', 'string');
        $table->addColumn('side', 'string');

        $table = $schema->createTable('users');
        $table->addColumn('id', 'integer', ['autoincrement' => true]);
        $table->addColumn('email', 'string');
        $table->addColumn('password', 'string');
        $table->addColumn('username', 'string');
        $table->addColumn('created_at', 'datetime', ['default' => 'CURRENT_TIMESTAMP']);
        $table->addUniqueIndex(['username'], 'users_username_index');
        $table->addUniqueIndex(['email'], 'users_email_index');
    }

    public function down(Schema $schema): void
    {
        $schema->dropTable('games');
        $schema->dropTable('moves');
        $schema->dropTable('players');
        $schema->dropTable('users');
    }
}
