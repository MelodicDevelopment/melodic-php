<?php

declare(strict_types=1);

namespace Tests\Data;

use Melodic\Data\DbContext;
use PDO;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class TestUser
{
    public int $id;
    public string $name;
    public string $email;
    public float $score;
    public bool $active;
    public ?string $bio;
}

class DbContextTest extends TestCase
{
    private DbContext $db;

    protected function setUp(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $pdo->exec('
            CREATE TABLE users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                email TEXT NOT NULL,
                score REAL NOT NULL DEFAULT 0.0,
                active INTEGER NOT NULL DEFAULT 1,
                bio TEXT DEFAULT NULL
            )
        ');

        $this->db = new DbContext($pdo);
    }

    #[Test]
    public function queryReturnsMultipleHydratedModels(): void
    {
        $this->insertUser('Alice', 'alice@example.com', 9.5, 1);
        $this->insertUser('Bob', 'bob@example.com', 7.2, 0);
        $this->insertUser('Charlie', 'charlie@example.com', 8.8, 1);

        $users = $this->db->query(TestUser::class, 'SELECT * FROM users ORDER BY id');

        $this->assertCount(3, $users);
        $this->assertContainsOnlyInstancesOf(TestUser::class, $users);

        $this->assertSame('Alice', $users[0]->name);
        $this->assertSame('Bob', $users[1]->name);
        $this->assertSame('Charlie', $users[2]->name);
    }

    #[Test]
    public function queryReturnsEmptyArrayWhenNoRows(): void
    {
        $users = $this->db->query(TestUser::class, 'SELECT * FROM users');

        $this->assertSame([], $users);
    }

    #[Test]
    public function queryWithParameterizedCondition(): void
    {
        $this->insertUser('Alice', 'alice@example.com', 9.5, 1);
        $this->insertUser('Bob', 'bob@example.com', 7.2, 0);

        $users = $this->db->query(
            TestUser::class,
            'SELECT * FROM users WHERE active = :active',
            ['active' => 1],
        );

        $this->assertCount(1, $users);
        $this->assertSame('Alice', $users[0]->name);
    }

    #[Test]
    public function queryFirstReturnsSingleModel(): void
    {
        $this->insertUser('Alice', 'alice@example.com', 9.5, 1);

        $user = $this->db->queryFirst(
            TestUser::class,
            'SELECT * FROM users WHERE name = :name',
            ['name' => 'Alice'],
        );

        $this->assertInstanceOf(TestUser::class, $user);
        $this->assertSame('Alice', $user->name);
        $this->assertSame('alice@example.com', $user->email);
    }

    #[Test]
    public function queryFirstReturnsNullWhenNoMatch(): void
    {
        $user = $this->db->queryFirst(
            TestUser::class,
            'SELECT * FROM users WHERE name = :name',
            ['name' => 'Nobody'],
        );

        $this->assertNull($user);
    }

    #[Test]
    public function commandReturnsAffectedRowCount(): void
    {
        $this->insertUser('Alice', 'alice@example.com', 9.5, 1);
        $this->insertUser('Bob', 'bob@example.com', 7.2, 1);
        $this->insertUser('Charlie', 'charlie@example.com', 8.8, 0);

        $affected = $this->db->command(
            'UPDATE users SET active = 0 WHERE active = :active',
            ['active' => 1],
        );

        $this->assertSame(2, $affected);
    }

    #[Test]
    public function commandReturnsZeroWhenNoRowsAffected(): void
    {
        $affected = $this->db->command(
            'DELETE FROM users WHERE name = :name',
            ['name' => 'Nobody'],
        );

        $this->assertSame(0, $affected);
    }

    #[Test]
    public function scalarReturnsSingleValue(): void
    {
        $this->insertUser('Alice', 'alice@example.com', 9.5, 1);
        $this->insertUser('Bob', 'bob@example.com', 7.2, 1);

        $count = $this->db->scalar('SELECT COUNT(*) FROM users');

        $this->assertSame(2, (int) $count);
    }

    #[Test]
    public function transactionCommitsOnSuccess(): void
    {
        $this->db->transaction(function (DbContext $db): void {
            $db->command(
                'INSERT INTO users (name, email, score, active) VALUES (:name, :email, :score, :active)',
                ['name' => 'Alice', 'email' => 'alice@example.com', 'score' => 9.5, 'active' => 1],
            );
            $db->command(
                'INSERT INTO users (name, email, score, active) VALUES (:name, :email, :score, :active)',
                ['name' => 'Bob', 'email' => 'bob@example.com', 'score' => 7.2, 'active' => 1],
            );
        });

        $users = $this->db->query(TestUser::class, 'SELECT * FROM users ORDER BY id');

        $this->assertCount(2, $users);
    }

    #[Test]
    public function transactionRollsBackOnException(): void
    {
        try {
            $this->db->transaction(function (DbContext $db): void {
                $db->command(
                    'INSERT INTO users (name, email, score, active) VALUES (:name, :email, :score, :active)',
                    ['name' => 'Alice', 'email' => 'alice@example.com', 'score' => 9.5, 'active' => 1],
                );

                throw new RuntimeException('Something went wrong');
            });
        } catch (RuntimeException) {
            // expected
        }

        $users = $this->db->query(TestUser::class, 'SELECT * FROM users');

        $this->assertSame([], $users);
    }

