<?php

declare(strict_types=1);

namespace OCA\FolderVideoPlayer\Tests\Unit\Migration;

use OCA\FolderVideoPlayer\Migration\Version0001Date20261121000000;
use OCA\FolderVideoPlayer\Migration\Version0002Date20261122000000;
use OCP\DB\ISchemaWrapper;
use OCP\DB\ITable;
use OCP\Migration\IOutput;
use PHPUnit\Framework\TestCase;

class Version0001MigrationTest extends TestCase
{
    public function testCreatesAllFourDedicatedTablesWithPathHashIndexes(): void
    {
        $createdTables = [];
        $tableColumns = [];
        $uniqueIndexes = [];

        $schema = $this->createMock(ISchemaWrapper::class);
        $schema->method('hasTable')->willReturn(false);
        $schema->method('createTable')
            ->willReturnCallback(function (string $tableName) use (&$createdTables, &$tableColumns, &$uniqueIndexes): ITable {
                $createdTables[] = $tableName;
                $tableColumns[$tableName] = [];
                $uniqueIndexes[$tableName] = [];

                $table = $this->createMock(ITable::class);
                $table->method('addColumn')
                    ->willReturnCallback(static function (string $colName) use ($tableName, &$tableColumns): void {
                        $tableColumns[$tableName][] = $colName;
                    });
                $table->method('addUniqueIndex')
                    ->willReturnCallback(static function (array $cols, ?string $idxName) use ($tableName, &$uniqueIndexes): void {
                        $uniqueIndexes[$tableName][$idxName ?? ''] = $cols;
                    });

                return $table;
            });

        $output = $this->createMock(IOutput::class);
        $migration = new Version0001Date20261121000000();

        $result = $migration->changeSchema($output, static fn (): ISchemaWrapper => $schema, []);
        $this->assertSame($schema, $result);
        $this->assertSame([
            'ncvp_folders',
            'ncvp_playback_progress',
            'ncvp_user_preferences',
            'ncvp_playback_sessions',
        ], $createdTables);

        foreach ($createdTables as $tableName) {
            $this->assertLessThanOrEqual(27, strlen($tableName));
        }

        $this->assertContains('path_hash', $tableColumns['ncvp_folders']);
        $this->assertContains('path_hash', $tableColumns['ncvp_playback_progress']);
        $this->assertSame(['scope', 'user_id', 'path_hash'], $uniqueIndexes['ncvp_folders']['ncvp_fld_scope_usr_path_uq']);
        $this->assertSame(['user_id', 'root_key', 'path_hash'], $uniqueIndexes['ncvp_playback_progress']['ncvp_prg_usr_root_path_uq']);
        $this->assertSame(['user_id'], $uniqueIndexes['ncvp_user_preferences']['ncvp_prf_usr_uq']);
        $this->assertSame(['session_token'], $uniqueIndexes['ncvp_playback_sessions']['ncvp_ses_token_uq']);
    }

    public function testSkipsCreationWhenTablesAlreadyExist(): void
    {
        $schema = $this->createMock(ISchemaWrapper::class);
        $schema->method('hasTable')->willReturn(true);
        $schema->expects($this->never())->method('createTable');

        $output = $this->createMock(IOutput::class);
        $migration = new Version0001Date20261121000000();
        $migration->changeSchema($output, static fn (): ISchemaWrapper => $schema, []);
    }

    public function testVersion0002AddsLastFolderAndSubtitleScaleColumns(): void
    {
        $addedColumns = [];
        $table = $this->createMock(ITable::class);
        $table->method('hasColumn')->willReturn(false);
        $table->method('addColumn')
            ->willReturnCallback(static function (string $colName) use (&$addedColumns): void {
                $addedColumns[] = $colName;
            });

        $schema = $this->createMock(ISchemaWrapper::class);
        $schema->method('hasTable')->with('ncvp_user_preferences')->willReturn(true);
        $schema->method('getTable')->with('ncvp_user_preferences')->willReturn($table);

        $output = $this->createMock(IOutput::class);
        $migration = new Version0002Date20261122000000();
        $migration->changeSchema($output, static fn (): ISchemaWrapper => $schema, []);

        $this->assertSame(['last_root_id', 'last_folder_path', 'subtitle_scale'], $addedColumns);
    }
}
