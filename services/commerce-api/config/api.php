<?php

return [
    'documentation' => [
        'enabled' => (bool) env('API_DOCS_ENABLED', false),
    ],
    'csv_import' => [
        'max_bytes' => (int) env('CSV_MAX_BYTES', 5 * 1024 * 1024),
        'max_rows' => (int) env('CSV_MAX_ROWS', 10_000),
    ],
];
