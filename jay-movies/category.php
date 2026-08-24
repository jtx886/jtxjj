<?php
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/tmdb.php';

// ========== 预算时间控制：避免 TMDB 连不上导致卡死 ==========
$startTime = microtime(true);
$BUDGET_SECONDS  = 3.0;  // 分类页总预算 3 秒
$PER_REQ_CONNECT = 1.2;
$PER_REQ_TOTAL   = 2.0;

$quickCfg = [
    'cn' => $PER_REQ_CONNECT,
    'tm' => $PER_REQ_TOTAL,
    'budget' => $BUDGET_SECONDS,
    'start' => $startTime,
];

function jm_cat_fetch(TMDB $tmdb, string $method, array $args, $fallbackSeed, $fallbackType, array &$cfg) {
    $elapsed = microtime(true) - $cfg['start'];
    if ($elapsed >= $cfg['budget']) {
        // 使用反射调用 genFallbackList（私有方法）
        $r = new ReflectionClass('TMDB');
        $m = $r->getMethod('genFallbackList');
        $m->setAccessible(true);
        return ['results' => $m->invokeArgs($tmdb, [20, $fallbackType, $fallbackSeed])['results']];
    }
    $remain = $cfg['budget'] - $elapsed;
    $curConnect = min($cfg['cn'], $remain * 0.6);
    $curTotal   = min($cfg['tm'], $remain * 0.95);
    if ($curTotal < 0.3) {
        $r = new ReflectionClass('TMDB');
        $m = $r->getMethod('genFallbackList');
        $m->setAccessible(true);
        return ['results' => $m->invokeArgs($tmdb, [20, $fallbackType, $fallbackSeed])['results']];
    }
    $_SERVER['__JM_CONNECT_TIMEOUT__'] = $curConnect;
    $_SERVER['__JM_TOTAL_TIMEOUT__']   = $curTotal;
    try {
        $r = new ReflectionClass('TMDB');
        $m = $r->getMethod($method);
        $res = $m->invokeArgs($tmdb, $args);
        if (is_array($res) && isset($res['results']) && is_array($res['results'])) return $res;
        // fallback
        $m2 = $r->getMethod('genFallbackList');
        $m2->setAccessible(true);
        return ['results' => $m2->invokeArgs($tmdb, [20, $fallbackType, $fallbackSeed])['results']];
    } catch (Throwable $e) {
        $r = new ReflectionClass('TMDB');
        $m = $r->getMethod('genFallbackList');
        $m->setAccessible(true);
        return ['results' => $m->invokeArgs($tmdb, [20, $fallbackType, $fallbackSeed])['results']];
    }
}

$tmdb = new TMDB();
$type = $_GET['type'] ?? 'movie';
$genreId = intval($_GET['genre'] ?? 0);

$mapType = $type;
if($type == 'anime' || $type == 'variety') $mapType = 'tv';

$catNames = ['movie'=>'电影','tv'=>'电视剧','anime'=>'动漫','variety'=>'综艺'];
$catName = $catNames[$type] ?? '影视';

// Movie genre tabs
$movieGenres = [
    ['id'=>0,'name'=>'全部'],
    ['id'=>28,'name'=>'动作'],
    ['id'=>35,'name'=>'喜剧'],
    ['id'=>10749,'name'=>'爱情'],
    ['id'=>878,'name'=>'科幻'],
    ['id'=>9648,'name'=>'悬疑'],
    ['id'=>18,'name'=>'剧情'],
    ['id'=>53,'name'=>'惊悚'],
    ['id'=>12,'name'=>'冒险'],
    ['id'=>80,'name'=>'犯罪'],
    ['id'=>16,'name'=>'动画'],
    ['id'=>10752,'name'=>'战争'],
    ['id'=>36,'name'=>'历史']
];
$tvGenres = [
    ['id'=>0,'name'=>'全部'],
    ['id'=>10759,'name'=>'动作冒险'],
    ['id'=>16,'name'=>'动画'],
    ['id'=>35,'name'=>'喜剧'],
    ['id'=>80,'name'=>'犯罪'],
    ['id'=>99,'name'=>'纪录'],
    ['id'=>18,'name'=>'剧情'],
    ['id'=>10751,'name'=>'家庭'],
    ['id'=>10762,'name'=>'儿童'],
    ['id'=>9648,'name'=>'悬疑'],
    ['id'=>10763,'name'=>'新闻'],
    ['id'=>10764,'name'=>'真人秀'],
    ['id'=>10765,'name'=>'科幻奇幻']
];
$genreTabs = ($mapType == 'movie') ? $movieGenres : $tvGenres;

