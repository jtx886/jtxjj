<?php
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/tmdb.php';

// Must login to watch
requireLogin();

$db = Database::getInstance();
$tmdb = new TMDB();

$type = $_GET['type'] ?? 'movie';
$id = intval($_GET['id'] ?? 0);
$season = intval($_GET['season'] ?? 1);
$episode = intval($_GET['episode'] ?? 1);
$epName = trim($_GET['name'] ?? '');
$lang = $_GET['lang'] ?? 'original';
$srcId = intval($_GET['src'] ?? 0);
$pageTitle = trim($_GET['title'] ?? '');

if(!$id) redirect('index.php');

// Get media info from TMDB
$media = $tmdb->getDetails($type, $id);
if(!$media) { redirect('index.php'); }
$title = $pageTitle ?: (isset($media['title']) ? $media['title'] : ($media['name'] ?? ''));
$poster = $tmdb->getImageUrl($media['poster_path'] ?? '', 'w500');

// Get play source
$sources = getAllPlaySources();
$curSource = getDefaultPlaySource();
if($srcId) {
    foreach($sources as $s) { if($s['id'] == $srcId) { $curSource = $s; break; } }
}
$srcUrl = $curSource['url'];

// Fetch source API to find the play URL using YYZY API
// Build query: search by title
$searchKeyword = urlencode($title);
$playSourceUrl = $srcUrl . '?ac=detail&wd=' . $searchKeyword;
// Also we construct parser URL with title to pass to ffzyplay
// The parser accepts ANY url and we feed it yyzy search result as fallback
$videoUrl = '';

// Try call yyzy api
$resp = null;
$yyzyConnectTimeout = defined('HTTP_CONNECT_TIMEOUT') ? HTTP_CONNECT_TIMEOUT : 3;
$yyzyTotalTimeout   = defined('HTTP_TOTAL_TIMEOUT')   ? HTTP_TOTAL_TIMEOUT   : 6;
if (function_exists('curl_init')) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $playSourceUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, $yyzyTotalTimeout);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $yyzyConnectTimeout);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_NOSIGNAL, 1);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 JayMovies');
    curl_setopt($ch, CURLOPT_ENCODING, 'gzip, deflate');
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    if (!empty($_SERVER['HTTPS_PROXY']))       curl_setopt($ch, CURLOPT_PROXY, $_SERVER['HTTPS_PROXY']);
    elseif (!empty($_SERVER['HTTP_PROXY']))    curl_setopt($ch, CURLOPT_PROXY, $_SERVER['HTTP_PROXY']);
    $raw = curl_exec($ch);
    curl_close($ch);
    if (is_string($raw) && $raw !== '') $resp = $raw;
}
if ($resp === null && ini_get('allow_url_fopen')) {
    $proxy = $_SERVER['HTTPS_PROXY'] ?? ($_SERVER['HTTP_PROXY'] ?? '');
    $ctx_opts = [
        'http' => [
            'method'  => 'GET',
            'header'  => "User-Agent: Mozilla/5.0 JayMovies\r\nAccept: */*\r\n",
            'timeout' => $yyzyTotalTimeout,
            'ignore_errors' => true,
        ],
        'ssl'  => ['verify_peer'=>false,'verify_peer_name'=>false],
    ];
    if ($proxy) {
        $p = parse_url($proxy);
        if (!empty($p['host'])) {
            $ctx_opts['http']['proxy'] = 'tcp://' . $p['host'] . ':' . (isset($p['port']) ? $p['port'] : 80);
            $ctx_opts['http']['request_fulluri'] = true;
        }
    }
    $ctx = stream_context_create($ctx_opts);
    $raw = @file_get_contents($playSourceUrl, false, $ctx);
    if (is_string($raw) && $raw !== '') $resp = $raw;
}

$yyzyData = json_decode($resp, true);
$playUrls = [];
if($yyzyData && isset($yyzyData['list']) && is_array($yyzyData['list'])) {
    // Match by title
    foreach($yyzyData['list'] as $item) {
        $itemName = $item['vod_name'] ?? '';
        if(mb_strpos($itemName, $title) !== false || mb_strpos($title, $itemName) !== false) {
            // Found item, extract play lines
            $playFrom = $item['vod_play_from'] ?? '';
            $playUrl = $item['vod_play_url'] ?? '';
            $froms = explode('$$$', $playFrom);
            $urls = explode('$$$', $playUrl);
            foreach($froms as $fi => $f) {
                $lineName = trim($f) ?: ('线路' . ($fi + 1));
                $lineUrls = explode('#', $urls[$fi] ?? '');
                $episodes = [];
                foreach($lineUrls as $ui => $ur) {
                    $parts = explode('$', $ur);
                    if(count($parts) >= 2) {
                        $episodes[] = ['name' => $parts[0], 'url' => $parts[1]];
                    } elseif($parts[0]) {
                        $episodes[] = ['name' => '第' . ($ui + 1) . '集', 'url' => $parts[0]];
                    }
                }
                if(count($episodes)) {
                    $playUrls[] = ['line' => $lineName, 'episodes' => $episodes];
                }
            }
            break;
        }
    }
}

