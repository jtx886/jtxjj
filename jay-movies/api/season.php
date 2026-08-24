<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/tmdb.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: public, max-age=86400');

$tmdb   = new TMDB();
$tvId   = isset($_GET['tv_id']) ? intval($_GET['tv_id']) : 0;
$seasonNum = isset($_GET['season']) ? intval($_GET['season']) : 1;

if (!$tvId || $seasonNum < 1) {
    echo json_encode(['success' => false, 'message' => '参数错误', 'episodes' => []], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $season = $tmdb->getSeasonDetails($tvId, $seasonNum);
    $episodesRaw = isset($season['episodes']) ? $season['episodes'] : [];
    $eps = [];
    $imgMap = [];
    foreach ($episodesRaw as $idx => $ep) {
        $num = intval($ep['episode_number'] ?? ($idx + 1));
        $name = trim($ep['name'] ?? '');
        if ($name === '') $name = '第 ' . $num . ' 集';
        $still = $ep['still_path'] ?? '';
        $stillUrl = $still ? $tmdb->getImageUrl($still, 'w300') : '';
        $air = $ep['air_date'] ?? '';
        $rating = isset($ep['vote_average']) ? floatval($ep['vote_average']) : 0;
        $overview = trim($ep['overview'] ?? '');
        $runtime = intval($ep['runtime'] ?? 0);
        $eps[] = [
            'num'         => $num,
            'name'        => $name,
            'still'       => $stillUrl,
            'air_date'    => $air,
            'rating'      => $rating ? number_format($rating, 1) : '0.0',
            'rating_raw'  => $rating,
            'overview'    => $overview ?: '暂无简介',
            'runtime'     => $runtime,
            'play_url'    => 'play.php?type=tv&id=' . $tvId . '&season=' . $seasonNum . '&episode=' . $num . '&name=' . urlencode($name),
        ];
    }

    $seasonInfo = [
        'name'           => $season['name'] ?? ('第 ' . $seasonNum . ' 季'),
        'overview'       => trim($season['overview'] ?? '') ?: '暂无该季简介',
        'air_date'       => $season['air_date'] ?? '',
        'poster_path'    => isset($season['poster_path']) ? $tmdb->getImageUrl($season['poster_path'], 'w342') : '',
        'episode_count'  => count($eps),
        'season_number'  => $seasonNum,
    ];
    echo json_encode([
        'success'  => true,
        'tv_id'    => $tvId,
        'season'   => $seasonNum,
        'season_info' => $seasonInfo,
        'episodes' => $eps,
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    @file_put_contents(__DIR__ . '/../data/season_error.log',
        '[' . date('Y-m-d H:i:s') . '] ' . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n", FILE_APPEND);
    echo json_encode(['success' => false, 'message' => '加载失败: ' . $e->getMessage(), 'episodes' => []], JSON_UNESCAPED_UNICODE);
}
