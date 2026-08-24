<?php
$pageTitle = '用户列表';
$activeMenu = 'users';
require_once __DIR__ . '/header.php';

$db = Database::getInstance();

$search = trim($_GET['search'] ?? '');
$filter = $_GET['filter'] ?? 'all';

$sql = "SELECT * FROM users WHERE 1=1";
$params = [];
if($search) { $sql .= " AND (username LIKE ? OR email LIKE ?)"; $params[] = "%$search%"; $params[] = "%$search%"; }
if($filter == 'banned') $sql .= " AND banned = 1";
if($filter == 'normal') $sql .= " AND banned = 0";
$sql .= " ORDER BY id DESC";
$users = $db->fetchAll($sql, $params);
?>

<div class="page-header">
    <div>
        <h1 class="page-title">用户管理</h1>
        <div class="page-desc">共 <?php echo count($users); ?> 名用户，其中封禁 <?php echo $totalBanned; ?> 名</div>
    </div>
</div>

<div class="search-bar">
    <form method="GET" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
        <input type="text" name="search" class="form-control" placeholder="搜索用户名或邮箱" value="<?php echo sanitize($search); ?>">
        <select name="filter" class="form-control" style="max-width:140px;">
            <option value="all" <?php echo $filter=='all'?'selected':''; ?>>全部</option>
            <option value="normal" <?php echo $filter=='normal'?'selected':''; ?>>正常用户</option>
            <option value="banned" <?php echo $filter=='banned'?'selected':''; ?>>已封禁</option>
        </select>
        <button class="btn btn-primary"><span class="icon icon-search"></span>搜索</button>
    </form>
</div>

<div class="data-card">
    <div class="data-card-body" style="padding:0;overflow-x:auto;">
        <table class="data-table">
            <thead>
                <tr>
                    <th>用户</th>
                    <th>邮箱</th>
                    <th>注册时间</th>
                    <th>最后登录</th>
                    <th>观看数</th>
                    <th>收藏数</th>
                    <th>状态</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($users as $u):
                    $h = $db->fetchOne("SELECT COUNT(*) as c FROM watch_history WHERE user_id = ?", [$u['id']])['c'];
                    $f = $db->fetchOne("SELECT COUNT(*) as c FROM favorites WHERE user_id = ?", [$u['id']])['c'];
                ?>
                <tr>
                    <td>
                        <div class="user-cell">
                            <div class="user-avatar user-avatar-sm">
                                <?php if($u['avatar']): ?><img src="<?php echo sanitize($u['avatar']); ?>" alt=""><?php else: echo mb_substr($u['username'],0,1); endif; ?>
                            </div>
                            <div>
                                <div style="font-weight:600;"><?php echo sanitize($u['username']); ?></div>
                                <?php if($u['username'] == ADMIN_USER): ?>
                                <span class="admin-badge" style="font-size:10px;padding:2px 6px;">开发者</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </td>
                    <td style="color:var(--text-secondary);font-size:13px;"><?php echo sanitize($u['email']); ?></td>
                    <td style="font-size:13px;color:var(--text-muted);"><?php echo date('Y-m-d', strtotime($u['created_at'])); ?></td>
                    <td style="font-size:13px;color:var(--text-muted);"><?php echo $u['last_login'] ? timeAgo($u['last_login']) : '从未'; ?></td>
                    <td><span class="badge badge-info"><?php echo $h; ?></span></td>
                    <td><span class="badge badge-success"><?php echo $f; ?></span></td>
                    <td>
                        <?php if($u['banned']): ?>
                        <span class="badge badge-danger" title="原因：<?php echo sanitize($u['ban_reason']); ?>，到期：<?php echo $u['ban_end'] ? date('Y-m-d',strtotime($u['ban_end'])) : '永久'; ?>">封禁中</span>
                        <?php else: ?>
                        <span class="badge badge-success">正常</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div class="actions-cell">
                            <button class="icon-btn" title="发邮件通知" onclick="sendUserMail(<?php echo $u['id']; ?>)" style="color:var(--info);"><span class="icon icon-bell"></span></button>
                            <?php if($u['username'] != ADMIN_USER): ?>
                                <?php if($u['banned']): ?>
                                <button class="icon-btn" title="解封" onclick="unbanUser(<?php echo $u['id']; ?>)" style="color:var(--success);"><span class="icon icon-edit"></span></button>
                                <?php else: ?>
                                <button class="icon-btn" title="封禁用户" onclick="banUser(<?php echo $u['id']; ?>)" style="color:var(--danger);"><span class="icon icon-trash"></span></button>
                                <?php endif; ?>
                            <?php endif; ?>
                            <a href="history.php?user_id=<?php echo $u['id']; ?>" class="icon-btn" title="查看历史"><span class="icon icon-clock"></span></a>
                            <a href="favorites.php?user_id=<?php echo $u['id']; ?>" class="icon-btn" title="查看收藏"><span class="icon icon-heart"></span></a>
                        </div>
                    </td>
                </tr>
                <?php endforeach;
                if(!count($users)): ?>
                <tr><td colspan="8" style="text-align:center;padding:60px;color:var(--text-muted);">没有找到符合条件的用户</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
