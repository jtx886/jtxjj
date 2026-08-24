<?php
$pageTitle = '仪表盘';
$activeMenu = 'dashboard';
require_once __DIR__ . '/header.php';

$db = Database::getInstance();

$newUsers = $db->fetchAll("SELECT * FROM users ORDER BY id DESC LIMIT 8");
$newFbs = $db->fetchAll("SELECT f.*, u.username, u.avatar 
    FROM feedbacks f LEFT JOIN users u ON u.id=f.user_id 
    ORDER BY f.id DESC LIMIT 8");
$newHist = $db->fetchAll("SELECT w.*, u.username, u.avatar 
    FROM watch_history w LEFT JOIN users u ON u.id=w.user_id 
    ORDER BY w.last_watch DESC LIMIT 8");
$newFav = $db->fetchAll("SELECT fav.*, u.username, u.avatar 
    FROM favorites fav LEFT JOIN users u ON u.id=fav.user_id 
    ORDER BY fav.id DESC LIMIT 8");

// Compute summary — 兼容 MySQL(CURDATE) 和 SQLite(DATE('now'))
$_todayFn = ($db->getDriver() === 'mysql') ? 'CURDATE()' : "DATE('now')";
$todayUsers = $db->fetchOne("SELECT COUNT(*) as c FROM users WHERE DATE(created_at) = $_todayFn")['c'];
$todayHist = $db->fetchOne("SELECT COUNT(*) as c FROM watch_history WHERE DATE(last_watch) = $_todayFn")['c'];
$todayFav = $db->fetchOne("SELECT COUNT(*) as c FROM favorites WHERE DATE(created_at) = $_todayFn")['c'];
$todayFb = $db->fetchOne("SELECT COUNT(*) as c FROM feedbacks WHERE DATE(created_at) = $_todayFn")['c'];
?>

<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-header">
            <div class="stat-icon purple"><span class="icon icon-user"></span></div>
            <span class="badge badge-info">今日 +<?php echo $todayUsers; ?></span>
        </div>
        <div class="stat-value"><?php echo $totalUsers; ?></div>
        <div class="stat-label">注册用户</div>
    </div>
    <div class="stat-card">
        <div class="stat-header">
            <div class="stat-icon blue"><span class="icon icon-clock"></span></div>
            <span class="badge badge-info">今日 +<?php echo $todayHist; ?></span>
        </div>
        <div class="stat-value"><?php echo $totalHist; ?></div>
        <div class="stat-label">观看记录</div>
    </div>
    <div class="stat-card">
        <div class="stat-header">
            <div class="stat-icon green"><span class="icon icon-heart"></span></div>
            <span class="badge badge-info">今日 +<?php echo $todayFav; ?></span>
        </div>
        <div class="stat-value"><?php echo $totalFav; ?></div>
        <div class="stat-label">收藏总数</div>
    </div>
    <div class="stat-card">
        <div class="stat-header">
            <div class="stat-icon orange"><span class="icon icon-edit"></span></div>
            <span class="badge badge-info">今日 +<?php echo $todayFb; ?></span>
        </div>
        <div class="stat-value"><?php echo $totalFb; ?></div>
        <div class="stat-label">反馈总数</div>
    </div>
</div>

<div class="grid-2" style="margin-bottom:24px;">
    <div class="data-card">
        <div class="data-card-header">
            <div class="data-card-title" style="display:flex;align-items:center;gap:10px;"><span class="icon icon-user" style="color:var(--primary);width:18px;height:18px;"></span>最新注册用户</div>
            <a href="users.php" class="section-more" style="font-size:13px;">查看全部</a>
        </div>
        <div class="data-card-body" style="padding:0;">
            <table class="data-table">
                <thead><tr><th>用户</th><th>邮箱</th><th>状态</th><th>注册时间</th></tr></thead>
                <tbody>
                    <?php foreach($newUsers as $u): ?>
                    <tr>
                        <td>
                            <div class="user-cell">
                                <div class="user-avatar user-avatar-sm">
                                    <?php if($u['avatar']): ?><img src="<?php echo sanitize($u['avatar']); ?>" alt=""><?php else: echo mb_substr($u['username'],0,1); endif; ?>
                                </div>
                                <div style="font-weight:600;"><?php echo sanitize($u['username']); ?></div>
                            </div>
                        </td>
                        <td style="color:var(--text-secondary);font-size:13px;"><?php echo sanitize($u['email']); ?></td>
                        <td>
                            <?php if($u['banned']): ?><span class="badge badge-danger">已封禁</span>
                            <?php else: ?><span class="badge badge-success">正常</span><?php endif; ?>
                        </td>
                        <td style="color:var(--text-muted);font-size:13px;"><?php echo timeAgo($u['created_at']); ?></td>
                    </tr>
                    <?php endforeach;
                    if(!count($newUsers)): ?>
                    <tr><td colspan="4" style="text-align:center;padding:40px;color:var(--text-muted);">暂无数据</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="data-card">
        <div class="data-card-header">
            <div class="data-card-title" style="display:flex;align-items:center;gap:10px;"><span class="icon icon-edit" style="color:var(--warning);width:18px;height:18px;"></span>最新反馈</div>
            <a href="feedbacks.php" class="section-more" style="font-size:13px;">查看全部</a>
        </div>
        <div class="data-card-body" style="padding:0;">
            <table class="data-table">
                <thead><tr><th>用户</th><th>标题</th><th>回复</th><th>时间</th></tr></thead>
                <tbody>
                    <?php foreach($newFbs as $f):
                        $replyCount = $db->fetchOne("SELECT COUNT(*) as c FROM feedback_replies WHERE feedback_id = ?", [$f['id']])['c'];
                    ?>
                    <tr style="cursor:pointer;" onclick="location.href='feedbacks.php?id=<?php echo $f['id']; ?>'">
                        <td>
                            <div class="user-cell">
                                <div class="user-avatar user-avatar-sm">
                                    <?php if($f['avatar']): ?><img src="<?php echo sanitize($f['avatar']); ?>" alt=""><?php else: echo mb_substr($f['username'] ?? 'A', 0,1); endif; ?>
                                </div>
                                <div style="font-weight:500;"><?php echo sanitize($f['username'] ?? '匿名'); ?></div>
                            </div>
                        </td>
                        <td style="color:var(--text-secondary);font-size:13px;max-width:240px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?php echo sanitize($f['title']); ?></td>
                        <td><span class="badge badge-info"><?php echo $replyCount; ?> 条</span></td>
                        <td style="color:var(--text-muted);font-size:13px;"><?php echo timeAgo($f['created_at']); ?></td>
                    </tr>
                    <?php endforeach;
                    if(!count($newFbs)): ?>
                    <tr><td colspan="4" style="text-align:center;padding:40px;color:var(--text-muted);">暂无数据</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="grid-2">
    <div class="data-card">
        <div class="data-card-header">
            <div class="data-card-title" style="display:flex;align-items:center;gap:10px;"><span class="icon icon-clock" style="color:var(--info);width:18px;height:18px;"></span>最新观看历史</div>
            <a href="history.php" class="section-more" style="font-size:13px;">查看全部</a>
        </div>
        <div class="data-card-body" style="padding:0;">
            <table class="data-table">
                <thead><tr><th>用户</th><th>影视</th><th>进度</th><th>时间</th></tr></thead>
                <tbody>
                    <?php foreach($newHist as $h): ?>
                    <tr>
                        <td>
                            <div class="user-cell">
                                <div class="user-avatar user-avatar-sm">
                                    <?php if($h['avatar']): ?><img src="<?php echo sanitize($h['avatar']); ?>" alt=""><?php else: echo mb_substr($h['username'] ?? 'A',0,1); endif; ?>
                                </div>
                                <div style="font-weight:500;"><?php echo sanitize($h['username'] ?? '用户'); ?></div>
                            </div>
                        </td>
                        <td style="color:var(--text-secondary);font-size:13px;">
                            <?php echo sanitize($h['media_title']); ?>
                            <?php if($h['media_type']=='tv'): ?>
                            <div style="font-size:11px;color:var(--text-muted);">S<?php echo $h['season']; ?>E<?php echo $h['episode']; ?></div>
                            <?php endif; ?>
                        </td>
                        <td style="font-size:13px;color:var(--text-secondary);"><?php echo formatDuration($h['play_seconds']); ?></td>
                        <td style="color:var(--text-muted);font-size:13px;"><?php echo timeAgo($h['last_watch']); ?></td>
                    </tr>
                    <?php endforeach;
                    if(!count($newHist)): ?>
                    <tr><td colspan="4" style="text-align:center;padding:40px;color:var(--text-muted);">暂无数据</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="data-card">
        <div class="data-card-header">
            <div class="data-card-title" style="display:flex;align-items:center;gap:10px;"><span class="icon icon-heart" style="color:var(--success);width:18px;height:18px;"></span>最新用户收藏</div>
            <a href="favorites.php" class="section-more" style="font-size:13px;">查看全部</a>
        </div>
        <div class="data-card-body" style="padding:0;">
            <table class="data-table">
                <thead><tr><th>用户</th><th>影视</th><th>类型</th><th>时间</th></tr></thead>
                <tbody>
                    <?php foreach($newFav as $f): ?>
                    <tr>
                        <td>
                            <div class="user-cell">
                                <div class="user-avatar user-avatar-sm">
                                    <?php if($f['avatar']): ?><img src="<?php echo sanitize($f['avatar']); ?>" alt=""><?php else: echo mb_substr($f['username'] ?? 'A',0,1); endif; ?>
                                </div>
                                <div style="font-weight:500;"><?php echo sanitize($f['username'] ?? '用户'); ?></div>
                            </div>
                        </td>
                        <td style="color:var(--text-secondary);font-size:13px;max-width:200px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?php echo sanitize($f['media_title']); ?></td>
                        <td><span class="badge badge-info"><?php echo $f['media_type']=='movie'?'电影':'剧集'; ?></span></td>
                        <td style="color:var(--text-muted);font-size:13px;"><?php echo timeAgo($f['created_at']); ?></td>
                    </tr>
                    <?php endforeach;
                    if(!count($newFav)): ?>
                    <tr><td colspan="4" style="text-align:center;padding:40px;color:var(--text-muted);">暂无数据</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
