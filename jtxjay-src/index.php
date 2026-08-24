<?php
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/tmdb.php';

$tmdb = new TMDB();
$db = Database::getInstance();

// Get trending for hero slides
$trending = $tmdb->getTrending('all', 'week', 1);
$heroMovies = [];
if(isset($trending['results']) && count($trending['results']) > 0) {
    $heroMovies = array_slice($trending['results'], 0, 3);
}

// Popular movies
$popularMovies = $tmdb->getPopular('movie', 1);
$movies = isset($popularMovies['results']) ? $popularMovies['results'] : [];

// Popular TV
$popularTv = $tmdb->getPopular('tv', 1);
$tvShows = isset($popularTv['results']) ? $popularTv['results'] : [];

// Top rated
$topRated = $tmdb->getTopRated('movie', 1);
$topMovies = isset($topRated['results']) ? $topRated['results'] : [];

// On the air TV
$onAir = $tmdb->getOnTheAir(1);
$onAirTv = isset($onAir['results']) ? $onAir['results'] : [];

// User favorites for highlighting
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
?>

<div class="container">

<!-- Hero Slider -->
<section class="hero-section">
    <?php foreach($heroMovies as $idx => $m):
        $mediaType = jmGetType($m);
        $backdrop = $tmdb->getImageUrl($m['backdrop_path'], 'original');
        $poster = $tmdb->getImageUrl($m['poster_path'], 'w500');
        $mid = $m['id'];
    ?>
    <div class="hero-slide <?php echo $idx==0?'active':''; ?>">
        <div class="hero-bg" style="background-image:url('<?php echo $backdrop; ?>')"></div>
        <div class="hero-content">
            <div class="hero-badge"><span class="icon icon-flame"></span>正在热映</div>
            <h1 class="hero-title"><?php echo sanitize(jmGetTitle($m)); ?></h1>
            <div class="hero-meta">
                <span class="hero-rating"><span class="icon icon-star"></span><?php echo jmGetRating($m); ?></span>
                <span><?php echo jmGetYear($m); ?></span>
                <span><?php echo jmGetMediaLabel($mediaType); ?></span>
                <?php if(isset($m['origin_country']) && count($m['origin_country'])): ?>
                <span><?php echo implode('/', $m['origin_country']); ?></span>
                <?php endif; ?>
            </div>
            <p class="hero-desc"><?php echo sanitize($m['overview']); ?></p>
            <div class="hero-actions">
                <a href="play.php?type=<?php echo $mediaType; ?>&id=<?php echo $mid; ?>" class="btn btn-primary btn-lg">
                    <span class="icon icon-play"></span>立即播放
                </a>
                <button class="btn btn-secondary btn-lg" onclick="toggleFavorite(this, <?php echo $mid; ?>, '<?php echo $mediaType; ?>', '<?php echo sanitize(jmGetTitle($m)); ?>', '<?php echo $poster; ?>', '<?php echo jmGetYear($m); ?>')">
                    <span class="icon icon-plus"></span>收藏
                </button>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
    <div class="hero-dots">
        <?php for($i=0; $i<count($heroMovies); $i++): ?>
        <div class="hero-dot <?php echo $i==0?'active':''; ?>"></div>
        <?php endfor; ?>
    </div>
</section>

<!-- Hot Recommendation -->
<section class="section">
    <div class="section-header">
        <h2 class="section-title"><span class="icon icon-flame"></span>热门推荐</h2>
        <a href="search.php?q=热门" class="section-more">查看更多 <span class="icon icon-chevron icon-chevron-right"></span></a>
    </div>
    <div class="media-grid">
        <?php
        $hotItems = array_merge(array_slice($movies, 0, 3), array_slice($tvShows, 0, 3));
        $trendingResults = isset($trending['results']) ? $trending['results'] : [];
        $displayItems = array_slice($trendingResults, 0, 6);
        if(count($displayItems) < 6) $displayItems = array_merge($displayItems, $hotItems);
        $displayItems = array_slice($displayItems, 0, 6);
        foreach($displayItems as $m):
            $mediaType = jmGetType($m);
            $poster = $tmdb->getImageUrl($m['poster_path'], 'w342');
            $title = jmGetTitle($m);
            $year = jmGetYear($m);
            $rating = jmGetRating($m);
            $mid = $m['id'];
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
                <button class="media-fav-btn <?php echo isFavorited($mediaType, $mid, $userFavIds); ?>" onclick="event.stopPropagation(); toggleFavorite(this, <?php echo $mid; ?>, '<?php echo $mediaType; ?>', '<?php echo sanitize($title); ?>', '<?php echo $poster; ?>', '<?php echo $year; ?>')">
                    <span class="icon icon-heart"></span>
                </button>
            </div>
            <div class="media-info">
                <div class="media-title"><?php echo sanitize($title); ?></div>
                <div class="media-sub">
                    <span class="media-year"><?php echo $year; ?></span>
                    <span><?php echo jmGetMediaLabel($mediaType); ?></span>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</section>

