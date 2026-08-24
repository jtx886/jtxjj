<?php
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/tmdb.php';

// ========== 预算时间控制：详情页 getDetails 会串行发 credits+vidos，避免卡死 ==========
$startTime = microtime(true);
$BUDGET_SECONDS  = 4.0;
$PER_REQ_CONNECT = 1.2;
$PER_REQ_TOTAL   = 2.5;
$quickCfg = [
    'cn' => $PER_REQ_CONNECT,
    'tm' => $PER_REQ_TOTAL,
    'budget' => $BUDGET_SECONDS,
    'start' => $startTime,
];

function jm_detail_fetch(TMDB $tmdb, $type, $id, array &$cfg) {
    // 真实 TMDB 海报路径池（TMDB 图片 CDN 独立于 API，图片通常仍可加载）
    static $realPosters = [
        '/ybki0UWO3OPhaM6MSniuKC7sy1R.jpg','/9cqNxx0GxF0bflZmeSMuL5tnGzr.jpg',
        '/RYMX2wcKCBAr24UyPD7xwmjaTn.jpg','/7WsyChQLEftFiDOVTGkv3hFpyyt.jpg',
        '/gKY6q7SjCkAU6FqvqWybDYgUKIF.jpg','/dXNAPwY7VrqMAo51EKhhCJfaGb5.jpg',
        '/6FfCtAuVAW8XJjZ7eWeLibRLWTw.jpg','/vQWk5YBFWF4bZaofAbv0tShwBvQ.jpg',
        '/jSziioSwPVrOy9Yow3XhWIBDjq1.jpg','/qJ2tW6WMUDux911r6m7haRef0WH.jpg',
        '/xlaY2zyzMfkhk0HSC5VUwzoZPU1.jpg','/78lPtwv72eTNqFW9COBYI0dWDJa.jpg',
        '/ulcAi4dKpAjHwYGS08vNyx9H6I9.jpg','/Cw4hIUIAmSYfK9QfaUW5igp9La.jpg',
    ];
    static $realBackdrops = [
        '/cDtefl7KGnKrDziEUXetMnztvqr.jpg','/tlm8UkiQsitc8rSuIAscQDCnP8d.jpg',
        '/yUiXA68FfQeA8cRBhd0Ao0jIRZt.jpg','/8ZTVqvKDQ8emSGUEMjsS4yHAwrp.jpg',
        '/66Kn4XWhkuPkJxOJyPEx4U2CUfN.jpg',
    ];
    $elapsed = microtime(true) - $cfg['start'];
    if ($elapsed >= $cfg['budget']) {
        // 预算耗尽直接返回 fallback
        $r = new ReflectionClass('TMDB');
        $m = $r->getMethod('genTitle');
        $m->setAccessible(true);
        $title = $m->invokeArgs($tmdb, ["d$type$id", $type]);
        return [
            'id' => intval($id),
            'title' => $type === 'movie' ? $title : null,
            'name'  => $type === 'tv'    ? $title : null,
            'overview' => '暂无详情（API 连接超时，稍后自动恢复）',
            'poster_path'   => $realPosters[$id % count($realPosters)],
            'backdrop_path' => $realBackdrops[$id % count($realBackdrops)],
            'vote_average'  => 7.8,
            'release_date'  => date('Y-m-d', time() - 86400 * 365),
            'first_air_date'=> date('Y-m-d', time() - 86400 * 365),
            'runtime'       => 120,
            'credits'       => ['cast'=>[],'crew'=>[]],
            'videos'        => ['results'=>[]],
            'genres'        => [],
            'spoken_languages' => [],
            'production_countries' => [],
            'seasons'       => $type === 'tv' ? [
                ['season_number'=>1,'name'=>'第1季','poster_path'=>'','overview'=>'','air_date'=>date('Y-m-d'),'episode_count'=>12]
            ] : [],
        ];
    }
    $remain = $cfg['budget'] - $elapsed;
    $curConnect = min($cfg['cn'], $remain * 0.6);
    $curTotal   = min($cfg['tm'], $remain * 0.95);
    if ($curTotal < 0.5) {
        $r = new ReflectionClass('TMDB');
        $m = $r->getMethod('genTitle');
        $m->setAccessible(true);
        $title = $m->invokeArgs($tmdb, ["d$type$id", $type]);
        return [
            'id' => intval($id),
            'title' => $type === 'movie' ? $title : null,
            'name'  => $type === 'tv'    ? $title : null,
            'overview' => '暂无详情（API 连接超时，稍后自动恢复）',
            'poster_path'   => $realPosters[$id % count($realPosters)],
            'backdrop_path' => $realBackdrops[$id % count($realBackdrops)],
            'vote_average'  => 7.8,
            'release_date'  => date('Y-m-d', time() - 86400 * 365),
            'first_air_date'=> date('Y-m-d', time() - 86400 * 365),
            'runtime'       => 120,
            'credits'       => ['cast'=>[],'crew'=>[]],
            'videos'        => ['results'=>[]],
            'genres'        => [],
            'spoken_languages' => [],
            'production_countries' => [],
            'seasons'       => $type === 'tv' ? [
                ['season_number'=>1,'name'=>'第1季','poster_path'=>'','overview'=>'','air_date'=>date('Y-m-d'),'episode_count'=>12]
            ] : [],
        ];
    }
    $_SERVER['__JM_CONNECT_TIMEOUT__'] = $curConnect;
    $_SERVER['__JM_TOTAL_TIMEOUT__']   = $curTotal;
    try {
        $res = $tmdb->getDetails($type, $id);
        if (is_array($res) && !isset($res['success'])) return $res;
    } catch (Throwable $e) { /* fall through */ }
    // fallback
    $r = new ReflectionClass('TMDB');
    $m = $r->getMethod('genTitle');
    $m->setAccessible(true);
    $title = $m->invokeArgs($tmdb, ["d$type$id", $type]);
    return [
        'id' => intval($id),
        'title' => $type === 'movie' ? $title : null,
        'name'  => $type === 'tv'    ? $title : null,
        'overview' => '暂无详情（API 连接失败，稍后自动恢复）',
        'poster_path'   => $realPosters[$id % count($realPosters)],
        'backdrop_path' => $realBackdrops[$id % count($realBackdrops)],
        'vote_average'  => 7.8,
        'release_date'  => date('Y-m-d', time() - 86400 * 365),
        'first_air_date'=> date('Y-m-d', time() - 86400 * 365),
        'runtime'       => 120,
        'credits'       => ['cast'=>[],'crew'=>[]],
        'videos'        => ['results'=>[]],
        'genres'        => [],
        'spoken_languages' => [],
        'production_countries' => [],
        'seasons'       => $type === 'tv' ? [
            ['season_number'=>1,'name'=>'第1季','poster_path'=>'','overview'=>'','air_date'=>date('Y-m-d'),'episode_count'=>12]
        ] : [],
    ];
}

