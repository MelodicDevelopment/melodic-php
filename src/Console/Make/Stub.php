<?php

declare(strict_types=1);

namespace Melodic\Console\Make;

class Stub
{
    /**
     * Replace placeholders in a template string.
     *
     * @param array<string, string> $replacements
     */
    public static function render(string $template, array $replacements): string
    {
        return str_replace(
            array_map(fn(string $key) => "{{$key}}", array_keys($replacements)),
            array_values($replacements),
            $template,
        );
    }

    /**
     * Whether a scaffolding name is a safe identifier once case-split: letters
     * and digits only, starting with a letter. Anything else (path separators,
     * dots, quotes, semicolons, ...) could escape the target directory or
     * corrupt the generated code, so commands reject it before any file write.
     */
    public static function isValidIdentifier(string $input): bool
    {
        return preg_match('/^[A-Za-z][A-Za-z0-9]*$/', self::pascalCase($input)) === 1;
    }

    public static function pascalCase(string $input): string
    {
        // Handle snake_case, kebab-case, and space-separated
        $words = preg_split('/[-_\s]+/', $input);

        if ($words === false) {
            return ucfirst($input);
        }

        return implode('', array_map(fn(string $word) => ucfirst(strtolower($word)), $words));
    }

    public static function camelCase(string $input): string
    {
        return lcfirst(self::pascalCase($input));
    }

    public static function snakeCase(string $input): string
    {
        // Insert underscore before uppercase letters that follow lowercase letters
        $result = preg_replace('/([a-z])([A-Z])/', '$1_$2', $input);

        if ($result === null) {
            return strtolower($input);
        }

        // Replace dashes and spaces with underscores
        $result = preg_replace('/[-\s]+/', '_', $result);

        return strtolower($result ?? $input);
    }

    public static function pluralize(string $word): string
    {
        if ($word === '') {
            return '';
        }

        // Irregular plurals
        $irregulars = [
            'person' => 'people',
            'child' => 'children',
            'man' => 'men',
            'woman' => 'women',
            'mouse' => 'mice',
            'goose' => 'geese',
            'ox' => 'oxen',
            'foot' => 'feet',
            'tooth' => 'teeth',
            'datum' => 'data',
            'index' => 'indices',
            'quiz' => 'quizzes',
            'status' => 'statuses',
            'campus' => 'campuses',
        ];

        $lower = strtolower($word);
        if (isset($irregulars[$lower])) {
            // Preserve original casing of the first character
            $plural = $irregulars[$lower];
            return ctype_upper($word[0]) ? ucfirst($plural) : $plural;
        }

        // Words ending in 's', 'sh', 'ch', 'x', 'z' → add 'es'
        if (preg_match('/(s|sh|ch|x|z)$/i', $word)) {
            return $word . 'es';
        }

        // Words ending in consonant + 'y' → replace 'y' with 'ies'
        if (preg_match('/[^aeiou]y$/i', $word)) {
            return substr($word, 0, -1) . 'ies';
        }

        // Words ending in 'f' or 'fe' → replace with 'ves'
        if (preg_match('/fe?$/i', $word)) {
            return preg_replace('/fe?$/i', 'ves', $word) ?? $word . 's';
        }

        // Default: add 's'
        return $word . 's';
    }
}
