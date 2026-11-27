<?php

declare(strict_types=1);

namespace App\Application\Dto\Cms;

/** An uploaded image: `path` goes into an image block or og_image. */
final readonly class StoredImage
{
    public function __construct(
        public string $path,
        public string $mime,
        public int $width,
        public int $height,
        public int $bytes,
    ) {}
}
