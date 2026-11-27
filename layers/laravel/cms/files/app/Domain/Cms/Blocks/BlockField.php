<?php

declare(strict_types=1);

namespace App\Domain\Cms\Blocks;

/** One field of a block type: the admin form is built from it and the validator checks the stored data against it. */
final readonly class BlockField
{
    /**
     * @param  array<array-key, string>  $options  `Choice` only: allowed stored value => label
     * @param  string|null  $default  value a new block starts with
     * @param  string|null  $hint  help text of the admin form
     */
    public function __construct(
        public string $name,
        public BlockFieldType $type,
        public bool $required = false,
        public ?int $maxLength = null,
        public array $options = [],
        public ?string $default = null,
        public ?string $hint = null,
    ) {}

    /** The problem with `$value` (the field is present in the data), or null when it is valid. */
    public function problem(mixed $value): ?string
    {
        if ($this->type === BlockFieldType::Choice) {
            return $this->choiceProblem($value);
        }

        if ($value === null) {
            return $this->required ? 'is required' : null;
        }

        if ( ! is_string($value)) {
            return 'must be a string';
        }

        if ($this->required && trim($value) === '') {
            return 'is required';
        }

        if ($this->maxLength !== null && mb_strlen($value) > $this->maxLength) {
            return "must be at most {$this->maxLength} characters";
        }

        return null;
    }

    private function choiceProblem(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return $this->required ? 'is required' : null;
        }

        if ( ! is_string($value) && ! is_int($value)) {
            return 'must be one of: ' . implode(', ', array_map(strval(...), array_keys($this->options)));
        }

        foreach (array_keys($this->options) as $option) {
            if ((string) $option === (string) $value) {
                return null;
            }
        }

        return 'must be one of: ' . implode(', ', array_map(strval(...), array_keys($this->options)));
    }
}
