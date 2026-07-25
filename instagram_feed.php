<?php
// instagram_feed.php
// Fetches the 4 latest Instagram posts and caches them for 1 hour.

function getInstagramFeed($token, $limit = 4) {
    $cacheFile = __DIR__ . '/cache/instagram.json';
    $cacheTime = 3600; // 1 hour

    // Return cached data if fresh
    if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < $cacheTime) {
        $data = json_decode(file_get_contents($cacheFile), true);
        if (!empty($data)) return $data;
    }

    // Fetch from Instagram
    $url = "https://graph.instagram.com/me/media?fields=id,caption,media_type,media_url,permalink,timestamp&limit={$limit}&access_token={$token}";
    $response = @file_get_contents($url);
    if ($response === false) return false;
    $data = json_decode($response, true);
    if (!isset($data['data'])) return false;

    // Keep only the first $limit items
    $data['data'] = array_slice($data['data'], 0, $limit);

    // Save to cache
    file_put_contents($cacheFile, json_encode($data));

    return $data;
}