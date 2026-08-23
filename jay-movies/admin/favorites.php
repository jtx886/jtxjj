<?php
$pageTitle = '用户收藏';
$activeMenu = 'favorites';
require_once __DIR__ . '/header.php';

$db = Database::getInstance();

$userId = intval($_GET['user_id'] ?? 0);
$search = trim($_GET['search'] ?? '');

$sql = "SELECT fav.*, u.username, u.avatar FROM favorites fav LEFT JOIN users u ON u.id=fav.user_id WHERE 1=1";
$params = [];
if($userId) { $sql .= " AND fav.user_id = ?"; $params[] = $userId; }
if($search) { $sql .= " AND (fav.media_title LIKE ? OR u.username LIKE ?)"; $params[]="%$search%"; $params[]="%$search%"; }
$sql .= " ORDER BY fav.id DESC LIMIT 500";
$rows = $db->fetchAll($sql, $params);

$byTypeStats = $db->fetchAll("SELECT media_type, COUNT(*) as c FROM favorites " . ($userId ? "WHERE user_id=$userId " : "") . "GROUP BY media_type");
$statsMap = [];
foreach($byTypeStats as $s) $statsMap[$s['media_type']] = $s['c'];
?>

<div class="page-header">
    <div>
        <h1 class="page-title">用户收藏管理</h1>
        <div class="page-desc">
            共 <?php echo count($rows); ?> 条收藏 · 
            电影 <strong style="color:var(--info);"><?php echo $statsMap['movie'] ?? 0; ?></strong> · 
            剧集 <strong style="color:var(--success);"><?php echo $statsMap['tv'] ?? 0; ?></strong>
            <?php if($userId): $u = $db->fetchOne("SELECT username FROM users WHERE id = ?", [$userId]); if($u): ?>
            · 筛选用户：<strong style="color:var(--primary);"><?php echo sanitize($u['username']); ?></strong>
            <?php endif; endif; ?>
        </div>
    </div>
    <?php if($userId): ?>
    <a href="favorites.php" class="btn btn-secondary btn-sm">清除筛选</a>
    <?php endif; ?>
</div>

<form method="GET" class="search-bar">
    <?php if($userId): ?><input type="hidden" name="user_id" value="<?php echo $userId; ?>"><?php endif; ?>
    <input type="text" name="search" class="form-control" placeholder="搜索影视标题或用户名" value="<?php echo sanitize($search); ?>">
    <select class="form-control" name="user_id" style="max-width:220px;" onchange="this.form.submit();">
        <option value="0">全部用户</option>
        <?php
        $allUsers = $db->fetchAll("SELECT id, username FROM users ORDER BY username ASC");
        foreach($allUsers as $u): ?>
        <option value="<?php echo $u['id']; ?>" <?php echo $userId==$u['id']?'selected':''; ?>>
            <?php echo sanitize($u['username']); ?>
        </option>
        <?php endforeach; ?>
    </select>
    <button class="btn btn-primary"><span class="icon icon-search"></span>搜索</button>
</form>

<div class="data-card">
    <div class="data-card-body" style="padding:0;">
        <table class="data-table">
            <thead>
                <tr>
                    <th>用户</th>
                    <th>影视</th>
                    <th>年份</th>
                    <th>类型</th>
                    <th>收藏时间</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($rows as $r): ?>
                <tr>
                    <td>
                        <a href="favorites.php?user_id=<?php echo $r['user_id']; ?>" style="display:flex;align-items:center;gap:10px;text-decoration:none;">
                            <div class="user-avatar user-avatar-sm">
                                <?php if($r['avatar']): ?><img src="<?php echo sanitize($r['avatar']); ?>" alt=""><?php else: echo mb_substr($r['username'] ?? '?', 0,1); endif; ?>
                            </div>
                            <div style="font-weight:600;"><?php echo sanitize($r['username'] ?? '未知用户'); ?></div>
                        </a>
                    </td>
                    <td>
                        <a href="../detail.php?type=<?php echo $r['media_type']; ?>&id=<?php echo $r['media_id']; ?>" style="display:flex;align-items:center;gap:10px;">
                            <?php if($r['media_poster']): ?>
                            <div style="width:40px;aspect-ratio:2/3;background:var(--bg-hover);border-radius:6px;overflow:hidden;flex-shrink:0;">
                                <img src="<?php echo sanitize($r['media_poster']); ?>" alt="" style="width:100%;height:100%;object-fit:cover;">
                            </div>
                            <?php endif; ?>
                            <div style="min-width:0;">
                                <div style="font-weight:500;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;max-width:260px;"><?php echo sanitize($r['media_title']); ?></div>
                                <div style="font-size:12px;color:var(--text-muted);">TMDB ID: <?php echo $r['media_id']; ?></div>
                            </div>
                        </a>
                    </td>
                    <td style="font-size:13px;color:var(--text-muted);"><?php echo $r['media_year'] ?: '-'; ?></td>
                    <td><span class="badge badge-<?php echo $r['media_type']=='movie'?'info':'success'; ?>"><?php echo $r['media_type']=='movie'?'电影':'剧集'; ?></span></td>
                    <td style="color:var(--text-muted);font-size:13px;"><?php echo timeAgo($r['created_at']); ?></td>
                </tr>
                <?php endforeach;
                if(!count($rows)): ?>
                <tr><td colspan="5" style="text-align:center;padding:60px;color:var(--text-muted);">暂无收藏数据</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
