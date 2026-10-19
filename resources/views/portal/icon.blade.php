@php
    $paths = [
        'chat' => 'M21 11a9 9 0 0 1-9 9 10 10 0 0 1-4-.9L3 21l1.9-5a9 9 0 1 1 16.1-5Z',
        'users' => 'M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2 M16 3a4 4 0 0 1 0 8 M22 21v-2a4 4 0 0 0-3-4 M13 7a4 4 0 1 1-8 0 4 4 0 0 1 8 0',
        'coin' => 'M12 6v12 M15 8H10a2 2 0 0 0 0 4h4a2 2 0 0 1 0 4H9 M22 12a10 10 0 1 1-20 0 10 10 0 0 1 20 0',
        'chart' => 'M3 3v18h18 M7 14l4-4 4 3 6-7',
        'checklist' => 'M9 5h12 M9 12h12 M9 19h12 M3 5h1 M3 12h1 M3 19h1',
        'folder' => 'M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2v11Z',
        'shield' => 'M12 22s8-4 8-11V5l-8-3-8 3v6c0 7 8 11 8 11Z m-4-10 3 3 5-6',
        'back' => 'M19 12H5 m6-6-6 6 6 6',
        'logout' => 'M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4 M16 17l5-5-5-5 M21 12H9',
        'send' => 'm22 2-7 20-4-9-9-4 20-7Z M22 2 11 13',
        'spark' => 'm12 3 2.3 6.7L21 12l-6.7 2.3L12 21l-2.3-6.7L3 12l6.7-2.3L12 3Z',
        'arrow-up-right' => 'M7 17 17 7 M7 7h10v10',
        'plus' => 'M12 5v14 M5 12h14',
        'history' => 'M3 11a9 9 0 1 1 2.6 7.4 M3 4v7h7 M12 7v5l3 2',
        'trash' => 'M3 6h18 M9 6V4h6v2 M5 6l1 14h12l1-14 M10 10v6 M14 10v6',
        'moon' => 'M20.8 13A9 9 0 0 1 11 3.2 9 9 0 1 0 20.8 13Z',
        'lock' => 'M7 10V7a5 5 0 0 1 10 0v3 M5 10h14v11H5z M12 14v3',
        'copy' => 'M9 9h12v12H9z M15 5V3H3v12h2',
        'check' => 'm5 12 4 4L19 6',
        'download' => 'M12 3v12 m-5-5 5 5 5-5 M5 16v5h14v-5',
        'chevron' => 'm6 9 6 6 6-6',
    ];
@endphp
<svg class="icon" viewBox="0 0 24 24" aria-hidden="true"><path d="{{ $paths[$icon ?? 'spark'] ?? $paths['spark'] }}"/></svg>
