<?php

declare(strict_types=1);

namespace ESoul\AppTemplates;

/** What a publish did (or, for a dry run, would do). */
final readonly class PublishResult
{
    public const string CREATED = 'created';

    public const string UPDATED = 'updated';

    public const string UNCHANGED = 'unchanged';

    /**
     * @param self::CREATED|self::UPDATED|self::UNCHANGED $status `created`: the branch did not exist yet; `unchanged`: the remote tree already equals the build
     * @param string|null                                 $commit the commit that was (or, for a dry run, would be) pushed; null when unchanged
     */
    public function __construct(
        public string $status,
        public ?string $commit,
        public int $files,
        public bool $pushed,
    ) {
    }
}
