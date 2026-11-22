<?php

declare(strict_types=1);

namespace OCA\NextcloudVideoPlayer\Tests\Unit\Db;

use OCA\NextcloudVideoPlayer\Db\FolderEntity;
use OCA\NextcloudVideoPlayer\Db\FolderMapper;
use OCA\NextcloudVideoPlayer\Db\PlaybackProgressEntity;
use OCA\NextcloudVideoPlayer\Db\PlaybackProgressMapper;
use OCA\NextcloudVideoPlayer\Db\PlaybackSessionEntity;
use OCA\NextcloudVideoPlayer\Db\PlaybackSessionMapper;
use OCA\NextcloudVideoPlayer\Db\UserPreferenceEntity;
use OCA\NextcloudVideoPlayer\Db\UserPreferenceMapper;
use OCP\DB\QueryBuilder\IExpressionBuilder;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\DB\QueryBuilder\IResult;
use OCP\IDBConnection;
use PHPUnit\Framework\TestCase;

class MappersTest extends TestCase
{
    /**
     * @param list<array<string, mixed>> $rows
     */
    private function createMockDbWithRows(array $rows, int $affectedRows = 1): IDBConnection
    {
        $result = $this->createMock(IResult::class);
        $result->method('fetchAll')->willReturn($rows);
        $result->method('closeCursor')->willReturn(true);

        $expr = $this->createMock(IExpressionBuilder::class);
        $expr->method('eq')->willReturn('col = :val');
        $expr->method('in')->willReturn('col IN (:vals)');
        $expr->method('gte')->willReturn('col >= :val');
        $expr->method('lt')->willReturn('col < :val');

        $qb = $this->createMock(IQueryBuilder::class);
        $qb->method('select')->willReturnSelf();
        $qb->method('from')->willReturnSelf();
        $qb->method('where')->willReturnSelf();
        $qb->method('andWhere')->willReturnSelf();
        $qb->method('orderBy')->willReturnSelf();
        $qb->method('insert')->willReturnSelf();
        $qb->method('values')->willReturnSelf();
        $qb->method('update')->willReturnSelf();
        $qb->method('set')->willReturnSelf();
        $qb->method('delete')->willReturnSelf();
        $qb->method('expr')->willReturn($expr);
        $qb->method('createNamedParameter')->willReturn(':p');
        $qb->method('executeQuery')->willReturn($result);
        $qb->method('executeStatement')->willReturn($affectedRows);
        $qb->method('getLastInsertId')->willReturn(42);

        $db = $this->createMock(IDBConnection::class);
        $db->method('getQueryBuilder')->willReturn($qb);
        return $db;
    }

    public function testFolderEntityAndMapperQueries(): void
    {
        $row = [
            'id' => 7,
            'root_key' => 'rk_movies',
            'scope' => 'global',
            'user_id' => '',
            'folder_path' => '/Movies',
            'path_hash' => hash('sha256', '/Movies'),
            'label' => 'Movies',
            'created_by' => 'admin',
            'created_at' => 1700000000,
            'updated_at' => 1700000000,
        ];
        $mapper = new FolderMapper($this->createMockDbWithRows([$row]));

        $globals = $mapper->findGlobalFolders();
        $this->assertCount(1, $globals);
        $this->assertSame('rk_movies', $globals[0]->getRootKey());
        $this->assertSame('/Movies', $globals[0]->getFolderPath());

        $userFolders = $mapper->findUserFolders('alice');
        $this->assertCount(1, $userFolders);

        $found = $mapper->findByRootKey('rk_movies');
        $this->assertInstanceOf(FolderEntity::class, $found);
        $this->assertSame('Movies', $found->getLabel());

        $byHash = $mapper->findByScopeAndPathHash('global', '', $row['path_hash']);
        $this->assertNotNull($byHash);
        $this->assertSame(1, $mapper->deleteGlobalFolders());

        $emptyMapper = new FolderMapper($this->createMockDbWithRows([]));
        $this->assertNull($emptyMapper->findByRootKey('missing'));
        $this->assertNull($emptyMapper->findByScopeAndPathHash('user', 'alice', 'missing'));
    }

