<?php

declare(strict_types=1);

namespace Tests\Data;

use Melodic\Data\DbContext;
use Melodic\Data\Migration\Migrator;
use Melodic\Data\Model;
use Melodic\Data\ModelBindingException;
use Melodic\Data\Paginator;
use PDO;
use PHPUnit\Framework\TestCase;

enum FeatureStatus: string
{
    case Active = 'active';
    case Closed = 'closed';
}

class FeatureRow extends Model
{
    public ?int $Id = null;
    public ?FeatureStatus $Status = null;
    public ?\DateTimeImmutable $CreatedAt = null;
    public ?bool $Flag = null;
    public ?int $Count = null;
}

class DataFeaturesTest extends TestCase
{
    private DbContext $db;
    private PDO $pdo;
    private string $dir;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->exec('CREATE TABLE rows (Id INTEGER PRIMARY KEY, Status TEXT, CreatedAt TEXT, Flag INTEGER)');
        $this->db = new DbContext($this->pdo);
        $this->dir = sys_get_temp_dir() . '/melodic_migrations_' . uniqid();
        mkdir($this->dir);
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->dir . '/*') ?: []);
        rmdir($this->dir);
    }

    public function testEnumAndDateTimeRoundTripThroughTheDatabase(): void
    {
        $model = FeatureRow::fromArray(['status' => 'active', 'createdAt' => '2026-01-02 03:04:05', 'flag' => false]);

        $params = $model->toUpdateArray();
        $this->assertSame(['Status' => 'active', 'CreatedAt' => '2026-01-02 03:04:05', 'Flag' => 0], $params);

        $this->db->command('INSERT INTO rows (Status, CreatedAt, Flag) VALUES (:Status, :CreatedAt, :Flag)', $params);

        $row = $this->db->queryFirst(FeatureRow::class, 'SELECT * FROM rows');
        $this->assertSame(FeatureStatus::Active, $row->Status);
        $this->assertSame('2026-01-02', $row->CreatedAt->format('Y-m-d'));
        $this->assertFalse($row->Flag);
    }

    public function testRawParamsAreNormalized(): void
    {
        $this->db->command('INSERT INTO rows (Status, CreatedAt, Flag) VALUES (:s, :c, :f)', [
            's' => FeatureStatus::Closed,
            'c' => new \DateTimeImmutable('2026-05-06 07:08:09'),
            'f' => false,
        ]);

        $raw = $this->pdo->query('SELECT Status, CreatedAt, Flag, typeof(Flag) AS t FROM rows')->fetch(PDO::FETCH_ASSOC);
        $this->assertSame('closed', $raw['Status']);
        $this->assertSame('2026-05-06 07:08:09', $raw['CreatedAt']);
        $this->assertSame('integer', $raw['t']);
        $this->assertSame(0, (int) $raw['Flag']);
    }

    public function testJsonRendersDateTimeAsIso8601(): void
    {
        $model = FeatureRow::fromArray(['status' => 'active', 'createdAt' => '2026-01-02T03:04:05+00:00']);
        $json = json_decode((string) json_encode($model), true);

        $this->assertSame('active', $json['status']);
        $this->assertSame('2026-01-02T03:04:05+00:00', $json['createdAt']);
    }

    public function testIntegerOverflowIsRejected(): void
    {
        $this->assertSame(7, FeatureRow::fromArray(['count' => '007'])->Count);
        $this->assertSame(-12, FeatureRow::fromArray(['count' => '-12'])->Count);

        $this->expectException(ModelBindingException::class);
        FeatureRow::fromArray(['count' => '99999999999999999999999']);
    }

    public function testCursorStreamsHydratedRows(): void
    {
        $this->pdo->exec("INSERT INTO rows (Status) VALUES ('active'), ('closed')");

        $statuses = [];
        foreach ($this->db->cursor(FeatureRow::class, 'SELECT * FROM rows ORDER BY Id') as $row) {
            $statuses[] = $row->Status;
        }

        $this->assertSame([FeatureStatus::Active, FeatureStatus::Closed], $statuses);
    }

    public function testPaginator(): void
    {
        for ($i = 0; $i < 7; $i++) {
            $this->pdo->exec("INSERT INTO rows (Status) VALUES ('active')");
        }
        $this->pdo->exec("INSERT INTO rows (Status) VALUES ('closed')");

        $page = Paginator::paginate(
            $this->db,
            FeatureRow::class,
            'SELECT * FROM rows WHERE Status = :s ORDER BY Id;',
            ['s' => 'active'],
            page: 2,
            perPage: 3,
        );

        $this->assertSame(7, $page->total);
        $this->assertSame(3, $page->pageCount());
        $this->assertCount(3, $page->items);
        $this->assertSame(4, $page->items[0]->Id);
        $this->assertTrue($page->hasMore());

        $this->assertSame([], Paginator::paginate($this->db, FeatureRow::class, 'SELECT * FROM rows ORDER BY Id', [], 99, 3)->items);
        $this->assertSame(100, Paginator::paginate($this->db, FeatureRow::class, 'SELECT * FROM rows ORDER BY Id', [], 1, 5000)->perPage);
        $this->assertSame(['items', 'total', 'page', 'perPage', 'pageCount'], array_keys($page->toArray()));
    }

    public function testMigratorAppliesInOrderAndRollsBack(): void
    {
        file_put_contents($this->dir . '/20260101000000_create_a.sql', "-- first\nCREATE TABLE a (Id INTEGER);\nINSERT INTO a (Id) VALUES (1);\n\n-- @down\nDROP TABLE a;\n");
        file_put_contents($this->dir . '/20260102000000_create_b.sql', "CREATE TABLE b (Id INTEGER);\n");

        $migrator = new Migrator($this->db, $this->dir);

        $this->assertSame(['20260101000000_create_a', '20260102000000_create_b'], $migrator->migrate());
        $this->assertSame([], $migrator->migrate());
        $this->assertSame(1, (int) $this->db->scalar('SELECT COUNT(*) FROM a'));

        $status = $migrator->status();
        $this->assertTrue($status[0]['applied']);
        $this->assertNotNull($status[1]['appliedAt']);

        // b has no @down section
        try {
            $migrator->rollback();
            $this->fail('Expected rollback without @down to fail');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('@down', $e->getMessage());
        }

        $this->db->command("DELETE FROM melodic_migrations WHERE name = '20260102000000_create_b'");
        $this->assertSame(['20260101000000_create_a'], $migrator->rollback());
        $this->assertFalse($migrator->status()[0]['applied']);
    }

    public function testMigratorReportsFailingMigrationByName(): void
    {
        file_put_contents($this->dir . '/20260101000000_bad.sql', "CREATE TABLE;\n");

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("Migration '20260101000000_bad' failed");
        (new Migrator($this->db, $this->dir))->migrate();
    }

    public function testMigratorRejectsUnsafeTableName(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new Migrator($this->db, $this->dir, 'x; DROP TABLE y');
    }
}
