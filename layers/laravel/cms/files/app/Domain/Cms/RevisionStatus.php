<?php

declare(strict_types=1);

namespace App\Domain\Cms;

/**
 * What became of the content of a revision: `Applied` is content a page had (the history), `Pending` is a proposal
 * of an assistant for a published page that waits for a person to apply it in the admin (it never reached the page),
 * `Discarded` is a proposal a person turned down.
 */
enum RevisionStatus: string
{
    case Applied = 'applied';
    case Pending = 'pending';
    case Discarded = 'discarded';
}
