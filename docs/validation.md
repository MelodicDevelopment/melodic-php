# Validation

Melodic provides attribute-based validation using PHP 8.2+ attributes on DTO properties. The `Validator` class inspects attributes via Reflection and runs each rule against the property value. Controller action parameters typed as a `Model` are bound from the request and validated automatically — see [Automatic Model Binding](#automatic-model-binding).

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
| `#[Required]` | — | Not null, not empty/whitespace string, not empty array (4.0) |
| `#[Email]` | — | String that is a valid email via `filter_var` |
| `#[MinLength(n)]` | `int $min` | String with at least n characters |
| `#[MaxLength(n)]` | `int $max` | String with at most n characters |
| `#[Min(n)]` | `int\|float $min` | Numeric value >= n (numeric strings are accepted) |
| `#[Max(n)]` | `int\|float $max` | Numeric value <= n (numeric strings are accepted) |
| `#[Pattern(regex)]` | `string $regex` | String matching the regular expression (`preg_match`, delimiters included) |
| `#[In(values)]` | `array $values` | Value must be in the list (strict comparison) |
| `#[Url]` (since 4.1) | — | Absolute `http://` or `https://` URL; other schemes are rejected |
| `#[Uuid]` (since 4.1) | — | UUID string in `8-4-4-4-12` hex form (case-insensitive) |
| `#[Date(format)]` (since 4.1) | `string $format = 'Y-m-d'` | Date/time string in exactly the given format (`2026-02-31` is rejected). A property already bound as a `DateTime` object passes. |

All rules live in `Melodic\Validation\Rules` and accept an optional `message` parameter to override the default error message:

```php
#[Required(message: 'Username cannot be blank')]
#[MinLength(3, message: 'Username must be at least 3 characters')]
```

### Rules are nullable-by-default

Since 3.0, every rule except `#[Required]` **passes when the value is `null`** (or the property was never set). Format rules only constrain a value that is present; `#[Required]` is the only rule that rejects a missing one.

```php
#[Email]
public ?string $backupEmail = null;   // omitted → valid; "nope" → invalid

#[Required]
#[Email]
public string $email;                 // omitted → "This field is required"
```

Only `null` is skipped. An empty string is a value: `''` fails `#[Email]`, `#[Pattern]`, and `#[MinLength(1)]`. `#[Required]` rejects `null`, `''`, whitespace-only strings, and `[]`, but accepts `0` and `false`.

`#[MinLength]` and `#[MaxLength]` count characters when the `mbstring` extension is loaded. The extension is optional (a Composer `suggest`); without it they count bytes, so multibyte text measures longer.

The type checks are part of each rule: the string rules (`#[Email]`, `#[MinLength]`, `#[MaxLength]`, `#[Pattern]`) fail for a non-null value that is not a string, and `#[Min]`/`#[Max]` fail for a non-numeric value.

### `#[In]` comparison semantics

`#[In]` compares strictly (`===`). Model binding coerces wire input to the
property's declared type *before* validation runs, so `#[In([1, 2])]` on an
`int` property accepts `"1"` from a client. On an untyped/`mixed` property no
coercion happens — `"1" !== 1` — so type the property or list values of the
matching type.

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

`validate()` checks every public property, runs all of its rules (a field can collect several messages), and keys the errors by property name. A typed property that was never initialized is validated as `null`.

Nested models are validated too (since 4.1): a property holding a `Model` instance, or an array containing `Model` instances, is validated recursively and its errors are keyed with a dotted path of property names — e.g. `Address.City` or `Lines.0.Quantity` for properties declared with those names.

## Validating Arrays

Validate raw input (e.g. from `$request->body()`) against a DTO class without instantiating it:

```php
$data = $request->body();

$result = $validator->validateArray($data, CreateUserDto::class);

if (!$result->isValid) {
    return $this->badRequest($result->errors);   // 400, same shape as automatic binding
}
```

`validateArray()` validates the raw array as-is, which makes it stricter and simpler than model binding:

- Each public property is looked up in `$data` by its **exact name** (case-sensitive). There is none of the key matching that `Model::fromArray()` does, so a `userName` key does not satisfy a `$UserName` property.
- A missing key is validated as `null`; keys with no matching property are ignored.
- Values are not coerced. `"25"` passes `#[Min(18)]` (numeric string), but `"1"` fails `#[In([1, 2])]` because the comparison is strict.
- Nested models are not validated — only the top-level rules run.

## ValidationResult

| Property/Method | Description |
|---|---|
| `$result->isValid` | `bool` — whether all rules passed |
| `$result->errors` | `array<string, string[]>` — field name to error messages |
| `ValidationResult::success()` | Factory for a valid result |
| `ValidationResult::failure($errors)` | Factory for an invalid result |

## ValidationException

`Melodic\Validation\ValidationException` carries a `ValidationResult` on its public `$result` property, for code (typically a service) that wants to abort with validation errors:

```php
$result = $validator->validate($dto);

if (!$result->isValid) {
    throw new ValidationException($result);
}
```

The [exception handler](error-handling.md) maps an uncaught `ValidationException` to a **400** whose JSON body is `$e->result->errors` — the same `{field: [messages]}` shape as automatic model binding, and always JSON regardless of the request's `Accept` header. It is logged as a client error. Automatic model binding does not throw this exception (it returns its 400 directly), and neither does the `Validator`; it exists for your own code.

`Melodic\Data\ModelBindingException` is mapped to 400 as well, for code that calls `Model::fromArray()` on client input itself. It goes through the normal error rendering, so the body is `{"error": "Field 'age' must be an integer."}` (or the HTML error page for non-JSON requests).

To answer with a different status or shape, register a [custom exception mapper](error-handling.md#custom-exception-mappers) for the exception class — mappers take precedence over the built-in mapping.

## Custom Rules

A rule is an attribute class that implements `Melodic\Validation\RuleInterface` (since 4.1) — a single `validate(mixed $value): bool` method — and exposes a public `$message` string. For backwards compatibility, any attribute class with a `validate()` method is also treated as a rule. A rule without a `$message` property reports `Invalid value`.

```php
use Attribute;
use Melodic\Validation\RuleInterface;

#[Attribute(Attribute::TARGET_PROPERTY)]
class Slug implements RuleInterface
{
    public function __construct(
        public readonly string $message = 'Must be a lowercase slug',
    ) {}

    public function validate(mixed $value): bool
    {
        if ($value === null) {
            return true;   // stay nullable-by-default; #[Required] handles absence
        }

        return is_string($value) && preg_match('/^[a-z0-9]+(-[a-z0-9]+)*$/', $value) === 1;
    }
}
```

Attributes that are not rules (such as `#[Guarded]`) are ignored by the validator.

## Automatic Model Binding

When a controller action has a parameter typed as a `Melodic\Data\Model` subclass, the framework automatically hydrates it from the request body and validates it. If the body cannot be bound to the model's types, or validation fails, a `400` JSON response is returned before the controller is called.

```php
use Melodic\Data\Model;
use Melodic\Validation\Rules\{Required, Email, MaxLength};

class CreateUserRequest extends Model
{
    #[Required]
    #[MaxLength(50)]
    public string $username;

    #[Required]
    #[Email]
    public string $email;
}
```

Register the routes — `POST /api/users` and `PUT /api/users/{id}`:

```php
$router->post('/api/users', UserApiController::class, 'store');
$router->put('/api/users/{id}', UserApiController::class, 'update');

// Or use apiResource() which registers all RESTful routes at once:
$router->apiResource('/api/users', UserApiController::class);
```

Then in the controller, type-hint the request model on the action parameter:

```php
class UserApiController extends ApiController
{
    public function __construct(
        private readonly UserService $userService,
    ) {}

    public function store(CreateUserRequest $request): JsonResponse
    {
        // $request is already hydrated from the POST body and validated
        $id = $this->userService->create($request->username, $request->email);
        return $this->created(['id' => $id], "/api/users/{$id}");
    }

    public function update(string $id, UpdateUserRequest $request): JsonResponse
    {
        // Route params (like $id from the URL) and model params work together
        $this->userService->update($id, $request);
        return $this->noContent();
    }
}
```

If the request body fails validation, the framework returns a `400` response whose body maps each field to its list of messages (there is no `errors` wrapper):

```json
{
    "username": ["This field is required"],
    "email": ["Must be a valid email address"]
}
```

Binding failures use the same status and shape. Binding stops at the first value it cannot coerce, so the body contains that one field:

```json
{
    "age": ["Field 'age' must be an integer."]
}
```

Validation errors are keyed by the model's property name; a binding error is keyed by the key the client sent.

**How it works:**
- The `RoutingMiddleware` uses `ReflectionMethod` to inspect action parameters
- Route params (strings from the URL like `$id`) are matched by name first and
  coerced to the parameter's declared type — see [Typed Route Parameters](#typed-route-parameters)
- Parameters typed as a `Model` subclass are hydrated via `Model::fromArray($request->body())`.
  The body is the submitted form data, or the decoded JSON when `JsonBodyParserMiddleware`
  is registered — without that middleware a JSON body is not parsed and the model is bound from an empty array
- The hydrated model is validated using the `Validator` (resolved from the DI container)
- If binding or validation fails, the controller action is never called
- Any other class-typed parameter is resolved from the DI container (see [Other Action Parameters](#other-action-parameters))

### Binding from the query string

Mark a model parameter with `#[FromQuery]` (`Melodic\Routing\FromQuery`, since 4.1) to bind it from the query string instead of the body — for `GET` endpoints with filter or paging DTOs. Coercion and validation work exactly as for a body-bound model.

```php
use Melodic\Routing\FromQuery;

public function index(#[FromQuery] UserFilter $filter): JsonResponse
{
    // GET /api/users?role=admin&page=2
}
```

### How input keys map to properties

For each input key, `Model::fromArray()` looks for a public property with that exact name, then with the first letter upper-cased (`userName` → `$UserName`), then fully upper-cased (`ein` → `$EIN`). Keys that match no property are ignored. Properties that receive no input keep their declared default; nullable properties without a default are set to `null`.

A non-nullable property with no default that the client omits is left **uninitialized**. Validation sees it as `null`, so mark such properties `#[Required]` (or give them a default) — otherwise the request passes validation and reading the property in your controller throws an `Error`.

### Type coercion

Input is coerced to each property's declared type before validation, so loosely typed wire data (form strings, JSON numbers) binds cleanly under `strict_types`. A value that cannot be coerced produces the `400` shown above rather than a `TypeError`.

| Property type | Accepted input |
|---|---|
| `int` | integers; integer strings (`"42"`, `"-7"`); floats with no fractional part (`5.0`) |
| `float` | integers, floats, numeric strings |
| `bool` | `true`/`false`; `1`/`0`; the strings `"1"`, `"true"`, `"yes"`, `"on"` and `"0"`, `"false"`, `"no"`, `"off"`, `""` (case-insensitive) |
| `string` | strings; integers and floats (converted to their string form) |
| Backed enum (4.0) | the case's backing value; an int-backed enum also accepts an integer string |
| `DateTime`, `DateTimeImmutable`, `DateTimeInterface` (4.0) | a non-empty date/time string PHP can parse (`DateTimeInterface` yields a `DateTimeImmutable`) |
| `array` | an array. Elements are left as decoded unless the property declares its element type with `#[ListOf]` — see [Lists of models](#lists-of-models) |
| `Model` subclass | a JSON object, hydrated recursively (and then validated recursively); binding errors use a dotted path such as `address.zip` (since 4.1) |
| union types, untyped, `mixed` | passed through unchanged |

`null` is accepted only for nullable properties; for any other property it is a binding error (`Field 'name' may not be null.`).

Integer strings too large for a PHP `int` are rejected rather than clamped, and a value that still does not fit the property after coercion (for example an array sent for a union-typed property) is reported as a `400` (`Field 'x' has an invalid type.`) instead of surfacing as a `TypeError`.

### Lists of models

A plain `array` property receives the decoded input untouched, so a list of JSON objects stays a list of arrays and its items are not validated. Declare the element type with `#[ListOf]` (`Melodic\Data\ListOf`, since 4.1) to have each element hydrated into a model and validated:

```php
use Melodic\Data\ListOf;
use Melodic\Data\Model;

class OrderLine extends Model
{
    #[Required]
    public string $Sku;

    #[Min(1)]
    public int $Quantity = 1;
}

class CreateOrderRequest extends Model
{
    #[ListOf(OrderLine::class)]
    public array $Lines = [];
}
```

Each element is bound with `OrderLine::fromArray()`, so the same key matching and coercion apply, and an element that is not a JSON object is a binding error. Errors carry the element's index:

```json
{ "lines.1.quantity": ["Field 'lines.1.quantity' must be an integer."] }
```

```json
{ "Lines.1.Sku": ["This field is required"] }
```

The first is a binding error (keyed by what the client sent), the second a validation error (keyed by property names).

### Typed Route Parameters

Route parameters are captured as strings and converted to the action parameter's declared type (4.0):

```php
public function show(int $id): JsonResponse { /* /users/42 → 42 */ }
public function byStatus(Status $status): JsonResponse { /* backed enum from its value */ }
```

| Parameter type | Accepted value |
|---|---|
| `int` | optional `-` followed by digits |
| `float` | any numeric string |
| `bool` | `1`, `0`, `true`, `false` (case-insensitive) |
| Backed enum | a valid backing value |
| `string`, untyped, anything else | passed through as the raw string |

A value that does not fit returns a `400` with the same body shape, keyed by the parameter name, and the controller is not called:

```json
{
    "id": ["Route parameter 'id' must be an integer."]
}
```

### Other Action Parameters

- A parameter typed as any other class (not a `Model`, not a route parameter) is resolved from the DI container, so services can be injected directly into an action (3.0).
- A scalar or untyped parameter with no matching route parameter receives its default value, or `null` if it is nullable. If it has neither, the request fails with a `RuntimeException` (a 500) naming the parameter.

```php
public function export(string $id, ReportService $reports, string $format = 'csv'): Response
```

## Mass Assignment

`Model::fromArray()` binds **every matching public property** from the input,
and `toUpdateArray()` carries every provided field into your UPDATE statement.
If you reuse a full entity DTO as a request body, a client can over-post
fields you never intended to accept (`role`, `isActive`, ...).

Two defenses, in order of preference:

1. **One input DTO per endpoint.** Define a dedicated request model per
   endpoint (`CreateUserRequest`, `UpdateUserProfileRequest`) containing only
   the fields that endpoint accepts. Never reuse a persistence/entity model as
   an action parameter.
2. **`#[Guarded]`** as defense in depth. A guarded property is never bound
   from `fromArray()` input and never appears in `toUpdateArray()`; it can
   only be set programmatically:

```php
use Melodic\Data\Guarded;
use Melodic\Data\Model;

class UserModel extends Model
{
    public string $username;

    #[Guarded]
    public string $role = 'user';   // wire input silently ignored

    #[Guarded]
    public bool $isActive = true;
}
```

`#[Guarded]` (`Melodic\Data\Guarded`, added in 4.0) only affects `fromArray()` binding — and therefore `toUpdateArray()`, which reports bound fields. Guarded properties are still populated when `DbContext` hydrates a model from a query, and they still appear in `toArray()`, `toPascalArray()`, and JSON output.

## Partial Updates

`Model::fromArray()` records which properties the input actually supplied, which lets an update endpoint distinguish "not sent" from "sent as `null`":

```php
public function update(int $id, UpdateUserRequest $request): JsonResponse
{
    $fields = $request->toUpdateArray();
    // PATCH {"email": "a@b.co", "nickname": null}
    // → ['email' => 'a@b.co', 'nickname' => null]   (username not sent, so absent)

    if ($request->wasProvided('nickname')) { /* the client sent it, even if null */ }
}
```

- `toUpdateArray()` returns only the properties that were present in the input, **including explicit nulls**, keyed by property name. Values are converted for PDO: booleans to `1`/`0`, backed enums to their backing value, and date/time objects to a `Y-m-d H:i:s` string.
- `wasProvided(string $property)` takes the property name as declared on the model (e.g. `EIN`), not the key the client sent (`ein`).
- Only `fromArray()` input is tracked. Properties you assign afterwards are not reported, and `#[Guarded]` properties never are.
- Use `toPascalArray()` when you want every initialized property (e.g. for an INSERT).

## Manual Validation in a Controller

You can still validate manually when you need more control:

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
            // Your choice of status and shape; this mirrors automatic binding.
            return $this->badRequest($result->errors);
        }

        $body = $this->request->body();
        $id = $this->userService->create($body['username'], $body['email']);

        return $this->created(['id' => $id], "/api/users/{$id}");
    }
}
```
