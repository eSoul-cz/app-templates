<?php

declare(strict_types=1);

namespace App\Domain\CQRS\Query\Cms;

use App\Application\Dto\Cms\PageChange;
use Esoul\Cqrs\Contracts\QueryInterface;

/**
 * The pages that changed since the last successful publish started (the build reads the export at its start),
 * newest first, so that a person sees what an AI assistant changed before publishing. Without a successful
 * publish every page that is not deleted counts. A page deleted since the publish is listed as deleted.
 *
 * @implements QueryInterface<list<PageChange>>
 */
final readonly class GetChangesSincePublishQuery implements QueryInterface {}