    #[Test]
    public function transactionRethrowsException(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Test failure');

        $this->db->transaction(function (): void {
            throw new RuntimeException('Test failure');
        });
    }

    #[Test]
    public function transactionReturnsCallbackResult(): void
    {
        $result = $this->db->transaction(function (DbContext $db): int {
            $db->command(
                'INSERT INTO users (name, email, score, active) VALUES (:name, :email, :score, :active)',
                ['name' => 'Alice', 'email' => 'alice@example.com', 'score' => 9.5, 'active' => 1],
            );

            return $db->lastInsertId();
        });

        $this->assertSame(1, $result);
    }

    #[Test]
    public function hydrationCastsIntFromString(): void
    {
        $this->insertUser('Alice', 'alice@example.com', 9.5, 1);

        $user = $this->db->queryFirst(TestUser::class, 'SELECT * FROM users LIMIT 1');

        $this->assertIsInt($user->id);
        $this->assertSame(1, $user->id);
    }

    #[Test]
    public function hydrationCastsFloatFromString(): void
    {
        $this->insertUser('Alice', 'alice@example.com', 9.5, 1);

        $user = $this->db->queryFirst(TestUser::class, 'SELECT * FROM users LIMIT 1');

        $this->assertIsFloat($user->score);
        $this->assertSame(9.5, $user->score);
    }

    #[Test]
    public function hydrationCastsBoolFromString(): void
    {
        $this->insertUser('Alice', 'alice@example.com', 9.5, 1);

        $user = $this->db->queryFirst(TestUser::class, 'SELECT * FROM users LIMIT 1');

        $this->assertIsBool($user->active);
        $this->assertTrue($user->active);
    }

    #[Test]
    public function hydrationCastsBoolFalse(): void
    {
        $this->insertUser('Alice', 'alice@example.com', 9.5, 0);

        $user = $this->db->queryFirst(TestUser::class, 'SELECT * FROM users LIMIT 1');

        $this->assertIsBool($user->active);
        $this->assertFalse($user->active);
    }

    #[Test]
    public function hydrationHandlesNullablePropertyWithNull(): void
    {
        $this->insertUser('Alice', 'alice@example.com', 9.5, 1, null);

        $user = $this->db->queryFirst(TestUser::class, 'SELECT * FROM users LIMIT 1');

        $this->assertNull($user->bio);
    }

    #[Test]
    public function hydrationHandlesNullablePropertyWithValue(): void
    {
        $this->insertUser('Alice', 'alice@example.com', 9.5, 1, 'A bio');

        $user = $this->db->queryFirst(TestUser::class, 'SELECT * FROM users LIMIT 1');

        $this->assertSame('A bio', $user->bio);
    }

    #[Test]
    public function hydrationCastsStringProperty(): void
    {
        $this->insertUser('Alice', 'alice@example.com', 9.5, 1);

        $user = $this->db->queryFirst(TestUser::class, 'SELECT * FROM users LIMIT 1');

        $this->assertIsString($user->name);
        $this->assertSame('Alice', $user->name);
    }

    #[Test]
    public function lastInsertIdReturnsIntAfterInsert(): void
    {
        $this->db->command(
            'INSERT INTO users (name, email, score, active) VALUES (:name, :email, :score, :active)',
            ['name' => 'Alice', 'email' => 'alice@example.com', 'score' => 9.5, 'active' => 1],
        );

        $this->assertSame(1, $this->db->lastInsertId());

        $this->db->command(
            'INSERT INTO users (name, email, score, active) VALUES (:name, :email, :score, :active)',
            ['name' => 'Bob', 'email' => 'bob@example.com', 'score' => 7.2, 'active' => 1],
        );

        $this->assertSame(2, $this->db->lastInsertId());
    }

    #[Test]
    public function queryHydratesStdClassFromRow(): void
    {
        $this->insertUser('Alice', 'alice@example.com', 9.5, 1);

        $users = $this->db->query(\stdClass::class, 'SELECT * FROM users');

        $this->assertCount(1, $users);
        $this->assertInstanceOf(\stdClass::class, $users[0]);
        $this->assertSame('Alice', $users[0]->name);
        $this->assertSame('alice@example.com', $users[0]->email);
    }

    #[Test]
    public function queryFirstHydratesStdClassFromRow(): void
    {
        $this->insertUser('Alice', 'alice@example.com', 9.5, 1);

        $result = $this->db->queryFirst(\stdClass::class, 'SELECT * FROM users LIMIT 1');

        $this->assertInstanceOf(\stdClass::class, $result);
        $this->assertSame('Alice', $result->name);
        $this->assertSame('alice@example.com', $result->email);
    }

    #[Test]
    public function constructorAcceptsDsnString(): void
    {
        $db = new DbContext('sqlite::memory:');

        $db->command('CREATE TABLE test (id INTEGER PRIMARY KEY, name TEXT)');
        $db->command('INSERT INTO test (name) VALUES (:name)', ['name' => 'test']);

        $count = $db->scalar('SELECT COUNT(*) FROM test');

        $this->assertSame(1, (int) $count);
    }

    private function insertUser(
        string $name,
        string $email,
        float $score,
        int $active,
        ?string $bio = null,
    ): void {
        $this->db->command(
            'INSERT INTO users (name, email, score, active, bio) VALUES (:name, :email, :score, :active, :bio)',
            ['name' => $name, 'email' => $email, 'score' => $score, 'active' => $active, 'bio' => $bio],
        );
    }
}
