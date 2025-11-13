<?php
// inc/path_helper.php
if (!function_exists('getWebBaseUrl')) {
    /**
     * Returns the web-accessible base URL for a SEA upload folder.
     *
     * Works for:
     *   XAMPP  → https://localhost/ea-web/data/uploads/SEA/<id>/
     *   Prod   → https://yourdomain.com/data/uploads/SEA/<id>/
     *
     * @param string $sea_id  (already sanitized)
     * @return string
     */
    function getWebBaseUrl(string $sea_id): string
    {
        // 1. Find the project root relative to the *current script*
        $scriptDir   = dirname($_SERVER['SCRIPT_NAME']);           // e.g. /ea-web/src
        $projectRoot = dirname($scriptDir);                        // e.g. /ea-web

        // 2. Build the path from the root to the uploads folder
        $base = rtrim($projectRoot, '/') . '/data/uploads/SEA/' . $sea_id;

        // 3. Return a **relative** path that works from the /public folder
        //     → ../data/uploads/SEA/<id>/
        // return '/data/uploads/SEA/' . $sea_id;
        return $base;
    }
}