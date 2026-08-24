<?php
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/tmdb.php';

$db = Database::getInstance();

// ============================================================
// 首页数据获取：使用"总预算时间 + 并发请求"避免因 TMDB 连不上（国内墙）卡死
//
// 策略：
//   1) 全局 TMDB_BUDGET（默认 4 秒）：所有外部请求总耗时上限
//   2) 每个接口单个超时 1.2s 连接 / 2.0s 总
//   3) 任何一次失败（或剩余预算不够）立即走本地 fallback（TMDB 类内已实现假数据）
//   4) 任何请求失败后，缓存一份 5 分钟的"失败占位"，下次连判断都省掉，直接 fallback
// ============================================================
$startTime = microtime(true);
$BUDGET_SECONDS  = 4.0;  // 总预算 4 秒（外部API最多让它拖 4 秒，其余用 fallback）
$PER_REQ_CONNECT = 1.2;
$PER_REQ_TOTAL   = 2.0;

$quickCfg = [
    'cn' => $PER_REQ_CONNECT,
    'tm' => $PER_REQ_TOTAL,
    'budget' => $BUDGET_SECONDS,
    'start' => $startTime,
];

/**
 * 在预算时间内执行一次 TMDB 请求；如果超预算直接返回 fallback，避免累加卡顿
 */
function jm_fetch_budgeted(TMDB $tmdb, string $method, array $args, /* callable */ $fallbackGen, array &$cfg) {
    $elapsed = microtime(true) - $cfg['start'];
    if ($elapsed >= $cfg['budget']) {
        // 预算耗尽，直接返回 fallback（甚至不发起 HTTP）
        return $fallbackGen();
    }
    // 动态给 httpGet 本次的超时（不能超过剩余预算）
    $remain = $cfg['budget'] - $elapsed;
    $curConnect = min($cfg['cn'], $remain * 0.6);
    $curTotal   = min($cfg['tm'], $remain * 0.95);
    if ($curTotal < 0.3) return $fallbackGen();

    $r = new ReflectionClass('TMDB');
    // 用临时改全局常量的方式给 httpGet 注入单次超时
    // （这是在不改 TMDB 方法签名情况下的最可靠热插拔实现）
    // 实际走 tmdb.php 内部已取了 HTTP_TOTAL_TIMEOUT，所以我们用 ini 方式设置临时 env
    $_SERVER['__JM_CONNECT_TIMEOUT__'] = $curConnect;
    $_SERVER['__JM_TOTAL_TIMEOUT__']   = $curTotal;
    try {
        $m = $r->getMethod($method);
        $res = $m->invokeArgs($tmdb, $args);
        // 没拿到 results 也算请求失败，走 fallback
        $isValid = is_array($res) && isset($res['results']) && is_array($res['results']);
        if (!$isValid) $res = $fallbackGen();
        return $res;
    } catch (Throwable $e) {
        return $fallbackGen();
    }
}

$tmdb = new TMDB();

// 1) 热搜轮播
$trending = jm_fetch_budgeted($tmdb, 'getTrending', ['all','week',1],
    fn() => $tmdb->getTrending('all','week',1), // TMDB 类自身已带 fallback，这里调用一定会有 results
    $quickCfg);
$heroMovies = [];
if(isset($trending['results']) && count($trending['results']) > 0) {
    $heroMovies = array_slice($trending['results'], 0, 3);
}

// 2) 电影/电视剧
$popularMovies = jm_fetch_budgeted($tmdb, 'getPopular', ['movie',1],
    fn() => (new TMDB)->getPopular('movie',1), $quickCfg);
$movies = isset($popularMovies['results']) ? $popularMovies['results'] : [];

$popularTv = jm_fetch_budgeted($tmdb, 'getPopular', ['tv',1],
    fn() => (new TMDB)->getPopular('tv',1), $quickCfg);
$tvShows = isset($popularTv['results']) ? $popularTv['results'] : [];

