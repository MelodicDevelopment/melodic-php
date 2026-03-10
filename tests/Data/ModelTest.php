<?php

declare(strict_types=1);

namespace Tests\Data;

use Melodic\Data\Model;
use PHPUnit\Framework\TestCase;

final class ModelTest extends TestCase
{
    public function testFromArrayWithPascalCaseKeys(): void
    {
        $model = TestModel::fromArray([
            'Id' => 1,
            'Name' => 'Alice',
            'Email' => 'alice@example.com',
        ]);

        $this->assertSame(1, $model->Id);
        $this->assertSame('Alice', $model->Name);
        $this->assertSame('alice@example.com', $model->Email);
    }

    public function testFromArrayWithCamelCaseKeys(): void
    {
        $model = TestModel::fromArray([
            'id' => 2,
            'name' => 'Bob',
            'email' => 'bob@example.com',
        ]);

        $this->assertSame(2, $model->Id);
        $this->assertSame('Bob', $model->Name);
        $this->assertSame('bob@example.com', $model->Email);
    }

    public function testFromArraySkipsUnknownKeys(): void
    {
        $model = TestModel::fromArray([
            'Id' => 1,
            'Name' => 'Alice',
            'UnknownField' => 'ignored',
        ]);

        $this->assertSame(1, $model->Id);
        $this->assertSame('Alice', $model->Name);
    }

    public function testToArrayReturnsCamelCaseKeys(): void
    {
        $model = TestModel::fromArray([
            'Id' => 1,
            'Name' => 'Alice',
            'Email' => 'alice@example.com',
        ]);

        $result = $model->toArray();

        $this->assertSame([
            'id' => 1,
            'name' => 'Alice',
            'email' => 'alice@example.com',
        ], $result);
    }

    public function testToArraySkipsUninitializedProperties(): void
    {
        $model = TestModel::fromArray([
            'Id' => 1,
            'Name' => 'Alice',
        ]);

        $result = $model->toArray();

        $this->assertArrayHasKey('id', $result);
        $this->assertArrayHasKey('name', $result);
        $this->assertArrayNotHasKey('email', $result);
    }

    public function testJsonSerializeSameAsToArray(): void
    {
        $model = TestModel::fromArray([
            'Id' => 1,
            'Name' => 'Alice',
            'Email' => 'alice@example.com',
        ]);

        $this->assertSame($model->toArray(), $model->jsonSerialize());
    }

    public function testJsonEncodeUsesJsonSerialize(): void
    {
        $model = TestModel::fromArray([
            'Id' => 1,
            'Name' => 'Alice',
            'Email' => 'alice@example.com',
        ]);

        $json = json_encode($model);

        $this->assertSame('{"id":1,"name":"Alice","email":"alice@example.com"}', $json);
    }
}

class TestModel extends Model
{
    public int $Id;
    public string $Name;
    public string $Email;
}
