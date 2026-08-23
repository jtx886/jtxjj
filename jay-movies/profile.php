<?php
require_once __DIR__ . '/includes/header.php';

requireLogin();

$db = Database::getInstance();
$user = currentUser();
$userId = $user['id'];
$tab = $_GET['tab'] ?? 'favorites';

$favorites = $db->fetchAll("SELECT * FROM favorites WHERE user_id = ? ORDER BY id DESC", [$userId]);
$history = $db->fetchAll("SELECT * FROM watch_history WHERE user_id = ? ORDER BY last_watch DESC", [$userId]);
$favCount = count($favorites);
$histCount = count($history);
$totalSeconds = 0;
foreach($history as $h) $totalSeconds += intval($h['play_seconds']);
$watchHours = round($totalSeconds / 3600, 1);

$success = '';
$error = '';
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_profile'])) {
    $username = trim($_POST['username'] ?? '');
    if(mb_strlen($username) < 2 || mb_strlen($username) > 20) {
        $error = '用户名长度 2-20';
    } else {
        $db->update('users', ['username' => $username], 'id = ?', [$userId]);
        $success = '保存成功';
        $user = currentUser();
    }
}
?>
<div class="container" style="padding-top:30px;">
    <div class="profile-header">
        <div class="profile-avatar">
            <div class="user-avatar user-avatar-lg" onclick="uploadAvatar()" style="cursor:pointer;" title="点击更换头像">
                <?php if(!empty($user['avatar'])): ?>
                <img src="<?php echo sanitize($user['avatar']); ?>" alt="avatar">
                <?php else: ?>
                <?php echo mb_substr($user['username'], 0, 1); ?>
                <?php endif; ?>
            </div>
            <div class="profile-avatar-edit" onclick="uploadAvatar()" title="更换头像">
                <span class="icon icon-edit" style="width:16px;height:16px;"></span>
            </div>
        </div>
        <div class="profile-info">
            <div class="profile-name"><?php echo sanitize($user['username']); ?></div>
            <div class="profile-email">📧 <?php echo sanitize($user['email']); ?></div>
            <?php if($user['banned']): ?>
            <div style="color:var(--danger);font-size:14px;margin-top:8px;">🚫 账号已封禁（<?php echo $user['ban_end'] ? '至 '.date('Y-m-d H:i',strtotime($user['ban_end'])) : '永久'; ?>）</div>
            <?php endif; ?>
            <div class="profile-stats" style="margin-top:16px;">
                <div class="profile-stat">
                    <div class="profile-stat-num"><?php echo $favCount; ?></div>
                    <div class="profile-stat-label">收藏数量</div>
                </div>
                <div class="profile-stat">
                    <div class="profile-stat-num"><?php echo $histCount; ?></div>
                    <div class="profile-stat-label">观看作品</div>
                </div>
                <div class="profile-stat">
                    <div class="profile-stat-num"><?php echo $watchHours; ?><span style="font-size:14px;">h</span></div>
                    <div class="profile-stat-label">观看时长</div>
                </div>
            </div>
        </div>
    </div>

    <?php if($success): ?><div class="alert alert-success"><?php echo $success; ?></div><?php endif; ?>
    <?php if($error): ?><div class="alert alert-danger"><?php echo $error; ?></div><?php endif; ?>

    <div class="tabs-wrap">
        <div class="tabs-nav">
            <div class="tab-item <?php echo $tab=='favorites'?'active':''; ?>" onclick="switchTab(this,'favorites')"><span class="icon icon-heart" style="width:16px;height:16px;"></span> 我的收藏</div>
            <div class="tab-item <?php echo $tab=='history'?'active':''; ?>" onclick="switchTab(this,'history')"><span class="icon icon-clock" style="width:16px;height:16px;"></span> 观看历史</div>
            <div class="tab-item <?php echo $tab=='settings'?'active':''; ?>" onclick="switchTab(this,'settings')"><span class="icon icon-settings" style="width:16px;height:16px;"></span> 账号设置</div>
        </div>

        <div class="tab-content <?php echo $tab=='favorites'?'active':''; ?>" id="tab-favorites">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
                <h3 style="font-size:18px;">收藏列表（共 <?php echo $favCount; ?> 部）</h3>
            </div>
            <div id="favList">
                <?php if(count($favorites)):
                foreach($favorites as $f): ?>
                <div class="list-row">
                    <a href="detail.php?type=<?php echo $f['media_type']; ?>&id=<?php echo $f['media_id']; ?>" class="list-poster">
                        <?php if($f['media_poster']): ?>
                        <img src="<?php echo sanitize($f['media_poster']); ?>" alt="">
                        <?php else: ?>
                        <div style="width:100%;height:100%;display:flex;align-items:center;justify-content:center;color:var(--text-muted);background:var(--bg-hover);">🎬</div>
                        <?php endif; ?>
                    </a>
                    <div class="list-info">
                        <a href="detail.php?type=<?php echo $f['media_type']; ?>&id=<?php echo $f['media_id']; ?>" class="list-title"><?php echo sanitize($f['media_title']); ?></a>
                        <div class="list-sub">
                            <span><?php echo $f['media_year']; ?></span>
                            <span><?php echo $f['media_type']=='movie'?'电影':'剧集'; ?></span>
                            <span>收藏于 <?php echo timeAgo($f['created_at']); ?></span>
                        </div>
                    </div>
                    <div class="list-actions">
                        <a href="play.php?type=<?php echo $f['media_type']; ?>&id=<?php echo $f['media_id']; ?>&title=<?php echo urlencode($f['media_title']); ?>" class="icon-btn primary" title="播放"><span class="icon icon-play"></span></a>
                        <button class="icon-btn" onclick="removeFavorite(<?php echo $f['id']; ?>, this)" title="移除"><span class="icon icon-trash"></span></button>
                    </div>
                </div>
                <?php endforeach;
                else: ?>
                <div class="empty-state">
                    <span class="icon icon-heart"></span>
                    <div class="empty-state-text">暂无收藏</div>
                    <div class="empty-state-desc">看到喜欢的影视，点击❤️收藏吧~</div>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="tab-content <?php echo $tab=='history'?'active':''; ?>" id="tab-history">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;flex-wrap:wrap;gap:10px;">
                <h3 style="font-size:18px;">观看历史（共 <?php echo $histCount; ?> 部）</h3>
                <?php if(count($history)): ?>
                <button class="btn btn-secondary btn-sm" onclick="clearAllHistory()"><span class="icon icon-trash"></span>清空全部</button>
                <?php endif; ?>
            </div>
            <div id="histList">
                <?php if(count($history)):
                foreach($history as $h): ?>
                <div class="list-row">
                    <a href="detail.php?type=<?php echo $h['media_type']; ?>&id=<?php echo $h['media_id']; ?>" class="list-poster">
                        <?php if($h['media_poster']): ?>
                        <img src="<?php echo sanitize($h['media_poster']); ?>" alt="">
                        <?php else: ?>
                        <div style="width:100%;height:100%;display:flex;align-items:center;justify-content:center;color:var(--text-muted);background:var(--bg-hover);">🎬</div>
                        <?php endif; ?>
                    </a>
                    <div class="list-info">
                        <a href="play.php?type=<?php echo $h['media_type']; ?>&id=<?php echo $h['media_id']; ?>&season=<?php echo $h['season']; ?>&episode=<?php echo $h['episode']; ?>&title=<?php echo urlencode($h['media_title']); ?>" class="list-title">
                            <?php echo sanitize($h['media_title']); ?>
                            <?php if($h['media_type']=='tv'): ?>
                            <span style="color:var(--text-muted);font-weight:400;font-size:13px;margin-left:8px;">第<?php echo $h['season']; ?>季 第<?php echo $h['episode']; ?>集</span>
                            <?php endif; ?>
                        </a>
                        <div class="list-sub">
                            <span>已观看 <?php echo formatDuration($h['play_seconds']); ?></span>
                            <span>最后观看：<?php echo timeAgo($h['last_watch']); ?></span>
                        </div>
                    </div>
                    <div class="list-actions">
                        <a href="play.php?type=<?php echo $h['media_type']; ?>&id=<?php echo $h['media_id']; ?>&season=<?php echo $h['season']; ?>&episode=<?php echo $h['episode']; ?>&title=<?php echo urlencode($h['media_title']); ?>" class="icon-btn primary" title="继续观看"><span class="icon icon-play"></span></a>
                        <button class="icon-btn" onclick="removeHistory(<?php echo $h['id']; ?>, this)" title="删除"><span class="icon icon-trash"></span></button>
                    </div>
                </div>
                <?php endforeach;
                else: ?>
                <div class="empty-state">
                    <span class="icon icon-clock"></span>
                    <div class="empty-state-text">暂无观看记录</div>
                    <div class="empty-state-desc">去看几部影片留下足迹吧~</div>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="tab-content <?php echo $tab=='settings'?'active':''; ?>" id="tab-settings">
            <div class="data-card">
                <div class="data-card-header">
                    <div class="data-card-title">账号设置</div>
                </div>
                <div class="data-card-body" style="padding:24px;">
                    <form method="POST" style="max-width:500px;">
                        <input type="hidden" name="save_profile" value="1">
                        <div class="form-group">
                            <label class="form-label">头像</label>
                            <div style="display:flex;align-items:center;gap:16px;">
                                <div class="user-avatar user-avatar-lg" style="cursor:pointer;" onclick="uploadAvatar()">
                                    <?php if(!empty($user['avatar'])): ?>
                                    <img src="<?php echo sanitize($user['avatar']); ?>" alt="avatar">
                                    <?php else: ?>
                                    <?php echo mb_substr($user['username'], 0, 1); ?>
                                    <?php endif; ?>
                                </div>
                                <div>
                                    <button type="button" class="btn btn-secondary" onclick="uploadAvatar()">更换头像</button>
                                    <div class="form-hint">支持 JPG/PNG，不超过 2MB</div>
                                </div>
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="form-label">邮箱地址</label>
                            <input type="text" class="form-control" value="<?php echo sanitize($user['email']); ?>" disabled>
                            <div class="form-hint">邮箱不可修改</div>
                        </div>
                        <div class="form-group">
                            <label class="form-label">用户名</label>
                            <input type="text" name="username" class="form-control" value="<?php echo sanitize($user['username']); ?>" required maxlength="20" minlength="2">
                        </div>
                        <div class="form-group">
                            <label class="form-label">注册时间</label>
                            <input type="text" class="form-control" value="<?php echo $user['created_at']; ?>" disabled>
                        </div>
                        <div class="form-group">
                            <label class="form-label">最后登录</label>
                            <input type="text" class="form-control" value="<?php echo $user['last_login'] ?: '暂无记录'; ?>" disabled>
                        </div>
                        <button type="submit" class="btn btn-primary"><span class="icon icon-edit"></span>保存修改</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