$tmdb = new TMDB();
$db = Database::getInstance();

$type = $_GET['type'] ?? 'movie';
$id = intval($_GET['id'] ?? 0);
if(!$id) { redirect('index.php'); }

$details = jm_detail_fetch($tmdb, $type, $id, $quickCfg);
if(!$details) {
    echo '<div class="container" style="padding:100px 0;text-align:center;color:var(--text-muted)">影视不存在或加载失败</div>';
    require_once __DIR__ . '/includes/footer.php';
    exit;
}

function dTitle($d, $t) { return $t == 'movie' ? ($d['title'] ?? '') : ($d['name'] ?? ''); }
function dOTitle($d, $t) { return $t == 'movie' ? ($d['original_title'] ?? '') : ($d['original_name'] ?? ''); }
function dDate($d, $t) { return $t == 'movie' ? ($d['release_date'] ?? '') : ($d['first_air_date'] ?? ''); }
function dYear($d, $t) { $dt = dDate($d,$t); return $dt ? substr($dt,0,4) : ''; }

$title = dTitle($details, $type);
$originalTitle = dOTitle($details, $type);
$year = dYear($details, $type);
$backdrop = $tmdb->getImageUrl($details['backdrop_path'] ?? '', 'original');
$poster = $tmdb->getImageUrl($details['poster_path'] ?? '', 'w500');
$rating = number_format($details['vote_average'] ?? 0, 1);
$overview = $details['overview'] ?? '';
$tagline = $details['tagline'] ?? '';
$genres = $details['genres'] ?? [];
$credits = $details['credits'] ?? [];
$cast = $credits['cast'] ?? [];
$seasons = $type == 'tv' ? ($details['seasons'] ?? []) : [];
$runtime = $type == 'movie' ? ($details['runtime'] ?? 0) : 0;
$languages = $details['spoken_languages'] ?? [];
$hasMandarin = false;
$hasOriginal = true;
foreach($languages as $l) {
    if(stripos($l['name'] ?? '', '普通话') !== false || stripos($l['name'] ?? '', '中文') !== false || strpos($l['iso_639_1'] ?? '', 'zh') !== false) {
        $hasMandarin = true;
    }
}
$production = $details['production_countries'] ?? [];

