<?php

declare(strict_types=1);

namespace App\Domain\CQRS\Command\Cms;

use App\Application\Contract\Cms\PublicMediaStore;
use App\Application\Dto\Cms\StoredImage;
use App\Domain\Cms\InvalidPageImage;
use Esoul\Cqrs\Attributes\HandlesCommand;
use Esoul\Cqrs\Contracts\CommandHandlerInterface;
use Esoul\Cqrs\Contracts\CommandInterface;
use Illuminate\Contracts\Filesystem\Factory as Filesystems;
use LogicException;

#[HandlesCommand(StorePageImageCommand::class)]
final readonly class StorePageImageCommandHandler implements CommandHandlerInterface
{
    /** What the FileUpload of the page form accepts (`maxSize(10240)` is in kilobytes). */
    public const int MAX_BYTES = 10240 * 1024;

    /** A decoded image this large in pixels can exhaust memory when the site build resizes it. */
    public const int MAX_SIDE = 8000;

    private const array EXTENSIONS = ['png', 'jpg', 'jpeg', 'webp', 'avif'];

    private const array MIMES = ['image/png', 'image/jpeg', 'image/webp', 'image/avif'];

    public function __construct(private Filesystems $disks, private PublicMediaStore $media) {}

    /** @throws InvalidPageImage */
    public function handle(CommandInterface $command): StoredImage
    {
        if ( ! $command instanceof StorePageImageCommand) {
            throw new LogicException('Unsupported command: ' . $command::class);
        }

        $problems = [];
        $extension = strtolower(pathinfo($command->filename, PATHINFO_EXTENSION));
        $bytes = strlen($command->contents);

        if ( ! in_array($extension, self::EXTENSIONS, true)) {
            $problems[] = 'The file name must end with .png, .jpg, .jpeg, .webp or .avif.';
        }

        if ($bytes === 0) {
            $problems[] = 'The image is empty.';
        } elseif ($bytes > self::MAX_BYTES) {
            $problems[] = 'The image must be at most ' . (self::MAX_BYTES / 1024 / 1024) . ' MB.';
        }

        if ($problems !== []) {
            throw new InvalidPageImage($problems);
        }

        // The type comes from the bytes, never from the file name or what the caller claims.
        $mime = (string) new \finfo(FILEINFO_MIME_TYPE)->buffer($command->contents);
        $size = in_array($mime, self::MIMES, true) ? @getimagesizefromstring($command->contents) : false;

        if ($size === false) {
            throw new InvalidPageImage(['The content is not an image of an accepted type (png, jpeg, webp or avif).']);
        }

        [$width, $height] = $size;

        if ($width > self::MAX_SIDE || $height > self::MAX_SIDE) {
            throw new InvalidPageImage(['The image is ' . $width . ' x ' . $height . ' px; at most ' . self::MAX_SIDE . ' x ' . self::MAX_SIDE . ' px is accepted.']);
        }

        $name = $this->media->name(hash('sha256', $command->contents), $mime) ?? throw new InvalidPageImage(['The image type cannot be served by the site.']);
        $path = 'images/' . $name;

        $this->disks->disk(PublicMediaStore::MEDIA_DISK)->put($path, $command->contents, 'public');

        return new StoredImage($path, $mime, $width, $height, $bytes);
    }
}
