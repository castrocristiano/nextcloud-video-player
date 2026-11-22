<?php

declare(strict_types=1);

namespace OCA\FolderVideoPlayer\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version0001Date20261121000000 extends SimpleMigrationStep
{
    public const TABLE_FOLDERS = 'ncvp_folders';
    public const TABLE_PROGRESS = 'ncvp_playback_progress';
    public const TABLE_PREFERENCES = 'ncvp_user_preferences';
    public const TABLE_SESSIONS = 'ncvp_playback_sessions';

    /**
     * @param Closure(): ISchemaWrapper $schemaClosure
     * @param array<string, mixed> $options
     */
    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper
    {
        /** @var ISchemaWrapper $schema */
        $schema = $schemaClosure();

        $this->createFoldersTable($schema, $output);
        $this->createProgressTable($schema, $output);
        $this->createPreferencesTable($schema, $output);
        $this->createSessionsTable($schema, $output);

        return $schema;
    }

    private function createFoldersTable(ISchemaWrapper $schema, IOutput $output): void
    {
        if ($schema->hasTable(self::TABLE_FOLDERS)) {
            return;
        }

        $output->info('Creating table ' . self::TABLE_FOLDERS);
        $table = $schema->createTable(self::TABLE_FOLDERS);
        $table->addColumn('id', Types::BIGINT, [
            'autoincrement' => true,
            'notnull' => true,
            'unsigned' => true,
        ]);
        $table->addColumn('root_key', Types::STRING, [
            'notnull' => true,
            'length' => 64,
        ]);
        $table->addColumn('scope', Types::STRING, [
            'notnull' => true,
            'length' => 16,
            'default' => 'user',
        ]);
        $table->addColumn('user_id', Types::STRING, [
            'notnull' => true,
            'length' => 64,
            'default' => '',
        ]);
        $table->addColumn('folder_path', Types::STRING, [
            'notnull' => true,
            'length' => 1024,
        ]);
        $table->addColumn('path_hash', Types::STRING, [
            'notnull' => true,
            'length' => 64,
        ]);
        $table->addColumn('label', Types::STRING, [
            'notnull' => true,
            'length' => 255,
        ]);
        $table->addColumn('created_by', Types::STRING, [
            'notnull' => true,
            'length' => 64,
            'default' => '',
        ]);
        $table->addColumn('created_at', Types::BIGINT, [
            'notnull' => true,
            'default' => 0,
        ]);
        $table->addColumn('updated_at', Types::BIGINT, [
            'notnull' => true,
            'default' => 0,
        ]);

        $table->setPrimaryKey(['id']);
        $table->addUniqueIndex(['root_key'], 'ncvp_fld_root_key_uq');
        $table->addUniqueIndex(['scope', 'user_id', 'path_hash'], 'ncvp_fld_scope_usr_path_uq');
        $table->addIndex(['scope', 'user_id'], 'ncvp_fld_scope_usr_idx');
    }

    private function createProgressTable(ISchemaWrapper $schema, IOutput $output): void
    {
        if ($schema->hasTable(self::TABLE_PROGRESS)) {
            return;
        }

        $output->info('Creating table ' . self::TABLE_PROGRESS);
        $table = $schema->createTable(self::TABLE_PROGRESS);
        $table->addColumn('id', Types::BIGINT, [
            'autoincrement' => true,
            'notnull' => true,
            'unsigned' => true,
        ]);
        $table->addColumn('user_id', Types::STRING, [
            'notnull' => true,
            'length' => 64,
        ]);
        $table->addColumn('file_id', Types::BIGINT, [
            'notnull' => false,
        ]);
        $table->addColumn('root_key', Types::STRING, [
            'notnull' => true,
            'length' => 64,
        ]);
        $table->addColumn('relative_path', Types::STRING, [
            'notnull' => true,
            'length' => 1024,
        ]);
        $table->addColumn('path_hash', Types::STRING, [
            'notnull' => true,
            'length' => 64,
        ]);
        $table->addColumn('position_seconds', Types::FLOAT, [
            'notnull' => true,
            'default' => 0,
        ]);
        $table->addColumn('duration_seconds', Types::FLOAT, [
            'notnull' => true,
            'default' => 0,
        ]);
        $table->addColumn('progress_ratio', Types::FLOAT, [
            'notnull' => true,
            'default' => 0,
        ]);
        $table->addColumn('completed', Types::BOOLEAN, [
            'notnull' => true,
            'default' => false,
        ]);
        $table->addColumn('last_session_token', Types::STRING, [
            'notnull' => false,
            'length' => 64,
        ]);
        $table->addColumn('updated_at', Types::BIGINT, [
            'notnull' => true,
            'default' => 0,
        ]);

        $table->setPrimaryKey(['id']);
        $table->addUniqueIndex(['user_id', 'root_key', 'path_hash'], 'ncvp_prg_usr_root_path_uq');
        $table->addIndex(['user_id', 'updated_at'], 'ncvp_prg_usr_upd_idx');
    }

