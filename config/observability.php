<?php

return [
    // Zero disables slow-request warnings. Exception logs still receive the
    // request correlation context installed by RequestContext.
    'slow_request_ms' => (int) env('SLOW_REQUEST_MS', 1500),
];
