<?php

declare(strict_types=1);

namespace App\Domain\CQRS\Command\Cms;

use App\Application\Dto\Cms\StoredImage;
use App\Domain\Cms\InvalidPageImage;
use Esoul\Cqrs\Contracts\CommandInterface;

/**
 * Stores an image on the media disk, where the page form of the admin stores its uploads, under the
 * content-addressed name of PublicMediaStore (the same bytes give the same path). The same rules as the admin
 * upload apply: png, jpeg, webp or avif, at most 10 MB, and the bytes must be a real image, whatever the file
 * name says. Throws {@see InvalidPageImage}; nothing is stored then.
 *
 * @implements CommandInterface<StoredImage>
 */
final readonly class StorePageImageCommand implements CommandInterface
{
    /** @param  string  $contents  the raw bytes of the image */
    public function __construct(public string $contents, public string $filename) {}
}
