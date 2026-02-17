<?php

declare(strict_types=1);

namespace Melodic\Console;

abstract class Command implements CommandInterface
{
    public function __construct(
        private readonly string $name,
        private readonly string $description,
    ) {}

    public function getName(): string
    {
        return $this->name;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    protected function writeln(string $text): void
    {
        echo $text . PHP_EOL;
    }

    protected function write(string $text): void
    {
        echo $text;
    }

    protected function error(string $text): void
    {
        fwrite(STDERR, $text . PHP_EOL);
    }

    /**
     * @param array<string> $headers
     * @param array<array<string>> $rows
     */
    protected function table(array $headers, array $rows): void
    {
        $columns = count($headers);
        $widths = array_fill(0, $columns, 0);

        foreach ($headers as $i => $header) {
            $widths[$i] = max($widths[$i], mb_strlen($header));
        }

        foreach ($rows as $row) {
            foreach ($row as $i => $cell) {
                if ($i < $columns) {
                    $widths[$i] = max($widths[$i], mb_strlen($cell));
                }
            }
        }

        $separator = '+';
        foreach ($widths as $width) {
            $separator .= str_repeat('-', $width + 2) . '+';
        }

        $this->writeln($separator);
        $this->writeln($this->formatRow($headers, $widths));
        $this->writeln($separator);

        foreach ($rows as $row) {
            $this->writeln($this->formatRow($row, $widths));
        }

        $this->writeln($separator);
    }

    /**
     * @param array<string> $cells
     * @param array<int> $widths
     */
    private function formatRow(array $cells, array $widths): string
    {
        $line = '|';

        foreach ($widths as $i => $width) {
            $cell = $cells[$i] ?? '';
            $line .= ' ' . str_pad($cell, $width) . ' |';
        }

        return $line;
    }
}
