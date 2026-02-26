<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260225190000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create chess game read model table';
    }

    public function up(Schema $schema): void
    {
        $table = $schema->createTable('chess_game_read_model');
        $table->addColumn('game_id', 'string', ['length' => 36, 'notnull' => true]);
        $table->addColumn('board', 'json', ['notnull' => true]);
        $table->addColumn('active_player', 'string', ['length' => 255, 'notnull' => true]);
        $table->addColumn('is_in_check', 'boolean', ['default' => false, 'notnull' => true]);
        $table->addColumn('draw_offered', 'boolean', ['default' => false, 'notnull' => true]);
        $table->addColumn('is_finished', 'boolean', ['default' => false, 'notnull' => true]);
        $table->addColumn('winner', 'string', ['length' => 50, 'notnull' => false]);
        $table->addColumn('player_one_name', 'string', ['length' => 255, 'notnull' => true]);
        $table->addColumn('player_two_name', 'string', ['length' => 255, 'notnull' => true]);
        $table->setPrimaryKey(['game_id']);
    }

    public function down(Schema $schema): void
    {
        $schema->dropTable('chess_game_read_model');
    }
}