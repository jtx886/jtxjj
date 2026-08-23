<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/tmdb.php';

$tmdb = new TMDB();
$tvId = intval($_GET['tv_id'] ?? 0);
$seasonNum = intval($_GET['season'] ?? 1);
if(!$tvId) { echo '<div style="padding:30px;text-align:center;color:var(--text-muted)">参数错误</div>'; exit; }

$season = $tmdb->getSeasonDetails($tvId, $seasonNum);
$episodes = isset($season['episodes']) ? $season['episodes'] : [];
if(empty($episodes)) { echo '<div style="padding:30px;text-align:center;color:var(--text-muted)">暂无剧集数据</div>'; exit; }
?>
<div class="episodes-list">
    <?php foreach($episodes as $idx => $ep):
        $num = $ep['episode_number'] ?? ($idx + 1);
        $name = $ep['name'] ?? ('第' . $num . '集');
        $still = $tmdb->getImageUrl($ep['still_path'] ?? '', 'w300');
        $air = $ep['air_date'] ?? '';
        $rating = isset($ep['vote_average']) ? number_format($ep['vote_average'], 1) : '0.0';
        $overview = $ep['overview'] ?? '';
        $runtime = $ep['runtime'] ?? 0;
    ?>
    <div class="episode-item" onclick="location.href='play.php?type=tv&id=<?php echo $tvId; ?>&season=<?php echo $seasonNum; ?>&episode=<?php echo $num; ?>&name=<?php echo urlencode($name); ?>'">
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
                <?php if($rating > 0): ?><span style="color:#fbbf24">★ <?php echo $rating; ?></span><span style="margin:0 8px">·</span><?php endif; ?>
                <?php if($runtime): ?><span><?php echo $runtime; ?> 分钟</span><?php endif; ?>
            </div>
            <div class="episode-desc"><?php echo sanitize($overview) ?: '暂无简介'; ?></div>
        </div>
        <div style="display:flex;align-items:center;align-self:center;flex-shrink:0;">
            <span class="btn btn-primary btn-sm"><span class="icon icon-play"></span>播放</span>
        </div>
    </div>
    <?php endforeach; ?>
</div>
