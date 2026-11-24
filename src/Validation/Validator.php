<?php

declare(strict_types=1);

namespace Melodic\Validation;

use Melodic\Data\Model;
use ReflectionClass;
use ReflectionProperty;

class Validator
{
    /**
     * Rule instances per class and property, built once — rule attributes are
     * immutable, so re-reflecting and re-instantiating them on every
     * validation was wasted work.
     *
     * @var array<class-string, array<string, array{property: ReflectionProperty, rules: object[]}>>
     */
    private array $ruleCache = [];

    public function validate(object $dto): ValidationResult
    {
        $errors = $this->collectErrors($dto, '');

        return $errors === []
            ? ValidationResult::success()
            : ValidationResult::failure($errors);
    }

    /**
     * @param array<string, mixed> $data
     * @param class-string $dtoClass
     */
    public function validateArray(array $data, string $dtoClass): ValidationResult
    {
        $errors = [];

        foreach ($this->rulesFor($dtoClass) as $fieldName => $entry) {
            $value = array_key_exists($fieldName, $data) ? $data[$fieldName] : null;

            $fieldErrors = $this->applyRules($entry['rules'], $value);
            if ($fieldErrors !== []) {
                $errors[$fieldName] = $fieldErrors;
            }
        }

        return $errors === []
            ? ValidationResult::success()
            : ValidationResult::failure($errors);
    }

    /**
     * Validate an object and, recursively, any nested Model (or list of
     * Models) it holds. Nested errors are keyed with a dotted path, e.g.
     * "Address.City" or "Lines.0.Quantity".
     *
     * @return array<string, string[]>
     */
    private function collectErrors(object $dto, string $prefix): array
    {
        $errors = [];

        foreach ($this->rulesFor($dto::class) as $fieldName => $entry) {
            $property = $entry['property'];
            $value = $property->isInitialized($dto) ? $property->getValue($dto) : null;

            $fieldErrors = $this->applyRules($entry['rules'], $value);
            if ($fieldErrors !== []) {
                $errors[$prefix . $fieldName] = $fieldErrors;
            }

            if ($value instanceof Model) {
                $errors += $this->collectErrors($value, $prefix . $fieldName . '.');
            } elseif (is_array($value)) {
                foreach ($value as $index => $item) {
                    if ($item instanceof Model) {
                        $errors += $this->collectErrors($item, $prefix . $fieldName . '.' . $index . '.');
                    }
                }
            }
        }

        return $errors;
    }

    /**
     * @param class-string $class
     * @return array<string, array{property: ReflectionProperty, rules: object[]}>
     */
    private function rulesFor(string $class): array
    {
        if (!isset($this->ruleCache[$class])) {
            $entries = [];

            foreach ((new ReflectionClass($class))->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
                if ($property->isStatic()) {
                    continue;
                }

                $rules = [];

                foreach ($property->getAttributes() as $attribute) {
                    // Only instantiate attributes that can be rules: anything
                    // implementing RuleInterface, or (for backwards
                    // compatibility) any attribute class with a validate() method.
                    $name = $attribute->getName();

                    // An attribute that names no class is a typo or a missing
                    // `use` — the rule would otherwise silently not apply.
                    if (!class_exists($name)) {
                        throw new \LogicException(sprintf(
                            'Attribute #[%s] on %s::$%s does not exist. Is a "use" statement missing?',
                            $name,
                            $class,
                            $property->getName(),
                        ));
                    }

                    if (is_a($name, RuleInterface::class, true) || method_exists($name, 'validate')) {
                        $rules[] = $attribute->newInstance();
                    }
                }

                $entries[$property->getName()] = ['property' => $property, 'rules' => $rules];
            }

            $this->ruleCache[$class] = $entries;
        }

        return $this->ruleCache[$class];
    }

    /**
     * @param object[] $rules
     * @return string[]
     */
    private function applyRules(array $rules, mixed $value): array
    {
        $errors = [];

        foreach ($rules as $rule) {
            if (!$rule->validate($value)) {
                $errors[] = property_exists($rule, 'message') ? (string) $rule->message : 'Invalid value';
            }
        }

        return $errors;
    }
}
