<?php
/**
 * Souborova cache (dashboard, grafy). Driv memcache, ten na hostingu neni.
 */

function cache_file($key)
{
    $dir = config('cache_dir', sys_get_temp_dir() . '/legacy_cache');
    if (!is_dir($dir)) {
        @mkdir($dir, 0777, true);
    }

    return $dir . '/' . md5($key) . '.cache';
}

function cache_get($key, $ttl = 300)
{
    if (!empty($GLOBALS['LEGACY_NO_CACHE'])) {
        return null;
    }
    $f = cache_file($key);
    if (!file_exists($f)) {
        return null;
    }
    if (filemtime($f) < time() - $ttl) {
        @unlink($f);
        return null;
    }
    $data = @file_get_contents($f);
    if ($data === false) {
        return null;
    }

    return unserialize($data);
}

function cache_set($key, $value)
{
    if (!empty($GLOBALS['LEGACY_NO_CACHE'])) {
        return false;
    }

    return @file_put_contents(cache_file($key), serialize($value)) !== false;
}

function cache_delete($key)
{
    $f = cache_file($key);
    if (file_exists($f)) {
        @unlink($f);
    }
}

/**
 * Smaze celou cache. Vola cron a settings.php.
 */
function cache_clear()
{
    $dir = config('cache_dir', sys_get_temp_dir() . '/legacy_cache');
    if (!is_dir($dir)) {
        return 0;
    }
    $n = 0;
    foreach (glob($dir . '/*.cache') ?: array() as $f) {
        if (@unlink($f)) {
            $n++;
        }
    }

    return $n;
}
