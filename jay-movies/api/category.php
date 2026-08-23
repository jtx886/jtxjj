<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/tmdb.php';

$tmdb = new TMDB();
$type = $_GET['type'] ?? 'movie';
$genre = intval($_GET['genre'] ?? 0);
$page = intval($_GET['page'] ?? 1);

$mapType = $type;
if($type == 'anime') $mapType = 'tv';
if($type == 'variety') $mapType = 'tv';

$db = Database::getInstance();
$favMap = [];
if(isLoggedIn()) {
    $favs = $db->fetchAll("SELECT media_id, media_type FROM favorites WHERE user_id = ?", [$_SESSION['user_id']]);
    foreach($favs as $f) { $favMap[$f['media_type'] . '_' . $f['media_id']] = true; }
}

if($type == 'anime') {
    $genre = 16;
    $result = $tmdb->getByGenre('tv', 16, $page);
} elseif($type == 'variety') {
    $result = $tmdb->getByGenre('tv', 10764, $page);
    if(!isset($result['results']) || count($result['results']) < 6) {
        $extra = $tmdb->getByGenre('tv', 35, $page);
        if(isset($extra['results'])) {
            $result['results'] = isset($result['results']) ? array_merge($result['results'], $extra['results']) : $extra['results'];
        }
    }
} elseif($genre) {
    $result = $tmdb->getByGenre($mapType, $genre, $page);
} else {
    $result = $tmdb->getPopular($mapType, $page);
}

$items = isset($result['results']) ? array_slice($result['results'], 0, 12) : [];
if(empty($items)) { echo '<div style="grid-column:1/-1;text-align:center;padding:60px;color:var(--text-muted)">暂无数据，请稍后再试</div>'; exit; }

function tTitle($m) { return isset($m['title']) ? $m['title'] : (isset($m['name']) ? $m['name'] : ''); }
function tYear($m) { $d = isset($m['release_date']) ? $m['release_date'] : (isset($m['first_air_date']) ? $m['first_air_date'] : ''); return $d ? substr($d,0,4) : ''; }
function tRating($m) { return isset($m['vote_average']) ? number_format($m['vote_average'],1) : '0.0'; }
$mt = in_array($type,['anime','variety']) ? 'tv' : $type;
$catLabel = ['movie'=>'电影','tv'=>'剧集','anime'=>'动漫','variety'=>'综艺'];
$label = $catLabel[$type] ?? '影视';

foreach($items as $m):
    $mid = $m['id'];
    $poster = $tmdb->getImageUrl($m['poster_path'], 'w342');
    $title = tTitle($m);
    $year = tYear($m);
    $rating = tRating($m);
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
<?php
endforeach;
?>
