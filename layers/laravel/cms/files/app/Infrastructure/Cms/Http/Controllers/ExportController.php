<?php

declare(strict_types=1);

namespace App\Infrastructure\Cms\Http\Controllers;

use App\Application\Cms\ContentExporter;
use App\Infrastructure\Cms\PublicMedia;
use Illuminate\Http\JsonResponse;

/**
 * GET /cms-api/v1/export: content for the build of the static site. The media files the export references are on
 * /_cms/ when the response is sent: the build does not download them, it only checks that the web server serves them.
 */
final readonly class ExportController
{
    public function __invoke(ContentExporter $exporter, PublicMedia $media): JsonResponse
    {
        $export = $exporter->build();

        // The same rules as the frontend schema: better a clear 422 here than a failing build.
        if ($export->problems !== []) {
            return response()->json(['message' => 'The content does not satisfy the export contract.', 'problems' => $export->problems], 422);
        }

        // A write failure is a 500 and the site build fails: the site must not link a file that does not exist.
        $media->store($export->files);

        return response()->json($export->payload, options: JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