    public function testPlaybackProgressEntityAndMapperQueries(): void
    {
        $row = [
            'id' => 11,
            'user_id' => 'alice',
            'file_id' => 99,
            'root_key' => 'rk_movies',
            'relative_path' => '/Action/Matrix.mp4',
            'path_hash' => hash('sha256', '/Action/Matrix.mp4'),
            'position_seconds' => 300.5,
            'duration_seconds' => 1200.0,
            'progress_ratio' => 0.25,
            'completed' => false,
            'last_session_token' => 'tok_123',
            'updated_at' => 1700000100,
        ];
        $mapper = new PlaybackProgressMapper($this->createMockDbWithRows([$row]));

        $progress = $mapper->findByUserAndVideo('alice', 'rk_movies', $row['path_hash']);
        $this->assertInstanceOf(PlaybackProgressEntity::class, $progress);
        $this->assertSame(300.5, $progress->getPositionSeconds());
        $this->assertFalse($progress->getCompleted());
        $this->assertCount(1, $mapper->findAllForUserAndRoot('alice', 'rk_movies'));
        $this->assertCount(1, $mapper->findAllForUser('alice'));

        $emptyMapper = new PlaybackProgressMapper($this->createMockDbWithRows([]));
        $this->assertNull($emptyMapper->findByUserAndVideo('alice', 'rk_movies', 'none'));
    }

    public function testUserPreferenceEntityAndMapperQueries(): void
    {
        $row = [
            'id' => 3,
            'user_id' => 'alice',
            'playback_rate' => 1.5,
            'autoplay_next' => true,
            'sort_by' => 'date_desc',
            'volume' => 0.8,
            'muted' => false,
            'preferred_subtitle' => 'pt-BR',
            'updated_at' => 1700000200,
        ];
        $mapper = new UserPreferenceMapper($this->createMockDbWithRows([$row]));
        $pref = $mapper->findByUserId('alice');
        $this->assertInstanceOf(UserPreferenceEntity::class, $pref);
        $this->assertSame(1.5, $pref->getPlaybackRate());
        $this->assertSame('pt-BR', $pref->getPreferredSubtitle());

        $emptyMapper = new UserPreferenceMapper($this->createMockDbWithRows([]));
        $this->assertNull($emptyMapper->findByUserId('bob'));
    }

    public function testPlaybackSessionEntityAndMapperQueries(): void
    {
        $row = [
            'id' => 21,
            'session_token' => 'tok_abc',
            'user_id' => 'alice',
            'root_key' => 'rk_movies',
            'relative_path' => '/Matrix.mp4',
            'stream_mode' => 'direct',
            'client_agent' => 'Mozilla/5.0',
            'status' => 'active',
            'started_at' => 1700000000,
            'last_heartbeat_at' => 1700000050,
            'expires_at' => 1700000230,
            'ended_at' => null,
        ];
        $mapper = new PlaybackSessionMapper($this->createMockDbWithRows([$row], 2));

        $session = $mapper->findByToken('tok_abc');
        $this->assertInstanceOf(PlaybackSessionEntity::class, $session);
        $this->assertSame('alice', $session->getUserId());
        $this->assertCount(1, $mapper->findActiveForUser('alice', 1700000100));
        $this->assertSame(2, $mapper->expireStaleSessions(1700000500, 'alice'));
        $this->assertSame(2, $mapper->revokeAllForUser('alice', 1700000500));
        $this->assertSame(2, $mapper->purgeEndedBefore(1690000000));

        $emptyMapper = new PlaybackSessionMapper($this->createMockDbWithRows([]));
        $this->assertNull($emptyMapper->findByToken('missing'));
    }
}
