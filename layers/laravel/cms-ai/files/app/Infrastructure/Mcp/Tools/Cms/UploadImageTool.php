<?php

declare(strict_types=1);

namespace App\Infrastructure\Mcp\Tools\Cms;

use App\Domain\AiConnector\ConnectorScope;
use App\Infrastructure\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use App\Domain\Cms\InvalidPageImage;
use App\Domain\CQRS\Command\Cms\StorePageImageCommand;
use App\Domain\CQRS\Command\Cms\StorePageImageCommandHandler;
use Esoul\LaravelCqrs\Facades\CommandBus;

#[Name('upload_image')]
#[Title('Upload an image')]
#[Description('Uploads an image (png, jpg, webp or avif, at most 10 MB and 8000 x 8000 px) from base64 content and returns the path to use in an image block or as og_image. The content must be a real image whatever the file name says. It cannot fetch a URL.')]
#[IsDestructive(false)]
#[IsIdempotent]
final class UploadImageTool extends CmsTool
{
    protected function requiredScope(): ConnectorScope
    {
        return ConnectorScope::ContentWrite;
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'filename' => $schema->string()->description('File name with its extension, for example hero.jpg.')->required(),
            'content_base64' => $schema->string()->description('The image bytes, base64 encoded.')->required(),
        ];
    }

    protected function execute(Request $request, User $user): Response|ResponseFactory
    {
        $this->authorizeEdit($user);

        if (($refused = $this->unknownArguments($request, ['filename', 'content_base64'])) instanceof Response) {
            return $refused;
        }

        $arguments = $request->validate([
            'filename' => ['required', 'string', 'max:255'],
            // Base64 is 4/3 of the bytes: refuse a huge string before it is decoded.
            'content_base64' => ['required', 'string', 'max:' . (int) ceil(StorePageImageCommandHandler::MAX_BYTES * 4 / 3 + 4)],
        ]);

        $contents = base64_decode($arguments['content_base64'], true);

        if ($contents === false) {
            return $this->refused(['content_base64 is not valid base64.'], 'The image was not stored');
        }

        try {
            $image = CommandBus::dispatch(new StorePageImageCommand($contents, $arguments['filename']));
        } catch (InvalidPageImage $invalid) {
            return $this->refused($invalid->problems, 'The image was not stored');
        }

        return Response::structured(['path' => $image->path, 'mime' => $image->mime, 'width' => $image->width, 'height' => $image->height, 'bytes' => $image->bytes]);
    }
}
