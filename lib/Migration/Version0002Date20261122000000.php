<?php

declare(strict_types=1);

namespace OCA\FolderVideoPlayer\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version0002Date20261122000000 extends SimpleMigrationStep
{
    /**
     * @param Closure(): ISchemaWrapper $schemaClosure
     * @param array<string, mixed> $options
     */
    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper
    {
        /** @var ISchemaWrapper $schema */
        $schema = $schemaClosure();

        if (!$schema->hasTable(Version0001Date20261121000000::TABLE_PREFERENCES)) {
            return $schema;
        }

        $table = $schema->getTable(Version0001Date20261121000000::TABLE_PREFERENCES);

        if (!$table->hasColumn('last_root_id')) {
            $output->info('Adding column last_root_id to ' . Version0001Date20261121000000::TABLE_PREFERENCES);
            $table->addColumn('last_root_id', Types::STRING, [
                'notnull' => false,
                'length' => 64,
            ]);
        }

        if (!$table->hasColumn('last_folder_path')) {
            $output->info('Adding column last_folder_path to ' . Version0001Date20261121000000::TABLE_PREFERENCES);
            $table->addColumn('last_folder_path', Types::STRING, [
                'notnull' => false,
                'length' => 1024,
            ]);
        }

        if (!$table->hasColumn('subtitle_scale')) {
            $output->info('Adding column subtitle_scale to ' . Version0001Date20261121000000::TABLE_PREFERENCES);
            $table->addColumn('subtitle_scale', Types::FLOAT, [
                'notnull' => true,
                'default' => 1.0,
            ]);
        }

        return $schema;
    }
}
