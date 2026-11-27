<?php

declare(strict_types=1);

namespace App\Domain\Cms\Blocks;

/**
 * Checks the `blocks` of a page against the catalogue: a list of `{type, data}` items, a known type, only
 * the fields of that type, every field valid. Anything else is refused, never stored.
 */
final readonly class BlockValidator
{
    public function __construct(private BlockCatalogue $catalogue) {}

    /**
     * @return list<string> the problems, each naming the block (1-based); empty when the blocks are valid
     */
    public function validate(mixed $blocks): array
    {
        if ($blocks === null) {
            return [];
        }

        if ( ! is_array($blocks)) {
            return ['The blocks must be a list.'];
        }

        $problems = [];

        foreach (array_values($blocks) as $index => $block) {
            array_push($problems, ...$this->block($block, 'Block ' . ($index + 1)));
        }

        return $problems;
    }

    /** @return list<string> */
    private function block(mixed $block, string $label): array
    {
        if ( ! is_array($block) || ! is_string($block['type'] ?? null) || ! is_array($block['data'] ?? null)) {
            return ["{$label}: a block needs a type and data."];
        }

        $extra = array_diff(array_keys($block), ['type', 'data']);

        if ($extra !== []) {
            return ["{$label}: unknown key " . implode(', ', array_map(strval(...), $extra)) . '.'];
        }

        $type = $this->catalogue->find($block['type']);

        if ($type === null) {
            return ["{$label}: unknown block type \"{$block['type']}\"."];
        }

        $problems = [];
        $label .= " ({$type->name})";

        foreach (array_keys($block['data']) as $key) {
            if ($type->field((string) $key) === null) {
                $problems[] = "{$label}: unknown field \"{$key}\".";
            }
        }

        foreach ($type->fields as $field) {
            $problem = $field->problem($block['data'][$field->name] ?? null);

            if ($problem !== null) {
                $problems[] = "{$label}: the field \"{$field->name}\" {$problem}.";
            }
        }

        return $problems;
    }
}
