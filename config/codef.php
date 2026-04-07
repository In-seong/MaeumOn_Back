<?php

return [
    'client_id' => env('CODEF_CLIENT_ID'),
    'client_secret' => env('CODEF_CLIENT_SECRET'),
    'base_url' => env('CODEF_BASE_URL', 'https://development.codef.io'),
    'oauth_url' => env('CODEF_OAUTH_URL', 'https://oauth.codef.io'),
    'public_key' => env('CODEF_PUBLIC_KEY'),
    'organization' => '0001',
    'timeout' => 300,
    'two_way_timeout' => 270,
    'two_way_cache_ttl' => 300, // 2-Way twoWayInfo 캐시 TTL (초)
];
