<?php
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/tmdb.php';

$tmdb = new TMDB();
$db = Database::getInstance();

$type = $_GET['type'] ?? 'movie';
$id = intval($_GET['id'] ?? 0);
if(!$id) { redirect('index.php'); }

$details = $tmdb->getDetails($type, $id);
if(!$details || isset($details['success']) && !$details['success']) {
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

<?php if($type == 'tv' && count($seasons)): ?>
<div class="container" style="margin-bottom:60px;">
    <h3 class="detail-section-title" style="font-size:22px;">剧集列表</h3>
    <div class="seasons-tabs">
        <?php foreach($seasons as $idx => $s):
            $sn = $s['season_number'] ?? ($idx + 1);
            if($sn < 1) continue;
        ?>
        <button class="season-tab <?php echo $idx==0?'active':''; ?>" onclick="selectSeason(this, <?php echo $sn; ?>, <?php echo $id; ?>)">
            第 <?php echo $sn; ?> 季
            <?php if(!empty($s['air_date'])): ?> (<?php echo substr($s['air_date'],0,4); ?>)<?php endif; ?>
            <?php if(!empty($s['episode_count'])): ?> · <?php echo $s['episode_count']; ?> 集<?php endif; ?>
        </button>
        <?php endforeach; ?>
    </div>
    <div id="episodes-loading" style="display:none;text-align:center;padding:30px;color:var(--text-muted);">加载中...</div>
    <div id="season-episodes">
        <?php
        $firstSeason = $seasons[0]['season_number'] ?? 1;
        if($firstSeason < 1 && count($seasons) > 1) $firstSeason = $seasons[1]['season_number'] ?? 1;
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
            ?>
            <div class="episode-item" onclick="location.href='play.php?type=tv&id=<?php echo $id; ?>&season=<?php echo $firstSeason; ?>&episode=<?php echo $num; ?>&name=<?php echo urlencode($name); ?>'">
                <div class="episode-num"><?php echo $num; ?></div>
                <?php if($still): ?>
                <div class="episode-thumb">
                    <img src="<?php echo $still; ?>" alt="" onerror="this.style.display='none'">
                </div>
                <?php endif; ?>
                <div class="episode-info">
                    <div class="episode-title"><?php echo sanitize($name); ?></div>
                    <div class="episode-meta">
                        <?php if($air): ?><span><?php echo $air; ?></span><span style="margin:0 8px">·</span><?php endif; ?>
                        <?php if($epRating > 0): ?><span style="color:#fbbf24">★ <?php echo $epRating; ?></span><span style="margin:0 8px">·</span><?php endif; ?>
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
    $favJs = "document.getElementById('mainFavBtn').onclick = function() { toggleFavorite(this, $id, '$type', '" . sanitize($title) . "', '$poster', '$year'); this.querySelector('span').className='icon icon-plus'; this.lastChild.textContent='加入收藏'; this.onclick = function() { toggleFavorite(this, $id, '$type', '" . sanitize($title) . "', '$poster', '$year'); }; };";
}
?>
<script>
<?php echo $favJs; ?>
</script>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
