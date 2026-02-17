<?php

declare(strict_types=1);

namespace Melodic\Validation;

use ReflectionClass;
use ReflectionProperty;

class Validator
{
    public function validate(object $dto): ValidationResult
    {
        $reflector = new ReflectionClass($dto);
        $errors = [];

        foreach ($reflector->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            $fieldName = $property->getName();
            $value = $property->isInitialized($dto) ? $property->getValue($dto) : null;

            $fieldErrors = $this->validateProperty($property, $value);
            if ($fieldErrors !== []) {
                $errors[$fieldName] = $fieldErrors;
            }
        }

        return $errors === []
            ? ValidationResult::success()
            : ValidationResult::failure($errors);
    }

    /**
     * @param class-string $dtoClass
     */
    public function validateArray(array $data, string $dtoClass): ValidationResult
    {
        $reflector = new ReflectionClass($dtoClass);
        $errors = [];

        foreach ($reflector->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            $fieldName = $property->getName();
            $value = array_key_exists($fieldName, $data) ? $data[$fieldName] : null;

            $fieldErrors = $this->validateProperty($property, $value);
            if ($fieldErrors !== []) {
                $errors[$fieldName] = $fieldErrors;
            }
        }

        return $errors === []
            ? ValidationResult::success()
            : ValidationResult::failure($errors);
    }

    /**
     * @return string[]
     */
    private function validateProperty(ReflectionProperty $property, mixed $value): array
    {
        $errors = [];

        foreach ($property->getAttributes() as $attribute) {
            $instance = $attribute->newInstance();

            if (method_exists($instance, 'validate') && !$instance->validate($value)) {
                $errors[] = $instance->message;
            }
        }

        return $errors;
    }
}
