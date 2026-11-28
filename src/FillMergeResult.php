<?php

declare(strict_types=1);

namespace ESoul\AppTemplates;

/** Outcome of {@see FillMerger::merge()}. */
final readonly class FillMergeResult
{
    /**
     * @param mixed                                          $value merged value
     * @param list<array{path: list<string>, value: mixed}>  $added keys (and list items, path ending in `[]`) that were added
     * @param list<string>                                   $kept  JSON paths whose existing, different value was kept
     */
    public function __construct(public mixed $value, public array $added, public array $kept) {
    }

    public function changed(): bool {
        return $this->added !== [];
    }
}