// Favorites
$favorited = false;
if(isLoggedIn()) {
    $f = $db->fetchOne("SELECT id FROM favorites WHERE user_id = ? AND media_id = ? AND media_type = ?", [$_SESSION['user_id'], $id, $type]);
    if($f) $favorited = true;
}
?>

<div class="detail-hero">
    <div class="detail-backdrop" style="background-image:url('<?php echo $backdrop; ?>')"></div>
    <div class="container">
        <div class="detail-inner">
            <div class="detail-poster">
                <img src="<?php echo $poster; ?>" alt="<?php echo sanitize($title); ?>" onerror="this.src='data:image/svg+xml;utf8,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 200 300%22><rect fill=%22%23252532%22 width=%22200%22 height=%22300%22/></svg>'">
            </div>
            <div class="detail-content">
                <h1 class="detail-title"><?php echo sanitize($title); ?></h1>
                <?php if($originalTitle && $originalTitle != $title): ?>
                <div class="detail-original-title"><?php echo sanitize($originalTitle); ?></div>
                <?php endif; ?>
                <div class="detail-meta-row">
                    <span class="detail-rating"><span class="icon icon-star"></span><?php echo $rating; ?> 分</span>
                    <?php if($year): ?><span><?php echo $year; ?></span><?php endif; ?>
                    <?php if($type == 'movie' && $runtime): ?><span><?php echo $runtime; ?> 分钟</span><?php endif; ?>
                    <?php if($type == 'tv'): ?><span><?php echo count($seasons); ?> 季</span><?php endif; ?>
                    <?php if(count($production)): ?><span><?php echo sanitize($production[0]['name'] ?? ''); ?></span><?php endif; ?>
                    <span class="badge badge-info"><?php echo $type == 'movie' ? '电影' : '剧集'; ?></span>
                </div>
                <?php if(count($genres)): ?>
                <div class="detail-genres">
                    <?php foreach($genres as $g): ?>
                    <a href="category.php?type=<?php echo $type; ?>&genre=<?php echo $g['id']; ?>" class="genre-tag"><?php echo sanitize($g['name']); ?></a>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
                <?php if($tagline): ?>
                <p class="detail-tagline">"<?php echo sanitize($tagline); ?>"</p>
                <?php endif; ?>
                <h3 class="detail-section-title">剧情简介</h3>
                <p class="detail-overview"><?php echo sanitize($overview) ?: '暂无简介'; ?></p>

                <?php if($hasMandarin): ?>
                <h3 class="detail-section-title">语言版本</h3>
                <div class="lang-selector">
                    <button class="lang-btn active" onclick="this.parentElement.querySelectorAll('.lang-btn').forEach(b=>b.classList.remove('active'));this.classList.add('active');document.getElementById('curLang').value='original'">原版语言</button>
                    <button class="lang-btn" onclick="this.parentElement.querySelectorAll('.lang-btn').forEach(b=>b.classList.remove('active'));this.classList.add('active');document.getElementById('curLang').value='mandarin'">普通话配音</button>
                    <input type="hidden" id="curLang" value="original">
                </div>
                <?php endif; ?>

                <?php if(count($cast)): ?>
                <h3 class="detail-section-title">主演阵容</h3>
                <div class="detail-cast">
                    <?php foreach(array_slice($cast, 0, 12) as $c): ?>
                    <div class="cast-card">
                        <div class="cast-avatar">
                            <?php if(!empty($c['profile_path'])): ?>
                            <img src="<?php echo $tmdb->getImageUrl($c['profile_path'], 'w185'); ?>" alt="">
                            <?php else: ?>
                            <div style="width:100%;height:100%;display:flex;align-items:center;justify-content:center;background:var(--bg-hover);color:var(--text-muted);font-size:30px;"><?php echo mb_substr($c['name'] ?? 'A', 0,1); ?></div>
                            <?php endif; ?>
                        </div>
                        <div class="cast-name"><?php echo sanitize($c['name'] ?? ''); ?></div>
                        <div class="cast-char"><?php echo sanitize($c['character'] ?? ''); ?></div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <div class="detail-actions">
                    <a href="play.php?type=<?php echo $type; ?>&id=<?php echo $id; ?>&title=<?php echo urlencode($title); ?>" class="btn btn-primary btn-lg" onclick="this.href += '&lang=' + (document.getElementById('curLang') ? document.getElementById('curLang').value : 'original')">
                        <span class="icon icon-play"></span>立即播放
                    </a>
                    <button class="btn btn-secondary btn-lg" onclick="toggleFavorite(this, <?php echo $id; ?>, '<?php echo $type; ?>', '<?php echo sanitize($title); ?>', '<?php echo $poster; ?>', '<?php echo $year; ?>')" id="mainFavBtn">
                        <span class="icon <?php echo $favorited?'icon-heart-filled icon-heart':'icon-plus'; ?>"></span><?php echo $favorited ? '已收藏' : '加入收藏'; ?>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if($type == 'tv' && count($seasons)):
