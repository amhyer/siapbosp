<?php

return [
    'password' => env('BACKUP_PASSWORD', ''),
    'retention_days' => (int) env('BACKUP_RETENTION_DAYS', 30),
];
