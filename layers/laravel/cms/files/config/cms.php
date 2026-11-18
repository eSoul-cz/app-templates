<?php

declare(strict_types=1);

return [
    /*
     * Bearer token of GET /cms-api/v1/export. Read by the frontend build (Jenkins, local development),
     * read-only. An empty token disables the export (404).
     */
    'export_token' => env('CMS_EXPORT_TOKEN'),

    /*
     * Addresses (IP or CIDR, comma separated) that may call /cms-api: the build agent fetches the export and
     * Jenkins reports the publish result. The build uses the public URL, so a changed agent address breaks
     * publishing until it is added here. Empty = no restriction.
     */
    'api_allowed_ips' => array_values(array_filter(array_map(trim(...), explode(',', (string) env('CMS_API_ALLOWED_IPS', ''))))),

    /*
     * Disks. `media` holds the uploads of the admin (private, never served directly). `public` is the content
     * addressed copy that the export writes and the web server serves at /_cms/.
     */
    'media_root' => storage_path('app/cms-media'),
    'public_media_root' => env('CMS_PUBLIC_MEDIA_ROOT') ?: storage_path('app/cms-public'),

    /*
     * The Publish button starts a Jenkins job that builds and deploys the static site.
     */
    'jenkins' => [
        'job_url' => env('CMS_JENKINS_JOB_URL'),
        'user' => env('CMS_JENKINS_USER'),
        'api_token' => env('CMS_JENKINS_API_TOKEN'),
        // Token with which Jenkins reports the result from its `post {}` block.
        'callback_token' => env('CMS_JENKINS_CALLBACK_TOKEN'),
        // A publish without a result for longer than this many minutes is marked failed, so it cannot block
        // the next publish forever (the callback is best effort).
        'timeout_minutes' => env('CMS_PUBLISH_TIMEOUT', 30),
    ],
];
