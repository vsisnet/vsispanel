<?php

return [
    'port' => (int) env('TERMINAL_PORT', 8022),
    'idle_timeout' => (int) env('TERMINAL_IDLE_TIMEOUT', 1800), // 30 minutes
];
