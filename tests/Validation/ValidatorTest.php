<?php

declare(strict_types=1);

namespace Tests\Validation;

use Melodic\Validation\Rules\Email;
use Melodic\Validation\Rules\In;
use Melodic\Validation\Rules\Max;
use Melodic\Validation\Rules\MaxLength;
use Melodic\Validation\Rules\Min;
use Melodic\Validation\Rules\MinLength;
use Melodic\Validation\Rules\Pattern;
use Melodic\Validation\Rules\Required;
use Melodic\Validation\ValidationException;
use Melodic\Validation\ValidationResult;
use Melodic\Validation\Validator;
use PHPUnit\Framework\TestCase;

// --- Test fixtures ---

class CreateUserRequest
{
    #[Required]
    #[MinLength(3)]
    public string $username;

    #[Required]
    #[Email]
    public string $email;

    #[Required]
    #[MinLength(8)]
    #[MaxLength(100)]
    public string $password;
}

class NumericDto
{
    #[Min(0)]
    #[Max(100)]
    public int $score;

    #[Min(0.5)]
    #[Max(10.5)]
    public float $rating;
}

class PatternDto
{
    #[Pattern('/^[A-Z]{2}-\d{4}$/')]
    public string $code;
}

class InDto
{
    #[In(['active', 'inactive', 'pending'])]
    public string $status;
}

class AllRulesDto
{
    #[Required]
    #[MinLength(2)]
    #[MaxLength(50)]
    #[Email]
    #[Pattern('/^.+@example\.com$/')]
    public string $email;
}

class OptionalFieldDto
{
    #[Required]
    public string $name;

    #[Email]
    public string $email;
}

// --- Tests ---

class ValidatorTest extends TestCase
{
    private Validator $validator;

    protected function setUp(): void
    {
        $this->validator = new Validator();
    }

    // --- Required rule ---

    public function testRequiredPassesWithValue(): void
    {
        $dto = new CreateUserRequest();
        $dto->username = 'john';
        $dto->email = 'john@example.com';
        $dto->password = 'secret123';

        $result = $this->validator->validate($dto);

        $this->assertTrue($result->isValid);
        $this->assertEmpty($result->errors);
    }

    public function testRequiredFailsWhenFieldIsNull(): void
    {
        $result = $this->validator->validateArray(
            ['username' => null, 'email' => 'a@b.com', 'password' => 'secret123'],
            CreateUserRequest::class
        );

        $this->assertFalse($result->isValid);
        $this->assertArrayHasKey('username', $result->errors);
    }

    public function testRequiredFailsWhenFieldIsEmptyString(): void
    {
        $result = $this->validator->validateArray(
            ['username' => '', 'email' => 'a@b.com', 'password' => 'secret123'],
            CreateUserRequest::class
        );

        $this->assertFalse($result->isValid);
        $this->assertArrayHasKey('username', $result->errors);
    }

    public function testRequiredFailsWhenFieldIsMissing(): void
    {
        $result = $this->validator->validateArray(
            ['email' => 'a@b.com', 'password' => 'secret123'],
            CreateUserRequest::class
        );

        $this->assertFalse($result->isValid);
        $this->assertArrayHasKey('username', $result->errors);
    }

    public function testRequiredFailsWhenWhitespaceOnly(): void
    {
        $result = $this->validator->validateArray(
            ['username' => '   ', 'email' => 'a@b.com', 'password' => 'secret123'],
            CreateUserRequest::class
        );

        $this->assertFalse($result->isValid);
        $this->assertArrayHasKey('username', $result->errors);
    }

    // --- MinLength rule ---

    public function testMinLengthPassesWhenLongEnough(): void
    {
        $result = $this->validator->validateArray(
            ['username' => 'john', 'email' => 'john@example.com', 'password' => 'secret123'],
            CreateUserRequest::class
        );

        $this->assertTrue($result->isValid);
    }

