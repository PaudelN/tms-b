<?php

if (! function_exists('ddc')) {
    function ddc(mixed ...$vars): void
    {
        header('Access-Control-Allow-Origin: http://localhost:3000');
        header('Access-Control-Allow-Credentials: true');
        dd(...$vars);
    }
}