<!-- Movies -->
<section class="section">
    <div class="section-header">
        <h2 class="section-title"><span class="icon icon-movie"></span>电影</h2>
        <a href="category.php?type=movie" class="section-more">查看更多 <span class="icon icon-chevron icon-chevron-right"></span></a>
    </div>
    <div class="category-tabs">
        <span class="cat-tab active" onclick="filterCategory(this, 'movie', 0)">全部</span>
        <span class="cat-tab" onclick="filterCategory(this, 'movie', 28)">动作</span>
        <span class="cat-tab" onclick="filterCategory(this, 'movie', 35)">喜剧</span>
        <span class="cat-tab" onclick="filterCategory(this, 'movie', 10749)">爱情</span>
        <span class="cat-tab" onclick="filterCategory(this, 'movie', 878)">科幻</span>
        <span class="cat-tab" onclick="filterCategory(this, 'movie', 9648)">悬疑</span>
        <span class="cat-tab" onclick="filterCategory(this, 'movie', 18)">剧情</span>
    </div>
    <div class="media-grid" id="mediaGrid">
        <?php foreach(array_slice($movies, 0, 6) as $m):
            $poster = $tmdb->getImageUrl($m['poster_path'], 'w342');
            $title = jmGetTitle($m);
            $year = jmGetYear($m);
            $rating = jmGetRating($m);
            $mid = $m['id'];
        ?>
        <div class="media-card" onclick="location.href='detail.php?type=movie&id=<?php echo $mid; ?>'">
            <div class="media-poster">
                <img src="<?php echo $poster; ?>" alt="<?php echo sanitize($title); ?>" onerror="this.src='data:image/svg+xml;utf8,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 200 300%22><rect fill=%22%23252532%22 width=%22200%22 height=%22300%22/></svg>'">
                <div class="media-poster-overlay">
                    <a href="play.php?type=movie&id=<?php echo $mid; ?>" class="btn btn-primary btn-sm" onclick="event.stopPropagation()">
                        <span class="icon icon-play"></span>播放
                    </a>
                </div>
                <div class="media-rating"><span class="icon icon-star"></span><?php echo $rating; ?></div>
                <button class="media-fav-btn <?php echo isFavorited('movie', $mid, $userFavIds); ?>" onclick="event.stopPropagation(); toggleFavorite(this, <?php echo $mid; ?>, 'movie', '<?php echo sanitize($title); ?>', '<?php echo $poster; ?>', '<?php echo $year; ?>')">
                    <span class="icon icon-heart"></span>
                </button>
            </div>
            <div class="media-info">
                <div class="media-title"><?php echo sanitize($title); ?></div>
                <div class="media-sub">
                    <span class="media-year"><?php echo $year; ?></span>
                    <span>电影</span>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</section>

<!-- TV Shows -->
<section class="section">
    <div class="section-header">
        <h2 class="section-title"><span class="icon icon-tv"></span>电视剧</h2>
        <a href="category.php?type=tv" class="section-more">查看更多 <span class="icon icon-chevron icon-chevron-right"></span></a>
    </div>
    <div class="media-grid">
        <?php foreach(array_slice($tvShows, 0, 6) as $m):
            $poster = $tmdb->getImageUrl($m['poster_path'], 'w342');
            $title = jmGetTitle($m);
            $year = jmGetYear($m);
            $rating = jmGetRating($m);
            $mid = $m['id'];
        ?>
        <div class="media-card" onclick="location.href='detail.php?type=tv&id=<?php echo $mid; ?>'">
            <div class="media-poster">
                <img src="<?php echo $poster; ?>" alt="<?php echo sanitize($title); ?>" onerror="this.src='data:image/svg+xml;utf8,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 200 300%22><rect fill=%22%23252532%22 width=%22200%22 height=%22300%22/></svg>'">
                <div class="media-poster-overlay">
                    <a href="play.php?type=tv&id=<?php echo $mid; ?>" class="btn btn-primary btn-sm" onclick="event.stopPropagation()">
                        <span class="icon icon-play"></span>播放
                    </a>
                </div>
                <div class="media-rating"><span class="icon icon-star"></span><?php echo $rating; ?></div>
                <button class="media-fav-btn <?php echo isFavorited('tv', $mid, $userFavIds); ?>" onclick="event.stopPropagation(); toggleFavorite(this, <?php echo $mid; ?>, 'tv', '<?php echo sanitize($title); ?>', '<?php echo $poster; ?>', '<?php echo $year; ?>')">
                    <span class="icon icon-heart"></span>
                </button>
            </div>
            <div class="media-info">
                <div class="media-title"><?php echo sanitize($title); ?></div>
                <div class="media-sub">
                    <span class="media-year"><?php echo $year; ?></span>
                    <span>剧集</span>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</section>