// Determine which episode URL to use
$currentEpisodeUrl = '';
if(count($playUrls) && isset($playUrls[0]['episodes'])) {
    $targetIdx = ($type == 'tv') ? ($episode - 1) : 0;
    $eps = $playUrls[0]['episodes'];
    if(isset($eps[$targetIdx])) {
        $currentEpisodeUrl = $eps[$targetIdx]['url'];
    } elseif(count($eps)) {
        $currentEpisodeUrl = $eps[0]['url'];
    }
}

// Build parser URL: if we have direct source, use it; otherwise use yyzy wd search URL passed to parser
$parserInput = $currentEpisodeUrl ?: ($srcUrl . '?wd=' . $searchKeyword . ($type == 'tv' ? ('&sid=' . $season . '&lid=' . $episode) : ''));
$playerUrl = PLAYER_PARSER . urlencode($parserInput);

// Add watch history
$userId = $_SESSION['user_id'];
$histId = 0;
$existHist = $db->fetchOne("SELECT id FROM watch_history WHERE user_id = ? AND media_id = ? AND media_type = ? LIMIT 1", [$userId, $id, $type]);
if($existHist) {
    $histId = $existHist['id'];
    $db->update('watch_history', [
        'season' => $season, 'episode' => $episode,
        'last_watch' => date('Y-m-d H:i:s')
    ], 'id = ?', [$histId]);
} else {
    $histId = $db->insert('watch_history', [
        'user_id' => $userId, 'media_id' => $id, 'media_type' => $type,
        'media_title' => $title, 'media_poster' => $poster,
        'season' => $season, 'episode' => $episode
    ]);
}
?>

<div class="container" style="padding-top:30px;">
    <div class="play-header">
        <h2 class="play-title">
            <?php echo sanitize($title); ?>
            <?php if($type == 'tv'): ?>
                <span style="color:var(--text-muted);font-size:16px;font-weight:400;margin-left:10px;">
                    第<?php echo $season; ?>季 第<?php echo $episode; ?>集<?php echo $epName ? ' · ' . sanitize($epName) : ''; ?>
                </span>
            <?php endif; ?>
        </h2>
        <div style="display:flex;gap:10px;">
            <a href="detail.php?type=<?php echo $type; ?>&id=<?php echo $id; ?>" class="btn btn-secondary btn-sm"><span class="icon icon-chevron icon-chevron-left"></span>返回详情</a>
        </div>
    </div>

    <div class="player-wrapper">
        <iframe src="<?php echo $playerUrl; ?>" allowfullscreen allow="fullscreen; encrypted-media" scrolling="no"></iframe>
    </div>

    <div style="margin-bottom:20px;">
        <div style="margin-bottom:12px;font-weight:600;font-size:15px;color:var(--text-secondary);">播放源</div>
        <div class="source-tabs">
            <?php foreach($sources as $i => $s): ?>
            <a href="?type=<?php echo $type; ?>&id=<?php echo $id; ?>&season=<?php echo $season; ?>&episode=<?php echo $episode; ?>&src=<?php echo $s['id']; ?>&lang=<?php echo $lang; ?>&name=<?php echo urlencode($epName); ?>&title=<?php echo urlencode($title); ?>"
               class="source-tab <?php echo $curSource['id'] == $s['id'] ? 'active' : ''; ?>"><?php echo sanitize($s['name']); ?></a>
            <?php endforeach; ?>
        </div>
    </div>

    <?php if(count($playUrls)): ?>
    <div style="margin-bottom:30px;">
        <?php foreach($playUrls as $li => $line): ?>
        <div style="margin-bottom:20px;">
            <div style="margin-bottom:10px;font-weight:600;font-size:14px;color:var(--text-secondary);">📺 <?php echo sanitize($line['line']); ?></div>
            <div style="display:flex;gap:8px;flex-wrap:wrap;">
                <?php foreach($line['episodes'] as $ei => $ep):
                    $target = $ep['url'];
                    $fullUrl = '?type=' . $type . '&id=' . $id . '&season=' . $season . '&episode=' . ($ei + 1) . '&src=' . $curSource['id'] . '&lang=' . $lang . '&name=' . urlencode($ep['name']) . '&title=' . urlencode($title) . '&_ep=' . urlencode($target);
                    $isActive = ($type == 'tv' && ($ei + 1) == $episode) || ($type == 'movie' && $ei == 0);
                ?>
                <a href="play.php<?php echo $fullUrl; ?>" class="btn btn-sm <?php echo $isActive ? 'btn-primary' : 'btn-outline'; ?>" style="min-width:70px;text-align:center;"><?php echo sanitize($ep['name']); ?></a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php if(count($playUrls) == 0): ?>
    <div class="alert alert-warning" style="display:flex;gap:12px;">
        <span class="icon icon-bell"></span>
        <span>暂无匹配的剧集列表，请尝试切换播放源。若仍无法播放，可前往<a href="feedback.php" style="color:var(--primary);margin:0 4px;">反馈页面</a>提交问题。</span>
    </div>
    <?php endif; ?>

</div>

<script>
startWatchTimer(<?php echo $histId; ?>);
// If user passed custom episode URL, override parser
<?php if(!empty($_GET['_ep'])): ?>
(function(){
    var customUrl = <?php echo json_encode($_GET['_ep']); ?>;
    var iframe = document.querySelector('.player-wrapper iframe');
    if(iframe && customUrl) {
        var base = <?php echo json_encode(PLAYER_PARSER); ?>;
        iframe.src = base + encodeURIComponent(customUrl);
    }
})();
<?php endif; ?>
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
