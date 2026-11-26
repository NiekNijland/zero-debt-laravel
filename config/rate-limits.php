<?php

declare(strict_types=1);

return [
    'read' => (int) env('RATE_LIMIT_READ', 120),
    'write' => (int) env('RATE_LIMIT_WRITE', 30),
];
