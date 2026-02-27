<?php

declare(strict_types=1);

namespace Tests\Console\Make;

use Melodic\Console\Make\Stub;
use PHPUnit\Framework\TestCase;

class StubTest extends TestCase
{
    public function testRenderReplacesPlaceholders(): void
    {
        $result = Stub::render('Hello {name}, welcome to {place}!', [
            'name' => 'Alice',
            'place' => 'Wonderland',
        ]);

        $this->assertSame('Hello Alice, welcome to Wonderland!', $result);
    }

    public function testRenderWithNoPlaceholders(): void
    {
        $result = Stub::render('No placeholders here', ['foo' => 'bar']);
        $this->assertSame('No placeholders here', $result);
    }

    public function testRenderWithEmptyReplacements(): void
    {
        $result = Stub::render('Hello {name}', []);
        $this->assertSame('Hello {name}', $result);
    }

    public function testPascalCaseFromSnakeCase(): void
    {
        $this->assertSame('UserProfile', Stub::pascalCase('user_profile'));
    }

    public function testPascalCaseFromKebabCase(): void
    {
        $this->assertSame('UserProfile', Stub::pascalCase('user-profile'));
    }

    public function testPascalCaseFromSpaceSeparated(): void
    {
        $this->assertSame('UserProfile', Stub::pascalCase('user profile'));
    }

    public function testPascalCaseAlreadyPascal(): void
    {
        $this->assertSame('Church', Stub::pascalCase('Church'));
    }

    public function testPascalCaseLowercase(): void
    {
        $this->assertSame('Church', Stub::pascalCase('church'));
    }

    public function testCamelCaseFromSnakeCase(): void
    {
        $this->assertSame('userProfile', Stub::camelCase('user_profile'));
    }

    public function testCamelCaseFromPascalCase(): void
    {
        $this->assertSame('church', Stub::camelCase('Church'));
    }

    public function testCamelCaseSingleWord(): void
    {
        $this->assertSame('church', Stub::camelCase('church'));
    }

    public function testSnakeCaseFromPascalCase(): void
    {
        $this->assertSame('user_profile', Stub::snakeCase('UserProfile'));
    }

    public function testSnakeCaseFromCamelCase(): void
    {
        $this->assertSame('user_profile', Stub::snakeCase('userProfile'));
    }

    public function testSnakeCaseFromKebabCase(): void
    {
        $this->assertSame('user_profile', Stub::snakeCase('user-profile'));
    }

    public function testSnakeCaseSingleWord(): void
    {
        $this->assertSame('church', Stub::snakeCase('Church'));
    }

    public function testPluralizeRegularWord(): void
    {
        $this->assertSame('Users', Stub::pluralize('User'));
    }

    public function testPluralizeWordEndingInS(): void
    {
        $this->assertSame('Addresses', Stub::pluralize('Address'));
    }

    public function testPluralizeWordEndingInCh(): void
    {
        $this->assertSame('Churches', Stub::pluralize('Church'));
    }

    public function testPluralizeWordEndingInSh(): void
    {
        $this->assertSame('Brushes', Stub::pluralize('Brush'));
    }

    public function testPluralizeWordEndingInX(): void
    {
        $this->assertSame('Boxes', Stub::pluralize('Box'));
    }

    public function testPluralizeWordEndingInZ(): void
    {
        $this->assertSame('Quizzes', Stub::pluralize('Quiz'));
    }

    public function testPluralizeWordEndingInConsonantY(): void
    {
        $this->assertSame('Categories', Stub::pluralize('Category'));
    }

    public function testPluralizeWordEndingInVowelY(): void
    {
        $this->assertSame('Days', Stub::pluralize('Day'));
    }

    public function testPluralizeIrregularPerson(): void
    {
        $this->assertSame('People', Stub::pluralize('Person'));
    }

    public function testPluralizeIrregularChild(): void
    {
        $this->assertSame('Children', Stub::pluralize('Child'));
    }

    public function testPluralizeIrregularStatus(): void
    {
        $this->assertSame('Statuses', Stub::pluralize('Status'));
    }

    public function testPluralizeEmptyString(): void
    {
        $this->assertSame('', Stub::pluralize(''));
    }
}
