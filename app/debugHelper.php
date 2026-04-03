<?php

if (! function_exists('ddc')) {
    function ddc(mixed ...$vars): void
    {
        // DELETE these two lines — they interfere with Laravel's response headers
        // header('Access-Control-Allow-Origin: http://localhost:3000');
        // header('Access-Control-Allow-Credentials: true');
        dd(...$vars);
    }
}
