<?php

declare(strict_types=1);

namespace App\Domain\Cms\Blocks;

/** What a block field holds; the admin form picks its input from it and the validator its rule. */
enum BlockFieldType: string
{
    /** One line of text. */
    case Text = 'text';

    /** Several lines of plain text. */
    case Textarea = 'textarea';

    /** One of `options`. */
    case Choice = 'choice';

    /** Path of an image on the cms-media disk. */
    case Image = 'image';
}
