<?php

namespace App\Statements;

use InvalidArgumentException;

/**
 * The text of a statement PDF as the browser extracted it (public/js/statement-text.js):
 * pages of rows, each row a list of text items with their x position in points.
 */
final class StatementText
{
    /** @param list<list<list<array{x: float, s: string}>>> $pages */
    private function __construct(public readonly array $pages) {}

    /** @param mixed $data decoded JSON: {pages: [[{y, items: [{x, s}]}]]} */
    public static function fromArray(mixed $data): self
    {
        if (! is_array($data) || ! isset($data['pages']) || ! is_array($data['pages'])) {
            throw new InvalidArgumentException('Not a statement.');
        }

        $pages = [];
        foreach ($data['pages'] as $page) {
            if (! is_array($page)) {
                throw new InvalidArgumentException('Not a statement.');
            }
            $rows = [];
            foreach ($page as $row) {
                if (! is_array($row) || ! isset($row['items']) || ! is_array($row['items'])) {
                    throw new InvalidArgumentException('Not a statement.');
                }
                $items = [];
                foreach ($row['items'] as $item) {
                    if (! is_array($item) || ! is_numeric($item['x'] ?? null) || ! is_string($item['s'] ?? null)) {
                        throw new InvalidArgumentException('Not a statement.');
                    }
                    $items[] = ['x' => (float) $item['x'], 's' => trim($item['s'])];
                }
                $rows[] = $items;
            }
            $pages[] = $rows;
        }

        return new self($pages);
    }

    /** @return list<list<array{x: float, s: string}>> every row of every page, in order */
    public function rows(): array
    {
        return array_merge(...array_values($this->pages ?: [[]]));
    }

    /** @param list<array{x: float, s: string}> $row */
    public static function join(array $row): string
    {
        return implode(' ', array_column($row, 's'));
    }

    public function contains(string $needle): bool
    {
        foreach ($this->rows() as $row) {
            if (str_contains(self::join($row), $needle)) {
                return true;
            }
        }

        return false;
    }
}