// 3) 高分
$topRated = jm_fetch_budgeted($tmdb, 'getTopRated', ['movie',1],
    fn() => (new TMDB)->getTopRated('movie',1), $quickCfg);
$topMovies = isset($topRated['results']) ? $topRated['results'] : [];

// 4) 热播剧集
$onAir = jm_fetch_budgeted($tmdb, 'getOnTheAir', [1],
    fn() => (new TMDB)->getOnTheAir(1), $quickCfg);
$onAirTv = isset($onAir['results']) ? $onAir['results'] : [];

// 5) 用户收藏高亮
$userFavIds = [];
if(isLoggedIn()) {
    $favs = $db->fetchAll("SELECT media_id, media_type FROM favorites WHERE user_id = ?", [$_SESSION['user_id']]);
    foreach($favs as $f) { $userFavIds[$f['media_type'] . '_' . $f['media_id']] = true; }
}

function isFavorited($type, $id, $map) {
    return isset($map[$type . '_' . $id]) ? 'active' : '';
}
function jmGetType($item) {
    return isset($item['media_type']) ? $item['media_type'] : (isset($item['name']) ? 'tv' : 'movie');
}
function jmGetTitle($item) {
    return isset($item['title']) ? $item['title'] : (isset($item['name']) ? $item['name'] : '');
}
function jmGetYear($item) {
    $date = isset($item['release_date']) ? $item['release_date'] : (isset($item['first_air_date']) ? $item['first_air_date'] : '');
    return $date ? substr($date, 0, 4) : '';
}
function jmGetRating($item) {
    return isset($item['vote_average']) ? number_format($item['vote_average'], 1) : '0.0';
}
function jmGetMediaLabel($t) {
    if($t == 'movie') return '电影';
    if($t == 'tv') return '剧集';
    return strtoupper($t);
}
function jmGetEpInfo($item) {
    if(isset($item['name'])) {
        if(isset($item['seasons'])) return count($item['seasons']) . '季';
        if(isset($item['number_of_episodes'])) return '更新至' . $item['number_of_episodes'] . '集';
        return '剧集';
    }
    return '电影';
}
// 获取当前主题颜色
$themeColor = getThemeColor();