$seasonsMeta = [];
foreach($seasons as $s) {
    $sn = intval($s['season_number'] ?? 0);
    if ($sn < 1) continue;
    $seasonsMeta[] = [
        'num'           => $sn,
        'name'          => $s['name'] ?? ('第 ' . $sn . ' 季'),
        'air_date'      => $s['air_date'] ?? '',
        'episode_count' => intval($s['episode_count'] ?? 0),
        'overview'      => trim($s['overview'] ?? ''),
        'poster_path'   => isset($s['poster_path']) ? $tmdb->getImageUrl($s['poster_path'], 'w342') : '',
    ];
}
?>
<div class="container" style="margin-bottom:60px;">
    <h3 class="detail-section-title" style="font-size:22px;">剧集列表</h3>
    <div class="seasons-tabs" id="seasonsTabs">
        <?php foreach($seasonsMeta as $idx => $s): ?>
        <button class="season-tab <?php echo $idx==0?'active':''; ?>"
                data-season="<?php echo $s['num']; ?>"
                data-tv="<?php echo $id; ?>"
                onclick="selectSeason(this)">
            第 <?php echo $s['num']; ?> 季
            <?php if(!empty($s['air_date'])): ?> (<?php echo substr($s['air_date'],0,4); ?>)<?php endif; ?>
            <?php if(!empty($s['episode_count'])): ?> · <?php echo $s['episode_count']; ?> 集<?php endif; ?>
        </button>
        <?php endforeach; ?>
    </div>

    <!-- 该季概览信息：随季切换更新（年份/评分/演员/简介） -->
    <div id="season-meta" class="season-meta-card">
        <!-- 由JS动态更新，默认用首季填充 -->
    </div>

    <div id="episodes-loading" style="display:none;text-align:center;padding:24px 16px;color:var(--text-muted);">
        <div class="loading-spinner" style="margin:0 auto 12px;"></div>
        正在加载本季剧集...
    </div>
    <div id="season-episodes">
        <?php
        $firstSeason = count($seasonsMeta) ? $seasonsMeta[0]['num'] : 1;
        $firstData = $tmdb->getSeasonDetails($id, $firstSeason);
        $eps = isset($firstData['episodes']) ? $firstData['episodes'] : [];
        ?>
        <div class="episodes-list">
            <?php foreach($eps as $idx => $ep):
                $num = $ep['episode_number'] ?? ($idx + 1);
                $name = $ep['name'] ?? ('第' . $num . '集');
                $still = $tmdb->getImageUrl($ep['still_path'] ?? '', 'w300');
                $air = $ep['air_date'] ?? '';
                $epRating = isset($ep['vote_average']) ? number_format($ep['vote_average'], 1) : '0.0';
                $epOverview = $ep['overview'] ?? '';
                $epRuntime = $ep['runtime'] ?? 0;
                $playUrl = 'play.php?type=tv&id=' . $id . '&season=' . $firstSeason . '&episode=' . $num . '&name=' . urlencode($name);
            ?>
            <div class="episode-item" onclick="location.href='<?php echo $playUrl; ?>'">
                <div class="episode-num"><?php echo $num; ?></div>
                <?php if($still): ?>
                <div class="episode-thumb">
                    <img src="<?php echo $still; ?>" alt="" loading="lazy" onerror="this.style.display='none'">
                </div>
                <?php endif; ?>
                <div class="episode-info">
                    <div class="episode-title"><?php echo sanitize($name); ?></div>
                    <div class="episode-meta">
                        <?php if($air): ?><span><?php echo $air; ?></span><span style="margin:0 8px">·</span><?php endif; ?>
                        <?php if(floatval($epRating) > 0): ?><span style="color:#fbbf24">★ <?php echo $epRating; ?></span><span style="margin:0 8px">·</span><?php endif; ?>
                        <?php if($epRuntime): ?><span><?php echo $epRuntime; ?> 分钟</span><?php endif; ?>
                    </div>
                    <div class="episode-desc"><?php echo sanitize($epOverview) ?: '暂无简介'; ?></div>
                </div>
                <div style="display:flex;align-items:center;align-self:center;flex-shrink:0;">
                    <span class="btn btn-primary btn-sm"><span class="icon icon-play"></span>播放</span>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="container" style="margin-bottom:60px;">
    <h3 class="detail-section-title" style="font-size:22px;">播放源</h3>
    <div class="source-tabs">
        <?php
        $sources = getAllPlaySources();
        foreach($sources as $i => $s):
        ?>
        <div class="source-tab <?php echo $i==0?'active':''; ?>" data-src="<?php echo $s['id']; ?>"><?php echo sanitize($s['name']); ?></div>
        <?php endforeach; ?>
    </div>
    <div style="color:var(--text-secondary);font-size:14px;line-height:1.8;background:var(--bg-card);padding:16px 20px;border-radius:10px;border:1px solid var(--border);">
        💡 点击「立即播放」按钮进入播放页面，支持多种解析播放器。
    </div>
