# Validation

Melodic provides attribute-based validation using PHP 8.2+ attributes on DTO properties. The `Validator` class inspects attributes via Reflection and runs each rule against the property value.

## Defining Rules on a DTO

```php
use Melodic\Validation\Rules\{Required, Email, MinLength, MaxLength, Min, Max, Pattern, In};

class CreateUserDto
{
    #[Required]
    #[MinLength(3)]
    #[MaxLength(50)]
    public string $username;

    #[Required]
    #[Email]
    public string $email;

    #[Required]
    #[Min(18)]
    #[Max(120)]
    public int $age;

    #[Required]
    #[In(['admin', 'editor', 'viewer'])]
    public string $role;

    #[Pattern('/^[A-Z]{2}-\d{4}$/')]
    public ?string $code = null;
}
```

## Available Rules

| Attribute | Parameters | Description |
|---|---|---|
| `#[Required]` | — | Not null and not empty string |
| `#[Email]` | — | Valid email via `filter_var` |
| `#[MinLength(n)]` | `int $min` | String length >= n |
| `#[MaxLength(n)]` | `int $max` | String length <= n |
| `#[Min(n)]` | `int\|float $min` | Numeric value >= n |
| `#[Max(n)]` | `int\|float $max` | Numeric value <= n |
| `#[Pattern(regex)]` | `string $regex` | Matches regular expression |
| `#[In(values)]` | `array $values` | Value must be in the list (strict comparison) |

All rules accept an optional `message` parameter to override the default error message:

```php
#[Required(message: 'Username cannot be blank')]
#[MinLength(3, message: 'Username must be at least 3 characters')]
```

## Validating Objects

```php
use Melodic\Validation\Validator;

$validator = new Validator();

$dto = new CreateUserDto();
$dto->username = 'al';
$dto->email = 'not-an-email';
$dto->age = 15;
$dto->role = 'superadmin';

$result = $validator->validate($dto);

$result->isValid;  // false
$result->errors;   // ['username' => ['Must be at least 3 characters'],
                   //  'email' => ['Must be a valid email address'],
                   //  'age' => ['Must be at least 18'],
                   //  'role' => ['Must be one of: admin, editor, viewer']]
```

## Validating Arrays

Validate raw input (e.g. from `$request->body()`) against a DTO class without instantiating it:

```php
$data = $request->body();

$result = $validator->validateArray($data, CreateUserDto::class);

if (!$result->isValid) {
    return $this->json(['errors' => $result->errors], 422);
}
```

## ValidationResult

| Property/Method | Description |
|---|---|
| `$result->isValid` | `bool` — whether all rules passed |
| `$result->errors` | `array<string, string[]>` — field name to error messages |
| `ValidationResult::success()` | Factory for a valid result |
| `ValidationResult::failure($errors)` | Factory for an invalid result |

## ValidationException

Throw a `ValidationException` to let the [exception handler](error-handling.md) return a 422 response automatically:

```php
$result = $validator->validate($dto);

if (!$result->isValid) {
    throw new ValidationException($result);
}
```

The exception carries the `ValidationResult` on its `$result` property, which the exception handler includes in the JSON response.

## In a Controller

```php
class UserApiController extends ApiController
{
    public function __construct(
        private readonly UserService $userService,
        private readonly Validator $validator,
    ) {}

    public function store(): JsonResponse
    {
        $result = $this->validator->validateArray(
            $this->request->body(),
            CreateUserDto::class
        );

        if (!$result->isValid) {
            return $this->json(['errors' => $result->errors], 422);
        }

        $body = $this->request->body();
        $id = $this->userService->create($body['username'], $body['email']);

        return $this->created(['id' => $id], "/api/users/{$id}");
    }
}
```
