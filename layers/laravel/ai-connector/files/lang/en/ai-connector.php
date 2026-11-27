<?php

declare(strict_types=1);

return [
    'consent' => [
        'title' => 'Connect an assistant',
        'heading' => ':client wants to connect to :app',
        'signed_in_as' => 'You are signed in as :user.',
        'lead' => 'If you approve, the assistant works in :app as you: it sees what you see and may do what you may do, and nothing more.',
        'can_heading' => 'The assistant will be able to',
        'cannot_heading' => 'The assistant will not be able to',
        'cannot' => [
            'Change the code, templates, settings or deployment of the application.',
            'Manage users, roles or permissions.',
            'Delete data permanently.',
            'Do anything that you are not allowed to do yourself.',
        ],
        'data_notice' => 'What the assistant reads is sent to the AI provider you use it with (for example Anthropic or OpenAI) and falls under your terms with them.',
        'revoke_notice' => 'You can disconnect the assistant at any time in the administration, under "Connected assistants". Every action of the assistant is recorded.',
        'returns_to' => 'After your decision you are sent back to :host.',
        'approve' => 'Approve',
        'cancel' => 'Cancel',
    ],
    'scopes' => [
        'content:read' => 'Read content: look up and read what you can see in the administration.',
        'content:write' => 'Change content: create and edit content in the same way you can in the administration. The changes are recorded under your name.',
    ],
    'page' => [
        'navigation' => 'Connected assistants',
        'title' => 'Connected assistants',
        'description' => 'Assistants (Claude, ChatGPT) that you have connected to this application. Disconnecting takes effect immediately.',
        'client' => 'Assistant',
        'user' => 'User',
        'scopes' => 'Permissions',
        'connected_at' => 'Connected',
        'last_used_at' => 'Last used',
        'never' => 'Not used yet',
        'everybody' => 'Show the connections of all users',
        'revoke' => 'Disconnect',
        'revoke_heading' => 'Disconnect this assistant?',
        'revoke_description' => 'The assistant loses access immediately. It can be connected again at any time.',
        'revoked' => 'The assistant was disconnected.',
        'not_found' => 'This connection no longer exists.',
        'empty_heading' => 'No assistant is connected',
        'empty_description' => 'Connect Claude or ChatGPT with the connector address :url (see the client guide).',
    ],
];