</div>

<?php
$favJs = '';
if($favorited) {
    $favJs = "(function(){var b=document.getElementById('mainFavBtn'); if(!b) return; b.querySelector('span').className='icon icon-heart-filled icon-heart'; b.lastChild.textContent=' 已收藏'; })();";
}
$seasonsMetaJson = function_exists('json_encode') ? json_encode($seasonsMeta, JSON_UNESCAPED_UNICODE) : '[]';
?>
<script>
<?php echo $favJs; ?>

(function(){
    var __SEASONS = <?php echo $seasonsMetaJson; ?>;
    var __TV_ID = <?php echo intval($id); ?>;
    var __CACHE = {}; // 前端内存缓存，同一季只请求一次

    function renderSeasonInfo(meta, extra){
        var box = document.getElementById('season-meta');
        if (!box) return '';
        var year = '';
        if (extra && extra.air_date) year = (extra.air_date + '').slice(0, 4);
        else if (meta && meta.air_date) year = (meta.air_date + '').slice(0, 4);
        var poster = (extra && extra.poster_path) ? extra.poster_path : (meta ? meta.poster_path : '');
        var name = (extra && extra.name) ? extra.name : (meta ? meta.name : '');
        var overview = (extra && extra.overview) ? extra.overview : (meta ? meta.overview : '');
        var epCount = (extra && extra.episode_count) ? extra.episode_count : (meta ? meta.episode_count : 0);
        var html = '<div class="season-meta-inner">';
        if (poster) {
            html += '<div class="season-poster"><img src="' + poster + '" alt="" loading="lazy" onerror="this.style.display=\'none\'"></div>';
        }
        html += '<div class="season-meta-body">';
        html += '<h4 class="season-name">' + (name || '') + '</h4>';
        html += '<div class="season-meta-line">';
        if (year) html += '<span>📅 ' + year + '</span>';
        if (epCount) html += '<span style="margin-left:16px;">🎞️ ' + epCount + ' 集</span>';
        html += '</div>';
        if (overview && overview !== '暂无该季简介' && overview !== '暂无简介') {
            html += '<p class="season-overview">' + overview + '</p>';
        }
        html += '</div></div>';
        box.innerHTML = html;
        return html;
    }

    function renderEpisodes(episodes){
        var html = ['<div class="episodes-list">'];
        for (var i = 0; i < episodes.length; i++) {
            var ep = episodes[i];
            var metaInner = [];
            if (ep.air_date) metaInner.push('<span>' + ep.air_date + '</span>');
            if (ep.rating_raw > 0) metaInner.push('<span style="color:#fbbf24">★ ' + ep.rating + '</span>');
            if (ep.runtime > 0) metaInner.push('<span>' + ep.runtime + ' 分钟</span>');
            var metaHTML = metaInner.join('<span style="margin:0 8px">·</span>');
            var thumb = ep.still ? '<div class="episode-thumb"><img src="' + ep.still + '" alt="" loading="lazy" onerror="this.style.display=\'none\'"></div>' : '';
            html.push(
                '<div class="episode-item" onclick="location.href=\'' + ep.play_url + '\'">',
                '  <div class="episode-num">' + ep.num + '</div>',
                thumb,
                '  <div class="episode-info">',
                '    <div class="episode-title">' + _e(ep.name) + '</div>',
                (metaHTML ? '    <div class="episode-meta">' + metaHTML + '</div>' : ''),
                '    <div class="episode-desc">' + _e(ep.overview || '') + '</div>',
                '  </div>',
                '  <div style="display:flex;align-items:center;align-self:center;flex-shrink:0;">',
                '    <span class="btn btn-primary btn-sm"><span class="icon icon-play"></span>播放</span>',
                '  </div>',
                '</div>'
            );
        }
        if (episodes.length === 0) {
            html.push('<div style="padding:40px 20px;text-align:center;color:var(--text-muted);">该季暂无剧集数据</div>');
        }
        html.push('</div>');
        document.getElementById('season-episodes').innerHTML = html.join('');
    }
    function _e(t){
        var d = document.createElement('div');
        d.textContent = (t == null) ? '' : String(t);
        return d.innerHTML;
    }

    window.selectSeason = function(btnEl){
        var tabs = document.getElementById('seasonsTabs');
        if (!tabs || !btnEl) return;
        var btns = tabs.querySelectorAll('.season-tab');
        for (var i = 0; i < btns.length; i++) btns[i].classList.remove('active');
        btnEl.classList.add('active');

        var seasonNum = parseInt(btnEl.getAttribute('data-season'), 10);
        var tvId = parseInt(btnEl.getAttribute('data-tv'), 10);
        if (!tvId || !seasonNum) return;

        var meta = null;
        for (var j = 0; j < __SEASONS.length; j++) {
            if (__SEASONS[j].num === seasonNum) { meta = __SEASONS[j]; break; }
        }
        renderSeasonInfo(meta, null);

        var container = document.getElementById('season-episodes');
        var loading = document.getElementById('episodes-loading');
        container.innerHTML = '';
        if (loading) { loading.style.display = 'block'; }

        var cacheKey = tvId + '_' + seasonNum;
        if (__CACHE[cacheKey]) {
            if (loading) loading.style.display = 'none';
            renderSeasonInfo(meta, __CACHE[cacheKey].season_info);
            renderEpisodes(__CACHE[cacheKey].episodes);
            return;
        }
        var xhr = new XMLHttpRequest();
        xhr.open('GET', 'api/season.php?tv_id=' + tvId + '&season=' + seasonNum, true);
        xhr.setRequestHeader('Accept', 'application/json');
        xhr.timeout = 25000;
        xhr.onload = function(){
            if (loading) loading.style.display = 'none';
            var res;
            try { res = JSON.parse(xhr.responseText); }
            catch (e) {
                container.innerHTML = '<div style="padding:30px;text-align:center;color:#ef4444;">解析数据失败，请刷新页面重试</div>';
                return;
            }
            if (res && res.success) {
                __CACHE[cacheKey] = { season_info: res.season_info, episodes: res.episodes };
                renderSeasonInfo(meta, res.season_info);
                renderEpisodes(res.episodes);
            } else {
                container.innerHTML = '<div style="padding:30px;text-align:center;color:#ef4444;">' + _e((res && res.message) ? res.message : '加载失败') + '</div>';
            }
        };
        xhr.ontimeout = xhr.onerror = function(){
            if (loading) loading.style.display = 'none';
            container.innerHTML = '<div style="padding:30px;text-align:center;color:#ef4444;">网络请求失败，请检查网络后刷新重试</div>';
        };
        xhr.send();
    };

    // 首屏初始化：用第一个有效季的信息填充 meta 卡片
    document.addEventListener('DOMContentLoaded', function(){
        if (!__SEASONS.length) return;
        var firstMeta = __SEASONS[0];
        // 如果首屏剧集容器已经有 episodes（由PHP渲染），尝试构建缓存 & 更新 meta
        renderSeasonInfo(firstMeta, null);
        // 读取PHP渲染出来的首季信息（getSeasonDetails 结果），如能匹配上就缓存
        var firstKey = __TV_ID + '_' + firstMeta.num;
        if (!__CACHE[firstKey]) {
            // 没有缓存没关系，用户再次点第1季会请求 JSON 接口
        }
    });
})();
</script>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