    public function testMinLengthFailsWhenTooShort(): void
    {
        $result = $this->validator->validateArray(
            ['username' => 'ab', 'email' => 'john@example.com', 'password' => 'secret123'],
            CreateUserRequest::class
        );

        $this->assertFalse($result->isValid);
        $this->assertArrayHasKey('username', $result->errors);
    }

    public function testMinLengthPassesAtExactMinimum(): void
    {
        $result = $this->validator->validateArray(
            ['username' => 'abc', 'email' => 'john@example.com', 'password' => '12345678'],
            CreateUserRequest::class
        );

        $this->assertTrue($result->isValid);
    }

    // --- MaxLength rule ---

    public function testMaxLengthPassesWhenShortEnough(): void
    {
        $result = $this->validator->validateArray(
            ['username' => 'john', 'email' => 'john@example.com', 'password' => 'secret123'],
            CreateUserRequest::class
        );

        $this->assertTrue($result->isValid);
    }

    public function testMaxLengthFailsWhenTooLong(): void
    {
        $result = $this->validator->validateArray(
            ['username' => 'john', 'email' => 'john@example.com', 'password' => str_repeat('a', 101)],
            CreateUserRequest::class
        );

        $this->assertFalse($result->isValid);
        $this->assertArrayHasKey('password', $result->errors);
    }

    public function testMaxLengthPassesAtExactMaximum(): void
    {
        $result = $this->validator->validateArray(
            ['username' => 'john', 'email' => 'john@example.com', 'password' => str_repeat('a', 100)],
            CreateUserRequest::class
        );

        $this->assertTrue($result->isValid);
    }

    // --- Email rule ---

    public function testEmailPassesWithValidEmail(): void
    {
        $result = $this->validator->validateArray(
            ['username' => 'john', 'email' => 'john@example.com', 'password' => 'secret123'],
            CreateUserRequest::class
        );

        $this->assertTrue($result->isValid);
    }

    public function testEmailFailsWithInvalidEmail(): void
    {
        $result = $this->validator->validateArray(
            ['username' => 'john', 'email' => 'not-an-email', 'password' => 'secret123'],
            CreateUserRequest::class
        );

        $this->assertFalse($result->isValid);
        $this->assertArrayHasKey('email', $result->errors);
    }

    public function testEmailFailsWithMissingAtSign(): void
    {
        $result = $this->validator->validateArray(
            ['username' => 'john', 'email' => 'johnexample.com', 'password' => 'secret123'],
            CreateUserRequest::class
        );

        $this->assertFalse($result->isValid);
        $this->assertArrayHasKey('email', $result->errors);
    }

    // --- Min rule ---

    public function testMinPassesWhenAboveMinimum(): void
    {
        $result = $this->validator->validateArray(
            ['score' => 50, 'rating' => 5.0],
            NumericDto::class
        );

        $this->assertTrue($result->isValid);
    }

    public function testMinFailsWhenBelowMinimum(): void
    {
        $result = $this->validator->validateArray(
            ['score' => -1, 'rating' => 5.0],
            NumericDto::class
        );

        $this->assertFalse($result->isValid);
        $this->assertArrayHasKey('score', $result->errors);
    }

    public function testMinPassesAtExactMinimum(): void
    {
        $result = $this->validator->validateArray(
            ['score' => 0, 'rating' => 0.5],
            NumericDto::class
        );

        $this->assertTrue($result->isValid);
    }

    public function testMinFailsWithFloatBelowMinimum(): void
    {
        $result = $this->validator->validateArray(
            ['score' => 50, 'rating' => 0.4],
            NumericDto::class
        );

        $this->assertFalse($result->isValid);
        $this->assertArrayHasKey('rating', $result->errors);
    }

    // --- Max rule ---

    public function testMaxPassesWhenBelowMaximum(): void
    {
        $result = $this->validator->validateArray(
            ['score' => 50, 'rating' => 5.0],
            NumericDto::class
        );

        $this->assertTrue($result->isValid);
    }

