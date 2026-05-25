<?php

namespace App\Helpers;

class UrlHelper
{
    /**
     * Get the dynamic webhook URL.
     *
     * @param string $path
     * @return string
     */
    public static function getWebhookUrl(string $path = ''): string
    {
        // Use NGROK only in local environment and if the variable is set
        if (app()->environment('local') && env('NGROK_LINK_LOCAL_HOST')) {
            return rtrim(env('NGROK_LINK_LOCAL_HOST'), '/') . '/' . ltrim($path, '/');
        }

        // For production or any real domain, use the Laravel url() helper
        return url($path);
    }
}