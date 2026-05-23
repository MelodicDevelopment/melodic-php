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

    public function testFromArrayBindsAllCapsPropertyFromLowercaseKey(): void
    {
        $model = AcronymModel::fromArray([
            'ein' => '12-3456789',
            'url' => 'https://example.com',
        ]);

        $this->assertSame('12-3456789', $model->EIN);
        $this->assertSame('https://example.com', $model->URL);
    }

    public function testFromArrayBindsAllCapsPropertyFromExactKey(): void
    {
        $model = AcronymModel::fromArray([
            'EIN' => '12-3456789',
            'URL' => 'https://example.com',
        ]);

        $this->assertSame('12-3456789', $model->EIN);
        $this->assertSame('https://example.com', $model->URL);
    }

    public function testToArraySerializesAllCapsPropertyAsLowercase(): void
    {
        $model = AcronymModel::fromArray([
            'ein' => '12-3456789',
            'url' => 'https://example.com',
        ]);

        $result = $model->toArray();

        $this->assertSame([
            'ein' => '12-3456789',
            'url' => 'https://example.com',
        ], $result);
    }

    public function testToArrayKeepsMixedCasePropertyUntouched(): void
    {
        $model = MixedCaseModel::fromArray([
            'userName' => 'alice',
            'apiKey' => 'secret',
        ]);

        $result = $model->toArray();

        $this->assertSame([
            'userName' => 'alice',
            'apiKey' => 'secret',
        ], $result);
    }

    public function testAcronymRoundTripsLowercase(): void
    {
        $model = AcronymModel::fromArray([
            'ein' => '12-3456789',
            'url' => 'https://example.com',
        ]);

        $this->assertSame(
            '{"ein":"12-3456789","url":"https:\/\/example.com"}',
            json_encode($model),
        );
    }

    public function testToUpdateArrayPreservesExplicitNull(): void
    {
        // RFC 7396: explicit null in input means "clear this field" and must be
        // emitted, not silently dropped.
        $model = PartialUpdateModel::fromArray(['Description' => null]);

        $this->assertSame(['Description' => null], $model->toUpdateArray());
    }

    public function testToUpdateArrayEmitsProvidedValue(): void
    {
        $model = PartialUpdateModel::fromArray(['Description' => 'x']);

        $this->assertSame(['Description' => 'x'], $model->toUpdateArray());
    }

    public function testToUpdateArrayEmptyWhenNothingProvided(): void
    {
        $model = PartialUpdateModel::fromArray([]);

        $this->assertSame([], $model->toUpdateArray());
    }

    public function testWasProvidedReflectsInput(): void
    {
        $model = PartialUpdateModel::fromArray(['Description' => 'x']);

        $this->assertTrue($model->wasProvided('Description'));
        $this->assertFalse($model->wasProvided('Name'));
    }

    public function testWasProvidedAndUpdateUseResolvedPropertyName(): void
    {
        // Lowercase input key resolves to the all-caps property; consumers should
        // ask for the property name (EIN), not the wire key (ein).
        $model = AcronymModel::fromArray(['ein' => '12-3456789']);

        $this->assertTrue($model->wasProvided('EIN'));
        $this->assertFalse($model->wasProvided('ein'));
        $this->assertSame(['EIN' => '12-3456789'], $model->toUpdateArray());
    }

    public function testProgrammaticAssignmentDoesNotPolluteUpdateArray(): void
    {
        // fields_set reflects the wire, not subsequent mutation.
        $model = new PartialUpdateModel();
        $model->Description = 'x';

        $this->assertSame([], $model->toUpdateArray());
        $this->assertFalse($model->wasProvided('Description'));
    }

    public function testToUpdateArrayConvertsBoolToInt(): void
    {
        $model = PartialUpdateModel::fromArray(['Active' => true]);

        $this->assertSame(['Active' => 1], $model->toUpdateArray());
    }
}

class TestModel extends Model
{
    public int $Id;
    public string $Name;
    public string $Email;
}

class AcronymModel extends Model
{
    public string $EIN;
    public string $URL;
}

class MixedCaseModel extends Model
{
    public string $userName;
    public string $apiKey;
}

class PartialUpdateModel extends Model
{
    public ?string $Name = null;
    public ?string $Description = null;
    public ?bool $Active = null;
}