// Fetch data（预算时间控制，避免卡死）
if($type == 'anime') {
    $result = jm_cat_fetch($tmdb, 'getByGenre', ['tv', 16, 1], "g16tv1", 'tv', $quickCfg);
} elseif($type == 'variety') {
    $result = jm_cat_fetch($tmdb, 'getByGenre', ['tv', 10764, 1], "g10764tv1", 'tv', $quickCfg);
} elseif($genreId) {
    $result = jm_cat_fetch($tmdb, 'getByGenre', [$mapType, $genreId, 1], "g{$genreId}{$mapType}1", $mapType, $quickCfg);
} else {
    $result = jm_cat_fetch($tmdb, 'getPopular', [$mapType, 1], "pop{$mapType}1", $mapType, $quickCfg);
}
$items = isset($result['results']) ? $result['results'] : [];

$db = Database::getInstance();
$favMap = [];
if(isLoggedIn()) {
    $favs = $db->fetchAll("SELECT media_id, media_type FROM favorites WHERE user_id = ?", [$_SESSION['user_id']]);
    foreach($favs as $f) { $favMap[$f['media_type'] . '_' . $f['media_id']] = true; }
}

function cTitle($m) { return isset($m['title']) ? $m['title'] : (isset($m['name']) ? $m['name'] : ''); }
function cYear($m) { $d = isset($m['release_date']) ? $m['release_date'] : (isset($m['first_air_date']) ? $m['first_air_date'] : ''); return $d ? substr($d,0,4) : ''; }
function cRating($m) { return isset($m['vote_average']) ? number_format($m['vote_average'],1) : '0.0'; }
$mt = $mapType;
$label = $catName;
?>
<div class="container" style="padding-top:30px;">
    <div class="page-header">
        <div>
            <h1 class="page-title"><?php echo $catName; ?>大全</h1>
            <div class="page-desc">全网最热门的<?php echo $catName; ?>资源，免费在线观看</div>
        </div>
    </div>

    <div class="category-tabs">
        <?php foreach($genreTabs as $g): ?>
        <span class="cat-tab <?php echo ($genreId==$g['id'])?'active':''; ?>" onclick="filterCategory(this, '<?php echo $type; ?>', <?php echo $g['id']; ?>)"><?php echo $g['name']; ?></span>
        <?php endforeach; ?>
    </div>

    <div class="media-grid" id="mediaGrid">
        <?php
        $show = array_slice($items, 0, 18);
        foreach($show as $m):
            $mid = $m['id'];
            $poster = $tmdb->getImageUrl($m['poster_path'], 'w342');
            $title = cTitle($m);
            $year = cYear($m);
            $rating = cRating($m);
            $fav = isset($favMap[$mt . '_' . $mid]) ? 'active' : '';
        ?>
        <div class="media-card" onclick="location.href='detail.php?type=<?php echo $mt; ?>&id=<?php echo $mid; ?>'">
            <div class="media-poster">
                <img src="<?php echo $poster; ?>" alt="<?php echo sanitize($title); ?>" onerror="this.src='data:image/svg+xml;utf8,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 200 300%22><rect fill=%22%23252532%22 width=%22200%22 height=%22300%22/></svg>'">
                <div class="media-poster-overlay">
                    <a href="play.php?type=<?php echo $mt; ?>&id=<?php echo $mid; ?>" class="btn btn-primary btn-sm" onclick="event.stopPropagation()">
                        <span class="icon icon-play"></span>播放
                    </a>
                </div>
                <div class="media-rating"><span class="icon icon-star"></span><?php echo $rating; ?></div>
                <button class="media-fav-btn <?php echo $fav; ?>" onclick="event.stopPropagation(); toggleFavorite(this, <?php echo $mid; ?>, '<?php echo $mt; ?>', '<?php echo sanitize($title); ?>', '<?php echo $poster; ?>', '<?php echo $year; ?>')">
                    <span class="icon icon-heart"></span>
                </button>
            </div>
            <div class="media-info">
                <div class="media-title"><?php echo sanitize($title); ?></div>
                <div class="media-sub">
                    <span class="media-year"><?php echo $year; ?></span>
                    <span><?php echo $label; ?></span>
                </div>
            </div>
        </div>
        <?php endforeach;
        if(!count($show)): ?>
        <div style="grid-column:1/-1;text-align:center;padding:80px 20px;color:var(--text-muted);">
            <div style="font-size:16px;margin-bottom:10px;">暂无数据</div>
            <div style="font-size:13px;">请尝试其他分类</div>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