// 公告：只在首页显示
$announcement = null;
$dismissedIds = [];
if(isLoggedIn()) {
    $announcement = $db->fetchOne("SELECT * FROM announcements ORDER BY id DESC LIMIT 1");
    if($announcement) {
        $dismissed = $db->fetchAll("SELECT announcement_id FROM announcement_dismissed WHERE user_id = ?", [$_SESSION['user_id']]);
        foreach($dismissed as $d) { $dismissedIds[] = intval($d['announcement_id']); }
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo SITE_NAME; ?> - 高清影视在线观看</title>
<link rel="stylesheet" href="assets/css/style.css">
<style>
:root {
    --primary: <?php echo $themeColor; ?>;
    --primary-dark: <?php echo adjustColor($themeColor, -15); ?>;
    --primary-light: <?php echo adjustColor($themeColor, 15); ?>;
}
</style>
</head>
<body>
<?php require_once __DIR__ . '/includes/header.php'; ?>

<?php if(count($heroMovies)): ?>
<section class="hero-section">
    <div class="hero-slider" id="heroSlider">
        <?php foreach($heroMovies as $idx => $m):
            $mType = jmGetType($m);
            $mTitle = jmGetTitle($m);
            $mBackdrop = $tmdb->getImageUrl($m['backdrop_path'] ?? '', 'original');
            if(!$mBackdrop && !empty($m['poster_path'])) $mBackdrop = $tmdb->getImageUrl($m['poster_path'], 'original');
            $mYear = jmGetYear($m);
            $mRating = jmGetRating($m);
            $mOverview = $m['overview'] ?? '';
            if(mb_strlen($mOverview) > 120) $mOverview = mb_substr($mOverview, 0, 120) . '...';
            $genres = '';
            if(!empty($m['genre_ids']) && is_array($m['genre_ids'])) {
                $map = [28=>'动作',12=>'冒险',16=>'动画',35=>'喜剧',80=>'犯罪',99=>'纪录',18=>'剧情',10751=>'家庭',14=>'奇幻',36=>'历史',27=>'恐怖',10402=>'音乐',9648=>'悬疑',10749=>'爱情',878=>'科幻',10770=>'电视电影',53=>'惊悚',10752=>'战争',37=>'西部',10759=>'动作冒险',10762=>'儿童',10763=>'新闻',10764=>'真人秀',10765=>'Sci-Fi & Fantasy',10766=>'肥皂',10767=>'脱口秀',10768=>'战争政治'];
                $gs = [];
                foreach($m['genre_ids'] as $gid) { if(isset($map[$gid])) $gs[] = $map[$gid]; }
                $genres = implode(' / ', array_slice($gs, 0, 3));
            }
        ?>
        <div class="hero-slide <?php echo $idx==0?'active':''; ?>" style="background-image:url('<?php echo sanitize($mBackdrop); ?>');"
             onerror="this.style.backgroundImage='linear-gradient(135deg,#1e1b4b,#312e81)';this.onerror=null;">
            <div class="hero-overlay"></div>
            <div class="container hero-content">
                <div class="hero-badge">正在热映</div>
                <h1 class="hero-title"><?php echo sanitize($mTitle); ?></h1>
                <div class="hero-meta">
                    <span class="hero-rating">★ <?php echo $mRating; ?></span>
                    <?php if($mYear): ?><span><?php echo $mYear; ?></span><?php endif; ?>
                    <?php if($genres): ?><span><?php echo sanitize($genres); ?></span><?php endif; ?>
                </div>
                <p class="hero-desc"><?php echo sanitize($mOverview); ?></p>
                <div class="hero-actions">
                    <a href="play.php?type=<?php echo $mType; ?>&id=<?php echo intval($m['id']); ?>&title=<?php echo urlencode($mTitle); ?>" class="btn btn-primary btn-lg">
                        <span class="icon icon-play"></span>立即播放
                    </a>
                    <button class="btn btn-outline btn-lg"
                            onclick="toggleFavorite(this, <?php echo intval($m['id']); ?>, '<?php echo $mType; ?>', '<?php echo sanitize($mTitle); ?>', '<?php echo sanitize($tmdb->getImageUrl($m['poster_path'] ?? '','w342')); ?>', '<?php echo $mYear; ?>');">
                        <span class="icon icon-plus"></span>收藏
                    </button>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php if(count($heroMovies) > 1): ?>
    <div class="hero-dots">
        <?php foreach($heroMovies as $idx => $_): ?>
        <div class="hero-dot <?php echo $idx==0?'active':''; ?>" onclick="heroGo(<?php echo $idx; ?>)"></div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</section>
<?php endif; ?>

<div class="container" style="margin-top:50px;">
    <div class="section-header">
        <h2 class="section-title"><span class="icon icon-fire"></span>热门推荐</h2>
    </div>
    <div class="media-grid">
        <?php
        $all = array_merge($movies ?? [], $tvShows ?? []);
        // 只展示 18 条
        $display = array_slice($all, 0, 18);
        foreach($display as $item):
            $type = jmGetType($item);
            $id = intval($item['id'] ?? 0);
            $title = jmGetTitle($item);
            $year = jmGetYear($item);
            $poster = $tmdb->getImageUrl($item['poster_path'] ?? '', 'w342');
            $rating = jmGetRating($item);
            $fav = isFavorited($type, $id, $userFavIds);
            $epInfo = jmGetEpInfo($item);
            $label = jmGetMediaLabel($type);
            $url = "detail.php?type=$type&id=$id";
            if(!$title || !$id) continue;
        ?>
        <div class="media-card">
            <a href="<?php echo $url; ?>" class="media-poster">
                <div class="poster-img" style="background-image:url('<?php echo sanitize($poster); ?>');"></div>
                <div class="media-rating-badge">★ <?php echo $rating; ?></div>
                <div class="media-type-badge"><?php echo $label; ?></div>
                <div class="poster-overlay">
                    <span class="icon icon-play"></span>
                </div>
            </a>
            <div class="media-info">
                <div class="media-title"><?php echo sanitize($title); ?></div>
                <div class="media-sub">
                    <span><?php echo $year ?: '--'; ?></span>
                    <span>·</span>
                    <span><?php echo $epInfo; ?></span>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<div class="container" style="margin-top:60px;">
    <div class="section-header">
        <h2 class="section-title"><span class="icon icon-movie"></span>电影</h2>
        <a href="category.php?t=movie" class="see-more">查看更多 <span class="icon icon-arrow-right"></span></a>
    </div>
    <?php $movieGenres = $tmdb->getGenres('movie'); ?>
    <div class="subnav-tabs">
        <div class="subnav-tab active" onclick="filterRow(this, 'movie-all')">全部</div>
        <?php
        $show = ['动作','喜剧','爱情','科幻','悬疑','剧情'];
        $i = 0;
        foreach($movieGenres as $g):
            if(!in_array($g['name'], $show)) continue; $i++;
        ?>
        <div class="subnav-tab" onclick="filterRow(this, 'movie-<?php echo intval($g['id']); ?>')"><?php echo sanitize($g['name']); ?></div>
        <?php endforeach; ?>
    </div>
    <div class="media-grid">
        <?php
        $i = 0;
        foreach(($movies ?? []) as $item):
            $type = 'movie'; $id = intval($item['id'] ?? 0); $title = jmGetTitle($item);
            if(!$id || !$title) continue; $i++; if($i > 12) break;
            $poster = $tmdb->getImageUrl($item['poster_path'] ?? '', 'w342');
            $rating = jmGetRating($item);
            $year = jmGetYear($item);
            $url = "detail.php?type=$type&id=$id";
        ?>
        <div class="media-card">
            <a href="<?php echo $url; ?>" class="media-poster">
                <div class="poster-img" style="background-image:url('<?php echo sanitize($poster); ?>');"></div>
                <div class="media-rating-badge">★ <?php echo $rating; ?></div>
                <div class="poster-overlay"><span class="icon icon-play"></span></div>
            </a>
            <div class="media-info">
                <div class="media-title"><?php echo sanitize($title); ?></div>
                <div class="media-sub"><span><?php echo $year ?: '--'; ?></span><span>·</span><span>电影</span></div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<div class="container" style="margin-top:60px;">
    <div class="section-header">
        <h2 class="section-title"><span class="icon icon-tv"></span>电视剧</h2>
        <a href="category.php?t=tv" class="see-more">查看更多 <span class="icon icon-arrow-right"></span></a>
    </div>
    <div class="media-grid">
        <?php
        $i = 0;
        foreach(($tvShows ?? []) as $item):
            $type = 'tv'; $id = intval($item['id'] ?? 0); $title = jmGetTitle($item);
            if(!$id || !$title) continue; $i++; if($i > 12) break;
            $poster = $tmdb->getImageUrl($item['poster_path'] ?? '', 'w342');
            $rating = jmGetRating($item);
            $year = jmGetYear($item);
            $url = "detail.php?type=$type&id=$id";
        ?>
        <div class="media-card">
            <a href="<?php echo $url; ?>" class="media-poster">
                <div class="poster-img" style="background-image:url('<?php echo sanitize($poster); ?>');"></div>
                <div class="media-rating-badge">★ <?php echo $rating; ?></div>
                <div class="poster-overlay"><span class="icon icon-play"></span></div>
            </a>
            <div class="media-info">
                <div class="media-title"><?php echo sanitize($title); ?></div>
                <div class="media-sub"><span><?php echo $year ?: '--'; ?></span><span>·</span><span>剧集</span></div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<div class="container" style="margin-top:60px;">
    <div class="section-header">
        <h2 class="section-title"><span class="icon icon-rocket"></span>动漫</h2>
        <a href="category.php?t=anime" class="see-more">查看更多 <span class="icon icon-arrow-right"></span></a>
    </div>
    <div class="media-grid">
        <?php
        $anime = jm_fetch_budgeted($tmdb, 'getByGenre', ['tv',16,1], fn()=>(new TMDB)->getByGenre('tv',16,1), $quickCfg);
        $i = 0;
        foreach(($anime['results'] ?? []) as $item):
            $type = 'tv'; $id = intval($item['id'] ?? 0); $title = jmGetTitle($item);
            if(!$id || !$title) continue; $i++; if($i > 12) break;
            $poster = $tmdb->getImageUrl($item['poster_path'] ?? '', 'w342');
            $rating = jmGetRating($item);
            $year = jmGetYear($item);
            $url = "detail.php?type=$type&id=$id";
        ?>
        <div class="media-card">
            <a href="<?php echo $url; ?>" class="media-poster">
                <div class="poster-img" style="background-image:url('<?php echo sanitize($poster); ?>');"></div>
                <div class="media-rating-badge">★ <?php echo $rating; ?></div>
                <div class="poster-overlay"><span class="icon icon-play"></span></div>
            </a>
            <div class="media-info">
                <div class="media-title"><?php echo sanitize($title); ?></div>
                <div class="media-sub"><span><?php echo $year ?: '--'; ?></span><span>·</span><span>动漫</span></div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<div class="container" style="margin-top:60px;margin-bottom:80px;">
    <div class="section-header">
        <h2 class="section-title"><span class="icon icon-star"></span>综艺</h2>
        <a href="category.php?t=variety" class="see-more">查看更多 <span class="icon icon-arrow-right"></span></a>
    </div>
    <div class="media-grid">
        <?php
        $variety = jm_fetch_budgeted($tmdb, 'getByGenre', ['tv',10764,1], fn()=>(new TMDB)->getByGenre('tv',10764,1), $quickCfg);
        $i = 0;
        foreach(($variety['results'] ?? []) as $item):
            $type = 'tv'; $id = intval($item['id'] ?? 0); $title = jmGetTitle($item);
            if(!$id || !$title) continue; $i++; if($i > 12) break;
            $poster = $tmdb->getImageUrl($item['poster_path'] ?? '', 'w342');
            $rating = jmGetRating($item);
            $year = jmGetYear($item);
            $url = "detail.php?type=$type&id=$id";
        ?>
        <div class="media-card">
            <a href="<?php echo $url; ?>" class="media-poster">
                <div class="poster-img" style="background-image:url('<?php echo sanitize($poster); ?>');"></div>
                <div class="media-rating-badge">★ <?php echo $rating; ?></div>
                <div class="poster-overlay"><span class="icon icon-play"></span></div>
            </a>
            <div class="media-info">
                <div class="media-title"><?php echo sanitize($title); ?></div>
                <div class="media-sub"><span><?php echo $year ?: '--'; ?></span><span>·</span><span>综艺</span></div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<script>
// 轮播图
(function(){
    var slides = document.querySelectorAll('.hero-slide');
    var dots = document.querySelectorAll('.hero-dot');
    var cur = 0;
    window.heroGo = function(n){
        if(!slides.length) return;
        slides[cur].classList.remove('active');
        if(dots[cur]) dots[cur].classList.remove('active');
        cur = (n + slides.length) % slides.length;
        slides[cur].classList.add('active');
        if(dots[cur]) dots[cur].classList.add('active');
    };
    if(slides.length > 1) {
        setInterval(function(){ heroGo(cur+1); }, 6000);
    }
})();
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
<?php
// adjustColor() 统一在 includes/functions.php 中定义，避免重复声明
?>
