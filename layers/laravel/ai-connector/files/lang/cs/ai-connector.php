<?php

declare(strict_types=1);

return [
    'consent' => [
        'title' => 'Připojení asistenta',
        'heading' => ':client se chce připojit k aplikaci :app',
        'signed_in_as' => 'Jste přihlášen(a) jako :user.',
        'lead' => 'Pokud přístup potvrdíte, bude asistent v aplikaci :app pracovat jako vy: uvidí to, co vidíte vy, a smí jen to, co smíte vy.',
        'can_heading' => 'Asistent bude moci',
        'cannot_heading' => 'Asistent nebude moci',
        'cannot' => [
            'Měnit kód, šablony, nastavení ani nasazení aplikace.',
            'Spravovat uživatele, role ani oprávnění.',
            'Trvale mazat data.',
            'Dělat cokoli, k čemu nemáte oprávnění vy sami.',
        ],
        'data_notice' => 'Co asistent přečte, odchází k poskytovateli AI, se kterým ho používáte (například Anthropic nebo OpenAI), a řídí se vašimi podmínkami u něj.',
        'revoke_notice' => 'Asistenta můžete kdykoli odpojit v administraci v sekci „Připojení asistenti“. Každá akce asistenta se zaznamenává.',
        'returns_to' => 'Po vašem rozhodnutí budete přesměrováni na :host.',
        'approve' => 'Povolit',
        'cancel' => 'Zrušit',
    ],
    'scopes' => [
        'content:read' => 'Číst obsah: vyhledat a přečíst to, co vidíte v administraci.',
        'content:write' => 'Měnit obsah: vytvářet a upravovat obsah stejně, jako to můžete v administraci. Změny se zaznamenají pod vaším jménem.',
    ],
    'page' => [
        'navigation' => 'Připojení asistenti',
        'title' => 'Připojení asistenti',
        'description' => 'Asistenti (Claude, ChatGPT), které jste připojili k této aplikaci. Odpojení platí okamžitě.',
        'client' => 'Asistent',
        'user' => 'Uživatel',
        'scopes' => 'Oprávnění',
        'connected_at' => 'Připojeno',
        'last_used_at' => 'Naposledy použito',
        'never' => 'Zatím nepoužito',
        'everybody' => 'Zobrazit připojení všech uživatelů',
        'revoke' => 'Odpojit',
        'revoke_heading' => 'Odpojit tohoto asistenta?',
        'revoke_description' => 'Asistent okamžitě ztratí přístup. Kdykoli ho můžete připojit znovu.',
        'revoked' => 'Asistent byl odpojen.',
        'not_found' => 'Toto připojení už neexistuje.',
        'empty_heading' => 'Není připojen žádný asistent',
        'empty_description' => 'Claude nebo ChatGPT připojíte adresou konektoru :url (viz návod pro klienta).',
    ],
];
