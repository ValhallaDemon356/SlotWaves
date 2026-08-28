<?php

$urls = [
    'http://127.0.0.1:8000/',
    'http://127.0.0.1:8000/master-data',
    'http://127.0.0.1:8000/schedule/1/dashboard',
    'http://127.0.0.1:8000/timeline/1',
    'http://127.0.0.1:8000/schedule/1/preview/time',
    'http://127.0.0.1:8000/schedule/1/preview/dos',
];

foreach ($urls as $url) {
    $ctx = stream_context_create([
        'http' => [
            'timeout' => 8,
            'ignore_errors' => true,
            'header' => "Connection: close\r\nUser-Agent: PHP-Test\r\n"
        ]
    ]);
    $start = microtime(true);
    $fp = @fopen($url, 'r', false, $ctx);
    if ($fp) {
        $meta = stream_get_meta_data($fp);
        $statusLine = $meta['wrapper_data'][0] ?? 'UNKNOWN';
        $duration = round((microtime(true) - $start) * 1000, 1);
        echo "$url => $statusLine ({$duration}ms)\n";
        fclose($fp);
    } else {
        echo "$url => FAILED TO CONNECT\n";
    }
}
