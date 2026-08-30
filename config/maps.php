<?php

return [
    'embed_base_url' => env('MAP_EMBED_BASE_URL', 'https://www.openstreetmap.org/export/embed.html'),
    'tile_url' => env('MAP_TILE_URL', 'https://tile.openstreetmap.org/{z}/{x}/{y}.png'),
    'tile_origin' => env('MAP_TILE_ORIGIN', 'https://tile.openstreetmap.org'),
    'public_base_url' => env('MAP_PUBLIC_BASE_URL', 'https://www.openstreetmap.org'),
    'frame_origin' => env('MAP_FRAME_ORIGIN', 'https://www.openstreetmap.org'),
];