<!-- Anime (using animation genre for movies/TV) -->
<section class="section">
    <div class="section-header">
        <h2 class="section-title"><span class="icon icon-star"></span>动漫专区</h2>
        <a href="category.php?type=anime" class="section-more">查看更多 <span class="icon icon-chevron icon-chevron-right"></span></a>
    </div>
    <div class="media-grid">
        <?php
        $anime = $tmdb->getByGenre('tv', 16, 1);
        $animeList = isset($anime['results']) ? array_slice($anime['results'], 0, 6) : [];
        foreach($animeList as $m):
            $poster = $tmdb->getImageUrl($m['poster_path'], 'w342');
            $title = jmGetTitle($m);
            $year = jmGetYear($m);
            $rating = jmGetRating($m);
            $mid = $m['id'];
        ?>
        <div class="media-card" onclick="location.href='detail.php?type=tv&id=<?php echo $mid; ?>'">
            <div class="media-poster">
                <img src="<?php echo $poster; ?>" alt="<?php echo sanitize($title); ?>" onerror="this.src='data:image/svg+xml;utf8,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 200 300%22><rect fill=%22%23252532%22 width=%22200%22 height=%22300%22/></svg>'">
                <div class="media-poster-overlay">
                    <a href="play.php?type=tv&id=<?php echo $mid; ?>" class="btn btn-primary btn-sm" onclick="event.stopPropagation()">
                        <span class="icon icon-play"></span>播放
                    </a>
                </div>
                <div class="media-rating"><span class="icon icon-star"></span><?php echo $rating; ?></div>
                <button class="media-fav-btn <?php echo isFavorited('tv', $mid, $userFavIds); ?>" onclick="event.stopPropagation(); toggleFavorite(this, <?php echo $mid; ?>, 'tv', '<?php echo sanitize($title); ?>', '<?php echo $poster; ?>', '<?php echo $year; ?>')">
                    <span class="icon icon-heart"></span>
                </button>
            </div>
            <div class="media-info">
                <div class="media-title"><?php echo sanitize($title); ?></div>
                <div class="media-sub">
                    <span class="media-year"><?php echo $year; ?></span>
                    <span>动漫</span>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</section>

<!-- Variety (reality genre fallback) -->
<section class="section">
    <div class="section-header">
        <h2 class="section-title"><span class="icon icon-bell"></span>综艺娱乐</h2>
        <a href="category.php?type=variety" class="section-more">查看更多 <span class="icon icon-chevron icon-chevron-right"></span></a>
    </div>
    <div class="media-grid">
        <?php
        $variety = $tmdb->getByGenre('tv', 10764, 1);
        $vList = isset($variety['results']) ? array_slice($variety['results'], 0, 6) : [];
        if(count($vList) < 6) { $onAirFill = array_slice($onAirTv, count($vList), 6-count($vList)); $vList = array_merge($vList, $onAirFill); }
        foreach($vList as $m):
            $poster = $tmdb->getImageUrl($m['poster_path'], 'w342');
            $title = jmGetTitle($m);
            $year = jmGetYear($m);
            $rating = jmGetRating($m);
            $mid = $m['id'];
        ?>
        <div class="media-card" onclick="location.href='detail.php?type=tv&id=<?php echo $mid; ?>'">
            <div class="media-poster">
                <img src="<?php echo $poster; ?>" alt="<?php echo sanitize($title); ?>" onerror="this.src='data:image/svg+xml;utf8,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 200 300%22><rect fill=%22%23252532%22 width=%22200%22 height=%22300%22/></svg>'">
                <div class="media-poster-overlay">
                    <a href="play.php?type=tv&id=<?php echo $mid; ?>" class="btn btn-primary btn-sm" onclick="event.stopPropagation()">
                        <span class="icon icon-play"></span>播放
                    </a>
                </div>
                <div class="media-rating"><span class="icon icon-star"></span><?php echo $rating; ?></div>
                <button class="media-fav-btn <?php echo isFavorited('tv', $mid, $userFavIds); ?>" onclick="event.stopPropagation(); toggleFavorite(this, <?php echo $mid; ?>, 'tv', '<?php echo sanitize($title); ?>', '<?php echo $poster; ?>', '<?php echo $year; ?>')">
                    <span class="icon icon-heart"></span>
                </button>
            </div>
            <div class="media-info">
                <div class="media-title"><?php echo sanitize($title); ?></div>
                <div class="media-sub">
                    <span class="media-year"><?php echo $year; ?></span>
                    <span>综艺</span>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</section>

</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
