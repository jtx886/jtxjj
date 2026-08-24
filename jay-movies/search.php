<?php
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/tmdb.php';

// ========== 预算时间控制：避免 TMDB 连不上导致卡死 ==========
$startTime = microtime(true);
$BUDGET_SECONDS  = 3.0;
$PER_REQ_CONNECT = 1.2;
$PER_REQ_TOTAL   = 2.0;
$quickCfg = [
    'cn' => $PER_REQ_CONNECT,
    'tm' => $PER_REQ_TOTAL,
    'budget' => $BUDGET_SECONDS,
    'start' => $startTime,
];

function jm_search_fetch(TMDB $tmdb, $query, $page, array &$cfg) {
    $elapsed = microtime(true) - $cfg['start'];
    if ($elapsed >= $cfg['budget']) {
        $r = new ReflectionClass('TMDB');
        $m = $r->getMethod('genFallbackList');
        $m->setAccessible(true);
        return ['results' => $m->invokeArgs($tmdb, [12, 'movie', "q{$query}{$page}"])['results']];
    }
    $remain = $cfg['budget'] - $elapsed;
    $curConnect = min($cfg['cn'], $remain * 0.6);
    $curTotal   = min($cfg['tm'], $remain * 0.95);
    if ($curTotal < 0.3) {
        $r = new ReflectionClass('TMDB');
        $m = $r->getMethod('genFallbackList');
        $m->setAccessible(true);
        return ['results' => $m->invokeArgs($tmdb, [12, 'movie', "q{$query}{$page}"])['results']];
    }
    $_SERVER['__JM_CONNECT_TIMEOUT__'] = $curConnect;
    $_SERVER['__JM_TOTAL_TIMEOUT__']   = $curTotal;
    try {
        $res = $tmdb->search($query, $page);
        if (is_array($res) && isset($res['results']) && is_array($res['results'])) return $res;
        $r = new ReflectionClass('TMDB');
        $m = $r->getMethod('genFallbackList');
        $m->setAccessible(true);
        return ['results' => $m->invokeArgs($tmdb, [12, 'movie', "q{$query}{$page}"])['results']];
    } catch (Throwable $e) {
        $r = new ReflectionClass('TMDB');
        $m = $r->getMethod('genFallbackList');
        $m->setAccessible(true);
        return ['results' => $m->invokeArgs($tmdb, [12, 'movie', "q{$query}{$page}"])['results']];
    }
}

$tmdb = new TMDB();
$q = trim($_GET['q'] ?? '');
$page = intval($_GET['page'] ?? 1);

$results = [];
if($q) {
    $data = jm_search_fetch($tmdb, $q, $page, $quickCfg);
    if(isset($data['results'])) {
        foreach($data['results'] as $r) {
            $mt = $r['media_type'] ?? (isset($r['name']) ? 'tv' : 'movie');
            if(in_array($mt, ['movie','tv'])) {
                $r['media_type'] = $mt;
                $results[] = $r;
            }
        }
    }
}

$db = Database::getInstance();
$favMap = [];
if(isLoggedIn()) {
    $favs = $db->fetchAll("SELECT media_id, media_type FROM favorites WHERE user_id = ?", [$_SESSION['user_id']]);
    foreach($favs as $f) { $favMap[$f['media_type'] . '_' . $f['media_id']] = true; }
}

function sTitle($m) { return isset($m['title']) ? $m['title'] : (isset($m['name']) ? $m['name'] : ''); }
function sYear($m) { $d = isset($m['release_date']) ? $m['release_date'] : (isset($m['first_air_date']) ? $m['first_air_date'] : ''); return $d ? substr($d,0,4) : ''; }
function sRating($m) { return isset($m['vote_average']) ? number_format($m['vote_average'],1) : '0.0'; }
$labels = ['movie'=>'电影','tv'=>'剧集','person'=>'人物'];
?>
<div class="container" style="padding-top:30px;">
    <div class="page-header">
        <div>
            <h1 class="page-title">
                <?php if($q): ?>
                    搜索结果：<?php echo sanitize($q); ?>
                <?php else: ?>
                    搜索
                <?php endif; ?>
            </h1>
            <?php if($q): ?>
            <div class="page-desc">共找到约 <?php echo count($results); ?> 条结果</div>
            <?php endif; ?>
        </div>
    </div>

    <form method="GET" class="search-bar">
        <input type="text" name="q" class="form-control" placeholder="输入电影、电视剧、动漫名称..." value="<?php echo sanitize($q); ?>" style="max-width:400px;" required>
        <button class="btn btn-primary"><span class="icon icon-search"></span>搜索</button>
    </form>

    <?php if($q): ?>
    <?php if(count($results)): ?>
    <div class="media-grid">
        <?php foreach($results as $m):
            $mediaType = $m['media_type'];
            $mid = $m['id'];
            $poster = $tmdb->getImageUrl($m['poster_path'], 'w342');
            $title = sTitle($m);
            $year = sYear($m);
            $rating = sRating($m);
            $fav = isset($favMap[$mediaType . '_' . $mid]) ? 'active' : '';
            $label = $labels[$mediaType] ?? '影视';
        ?>
        <div class="media-card" onclick="location.href='detail.php?type=<?php echo $mediaType; ?>&id=<?php echo $mid; ?>'">
            <div class="media-poster">
                <img src="<?php echo $poster; ?>" alt="<?php echo sanitize($title); ?>" onerror="this.src='data:image/svg+xml;utf8,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 200 300%22><rect fill=%22%23252532%22 width=%22200%22 height=%22300%22/></svg>'">
                <div class="media-poster-overlay">
                    <a href="play.php?type=<?php echo $mediaType; ?>&id=<?php echo $mid; ?>" class="btn btn-primary btn-sm" onclick="event.stopPropagation()">
                        <span class="icon icon-play"></span>播放
                    </a>
                </div>
                <div class="media-rating"><span class="icon icon-star"></span><?php echo $rating; ?></div>
                <button class="media-fav-btn <?php echo $fav; ?>" onclick="event.stopPropagation(); toggleFavorite(this, <?php echo $mid; ?>, '<?php echo $mediaType; ?>', '<?php echo sanitize($title); ?>', '<?php echo $poster; ?>', '<?php echo $year; ?>')">
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
        <?php endforeach; ?>
    </div>
    <?php else: ?>
    <div class="data-card" style="text-align:center;padding:80px 20px;">
        <div style="font-size:64px;margin-bottom:20px;opacity:0.3;">🔍</div>
        <div style="font-size:18px;font-weight:700;margin-bottom:8px;">没有找到相关结果</div>
        <div style="color:var(--text-muted);font-size:14px;">试试其他关键词，如：庆余年、海贼王</div>
    </div>
    <?php endif; ?>
    <?php else: ?>
    <div class="data-card" style="padding:50px 40px;">
        <h3 style="margin-bottom:16px;font-size:18px;">🔥 热门搜索</h3>
        <div style="display:flex;gap:10px;flex-wrap:wrap;">
            <?php
            $hot = ['庆余年 第二季','海贼王','斗破苍穹','难哄','漫长的季节','流浪地球','黑神话','三体','长津湖','阿凡达','你好李焕英','满江红'];
            foreach($hot as $h): ?>
            <a href="search.php?q=<?php echo urlencode($h); ?>" class="cat-tab"><?php echo $h; ?></a>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
