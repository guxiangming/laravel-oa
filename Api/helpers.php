<?php


if (! function_exists('version')) {
    function version($version)
    {
        return app('api.url')->version($version);
    }
}