    public function testMaxFailsWhenAboveMaximum(): void
    {
        $result = $this->validator->validateArray(
            ['score' => 101, 'rating' => 5.0],
            NumericDto::class
        );

        $this->assertFalse($result->isValid);
        $this->assertArrayHasKey('score', $result->errors);
    }

    public function testMaxPassesAtExactMaximum(): void
    {
        $result = $this->validator->validateArray(
            ['score' => 100, 'rating' => 10.5],
            NumericDto::class
        );

        $this->assertTrue($result->isValid);
    }

    public function testMaxFailsWithFloatAboveMaximum(): void
    {
        $result = $this->validator->validateArray(
            ['score' => 50, 'rating' => 10.6],
            NumericDto::class
        );

        $this->assertFalse($result->isValid);
        $this->assertArrayHasKey('rating', $result->errors);
    }

    // --- Pattern rule ---

    public function testPatternPassesWithMatchingValue(): void
    {
        $result = $this->validator->validateArray(
            ['code' => 'AB-1234'],
            PatternDto::class
        );

        $this->assertTrue($result->isValid);
    }

    public function testPatternFailsWithNonMatchingValue(): void
    {
        $result = $this->validator->validateArray(
            ['code' => 'invalid'],
            PatternDto::class
        );

        $this->assertFalse($result->isValid);
        $this->assertArrayHasKey('code', $result->errors);
    }

    public function testPatternFailsWithPartialMatch(): void
    {
        $result = $this->validator->validateArray(
            ['code' => 'AB-12'],
            PatternDto::class
        );

        $this->assertFalse($result->isValid);
        $this->assertArrayHasKey('code', $result->errors);
    }

    // --- In rule ---

    public function testInPassesWithAllowedValue(): void
    {
        $result = $this->validator->validateArray(
            ['status' => 'active'],
            InDto::class
        );

        $this->assertTrue($result->isValid);
    }

    public function testInFailsWithDisallowedValue(): void
    {
        $result = $this->validator->validateArray(
            ['status' => 'deleted'],
            InDto::class
        );

        $this->assertFalse($result->isValid);
        $this->assertArrayHasKey('status', $result->errors);
    }

    public function testInUsesStrictComparison(): void
    {
        $result = $this->validator->validateArray(
            ['status' => 1],
            InDto::class
        );

        $this->assertFalse($result->isValid);
        $this->assertArrayHasKey('status', $result->errors);
    }

    // --- validate() method with object ---

    public function testValidateObjectPassesWithValidData(): void
    {
        $dto = new CreateUserRequest();
        $dto->username = 'john';
        $dto->email = 'john@example.com';
        $dto->password = 'secret123';

        $result = $this->validator->validate($dto);

        $this->assertTrue($result->isValid);
        $this->assertEmpty($result->errors);
    }

    public function testValidateObjectFailsWithInvalidData(): void
    {
        $dto = new CreateUserRequest();
        $dto->username = 'ab';
        $dto->email = 'invalid';
        $dto->password = 'short';

        $result = $this->validator->validate($dto);

        $this->assertFalse($result->isValid);
        $this->assertArrayHasKey('username', $result->errors);
        $this->assertArrayHasKey('email', $result->errors);
        $this->assertArrayHasKey('password', $result->errors);
    }

    public function testValidateObjectWithUninitializedProperty(): void
    {
        $dto = new CreateUserRequest();
        $dto->email = 'john@example.com';
        $dto->password = 'secret123';
        // username is uninitialized

        $result = $this->validator->validate($dto);

        $this->assertFalse($result->isValid);
        $this->assertArrayHasKey('username', $result->errors);
    }

    // --- validateArray() method ---

    public function testValidateArrayPassesWithValidData(): void
    {
        $result = $this->validator->validateArray(
            ['username' => 'john', 'email' => 'john@example.com', 'password' => 'secret123'],
            CreateUserRequest::class
        );

        $this->assertTrue($result->isValid);
        $this->assertEmpty($result->errors);
    }