    private function createPreferencesTable(ISchemaWrapper $schema, IOutput $output): void
    {
        if ($schema->hasTable(self::TABLE_PREFERENCES)) {
            return;
        }

        $output->info('Creating table ' . self::TABLE_PREFERENCES);
        $table = $schema->createTable(self::TABLE_PREFERENCES);
        $table->addColumn('id', Types::BIGINT, [
            'autoincrement' => true,
            'notnull' => true,
            'unsigned' => true,
        ]);
        $table->addColumn('user_id', Types::STRING, [
            'notnull' => true,
            'length' => 64,
        ]);
        $table->addColumn('playback_rate', Types::FLOAT, [
            'notnull' => true,
            'default' => 1.0,
        ]);
        $table->addColumn('autoplay_next', Types::BOOLEAN, [
            'notnull' => true,
            'default' => true,
        ]);
        $table->addColumn('sort_by', Types::STRING, [
            'notnull' => true,
            'length' => 32,
            'default' => 'name_asc',
        ]);
        $table->addColumn('volume', Types::FLOAT, [
            'notnull' => true,
            'default' => 1.0,
        ]);
        $table->addColumn('muted', Types::BOOLEAN, [
            'notnull' => true,
            'default' => false,
        ]);
        $table->addColumn('preferred_subtitle', Types::STRING, [
            'notnull' => false,
            'length' => 32,
        ]);
        $table->addColumn('updated_at', Types::BIGINT, [
            'notnull' => true,
            'default' => 0,
        ]);

        $table->setPrimaryKey(['id']);
        $table->addUniqueIndex(['user_id'], 'ncvp_prf_usr_uq');
    }

    private function createSessionsTable(ISchemaWrapper $schema, IOutput $output): void
    {
        if ($schema->hasTable(self::TABLE_SESSIONS)) {
            return;
        }

        $output->info('Creating table ' . self::TABLE_SESSIONS);
        $table = $schema->createTable(self::TABLE_SESSIONS);
        $table->addColumn('id', Types::BIGINT, [
            'autoincrement' => true,
            'notnull' => true,
            'unsigned' => true,
        ]);
        $table->addColumn('session_token', Types::STRING, [
            'notnull' => true,
            'length' => 64,
        ]);
        $table->addColumn('user_id', Types::STRING, [
            'notnull' => true,
            'length' => 64,
        ]);
        $table->addColumn('root_key', Types::STRING, [
            'notnull' => true,
            'length' => 64,
        ]);
        $table->addColumn('relative_path', Types::STRING, [
            'notnull' => true,
            'length' => 1024,
        ]);
        $table->addColumn('stream_mode', Types::STRING, [
            'notnull' => true,
            'length' => 16,
            'default' => 'direct',
        ]);
        $table->addColumn('client_agent', Types::STRING, [
            'notnull' => false,
            'length' => 255,
        ]);
        $table->addColumn('status', Types::STRING, [
            'notnull' => true,
            'length' => 16,
            'default' => 'active',
        ]);
        $table->addColumn('started_at', Types::BIGINT, [
            'notnull' => true,
            'default' => 0,
        ]);
        $table->addColumn('last_heartbeat_at', Types::BIGINT, [
            'notnull' => true,
            'default' => 0,
        ]);
        $table->addColumn('expires_at', Types::BIGINT, [
            'notnull' => true,
            'default' => 0,
        ]);
        $table->addColumn('ended_at', Types::BIGINT, [
            'notnull' => false,
        ]);

        $table->setPrimaryKey(['id']);
        $table->addUniqueIndex(['session_token'], 'ncvp_ses_token_uq');
        $table->addIndex(['user_id', 'status', 'expires_at'], 'ncvp_ses_usr_st_exp_idx');
        $table->addIndex(['status', 'expires_at'], 'ncvp_ses_st_exp_idx');
    }
}