    public function testValidateArrayFailsWithMissingFields(): void
    {
        $result = $this->validator->validateArray(
            [],
            CreateUserRequest::class
        );

        $this->assertFalse($result->isValid);
        $this->assertArrayHasKey('username', $result->errors);
        $this->assertArrayHasKey('email', $result->errors);
        $this->assertArrayHasKey('password', $result->errors);
    }

    // --- Multiple errors per field ---

    public function testMultipleErrorsCollectedPerField(): void
    {
        $result = $this->validator->validateArray(
            ['username' => '', 'email' => 'john@example.com', 'password' => 'secret123'],
            CreateUserRequest::class
        );

        $this->assertFalse($result->isValid);
        // username is empty: fails Required and MinLength
        $this->assertCount(2, $result->errors['username']);
    }

    // --- ValidationResult ---

    public function testValidationResultSuccessFactory(): void
    {
        $result = ValidationResult::success();

        $this->assertTrue($result->isValid);
        $this->assertEmpty($result->errors);
    }

    public function testValidationResultFailureFactory(): void
    {
        $errors = ['field' => ['Error 1', 'Error 2']];
        $result = ValidationResult::failure($errors);

        $this->assertFalse($result->isValid);
        $this->assertSame($errors, $result->errors);
    }

    // --- ValidationException ---

    public function testValidationExceptionHoldsResult(): void
    {
        $result = ValidationResult::failure(['field' => ['Error']]);
        $exception = new ValidationException($result);

        $this->assertSame($result, $exception->result);
        $this->assertSame('Validation failed', $exception->getMessage());
    }

    public function testValidationExceptionWithCustomMessage(): void
    {
        $result = ValidationResult::failure(['field' => ['Error']]);
        $exception = new ValidationException($result, 'Custom error');

        $this->assertSame('Custom error', $exception->getMessage());
    }

    // --- Custom error messages ---

    public function testRequiredRuleCustomMessage(): void
    {
        $rule = new Required('Username is required');

        $this->assertFalse($rule->validate(null));
        $this->assertSame('Username is required', $rule->message);
    }

    public function testMinLengthRuleCustomMessage(): void
    {
        $rule = new MinLength(3, 'Too short');

        $this->assertFalse($rule->validate('ab'));
        $this->assertSame('Too short', $rule->message);
    }

    public function testMaxLengthRuleCustomMessage(): void
    {
        $rule = new MaxLength(5, 'Too long');

        $this->assertFalse($rule->validate('abcdef'));
        $this->assertSame('Too long', $rule->message);
    }

    public function testEmailRuleCustomMessage(): void
    {
        $rule = new Email('Invalid email');

        $this->assertFalse($rule->validate('bad'));
        $this->assertSame('Invalid email', $rule->message);
    }

    public function testMinRuleCustomMessage(): void
    {
        $rule = new Min(10, 'Too small');

        $this->assertFalse($rule->validate(5));
        $this->assertSame('Too small', $rule->message);
    }

    public function testMaxRuleCustomMessage(): void
    {
        $rule = new Max(10, 'Too big');

        $this->assertFalse($rule->validate(15));
        $this->assertSame('Too big', $rule->message);
    }

    public function testPatternRuleCustomMessage(): void
    {
        $rule = new Pattern('/^\d+$/', 'Must be numeric');

        $this->assertFalse($rule->validate('abc'));
        $this->assertSame('Must be numeric', $rule->message);
    }

    public function testInRuleCustomMessage(): void
    {
        $rule = new In(['a', 'b'], 'Invalid choice');

        $this->assertFalse($rule->validate('c'));
        $this->assertSame('Invalid choice', $rule->message);
    }

    // --- Non-required field validation ---

    public function testNonRequiredFieldSkipsOtherRulesWhenNull(): void
    {
        $result = $this->validator->validateArray(
            ['name' => 'John'],
            OptionalFieldDto::class
        );

        // email is not required, but Email rule will fail for null
        // This validates that each rule runs independently
        $this->assertFalse($result->isValid);
        $this->assertArrayHasKey('email', $result->errors);
    }
}
