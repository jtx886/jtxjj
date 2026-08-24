<?php
$pageTitle = '反馈管理';
$activeMenu = 'feedbacks';
require_once __DIR__ . '/header.php';

$db = Database::getInstance();
$msg = '';

$highlightId = intval($_GET['id'] ?? 0);

if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    if($action == 'reply') {
        $fid = intval($_POST['feedback_id'] ?? 0);
        $content = trim($_POST['content'] ?? '');
        if($fid && $content) {
            $db->insert('feedback_replies', [
                'feedback_id' => $fid,
                'user_id' => 0, // 0 for admin in general
                'content' => $content,
                'is_admin' => 1
            ]);
            // Also attach the admin user if we can get one (use first admin by username match)
            $admin = $db->fetchOne("SELECT id FROM users WHERE username = ?", [ADMIN_USER]);
            if($admin) {
                $last = $db->fetchOne("SELECT id FROM feedback_replies ORDER BY id DESC LIMIT 1");
                if($last) $db->update('feedback_replies', ['user_id' => $admin['id']], 'id = ?', [$last['id']]);
            }
            $msg = '回复成功';
        }
    } elseif($action == 'delete_feedback') {
        $id = intval($_POST['id'] ?? 0);
        if($id) {
            $db->delete('feedbacks','id=?',[$id]);
            $db->delete('feedback_replies','feedback_id=?',[$id]);
            $db->delete('feedback_likes','feedback_id=?',[$id]);
            $msg='已删除';
        }
    }
}

$search = trim($_GET['search'] ?? '');
$sql = "SELECT f.*, u.username, u.avatar FROM feedbacks f LEFT JOIN users u ON u.id=f.user_id WHERE 1=1";
$params = [];
if($search) { $sql .= " AND (f.title LIKE ? OR f.content LIKE ? OR u.username LIKE ?)"; $params[]="%$search%";$params[]="%$search%";$params[]="%$search%"; }
$sql .= " ORDER BY f.id DESC";
$list = $db->fetchAll($sql, $params);

$usersMap = [];
$userRows = $db->fetchAll("SELECT id, username, avatar FROM users");
foreach($userRows as $u) $usersMap[$u['id']] = $u;
$adminIds = [];
foreach($userRows as $u) if($u['username'] == ADMIN_USER) $adminIds[$u['id']] = true;
?>

<?php if($msg): ?><div class="alert alert-success"><?php echo $msg; ?></div><?php endif; ?>

<div class="page-header">
    <div>
        <h1 class="page-title">反馈管理</h1>
        <div class="page-desc">共 <?php echo count($list); ?> 条用户反馈，可查看详情并以管理员身份回复</div>
    </div>
</div>

<form method="GET" class="search-bar">
    <input type="text" name="search" class="form-control" placeholder="搜索标题、内容或用户名" value="<?php echo sanitize($search); ?>">
    <button class="btn btn-primary"><span class="icon icon-search"></span>搜索</button>
</form>

<div class="feedback-list" style="max-width:1100px;">
    <?php foreach($list as $f):
        $fid = $f['id'];
        $author = isset($usersMap[$f['user_id']]) ? $usersMap[$f['user_id']] : ['username'=>'匿名','avatar'=>''];
        $authorIsAdmin = isset($adminIds[$f['user_id']]);
        $likes = $db->fetchOne("SELECT COUNT(*) c FROM feedback_likes WHERE feedback_id=?",[$fid])['c'];
        $replies = $db->fetchAll("SELECT r.*, u.username, u.avatar FROM feedback_replies r LEFT JOIN users u ON u.id=r.user_id WHERE r.feedback_id=? ORDER BY r.id ASC", [$fid]);
        // Sort admin last as per request (display order: OP replies, then ADMIN on top of user replies)
        $opReplies = []; $adminReplies = []; $normReplies = [];
        foreach($replies as $r) {
            $isAdmin = $r['is_admin'] || isset($adminIds[$r['user_id']]);
            if($r['user_id'] == $f['user_id']) $opReplies[] = $r;
            elseif($isAdmin) $adminReplies[] = $r;
            else $normReplies[] = $r;
        }
        $sortedReplies = array_merge($opReplies, $adminReplies, $normReplies);
        $totalReplies = count($sortedReplies);
        $isHighlighted = ($highlightId == $fid);
    ?>
    <div class="feedback-card" id="fb-<?php echo $fid; ?>" style="<?php echo $isHighlighted ? 'border-color:var(--primary);box-shadow:0 0 0 3px rgba(139,92,246,0.15);' : ''; ?>">
        <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:12px;margin-bottom:12px;">
            <div class="feedback-header" style="margin-bottom:0;">
                <div class="user-avatar user-avatar-sm">
                    <?php if($author['avatar']): ?><img src="<?php echo sanitize($author['avatar']); ?>" alt=""><?php else: echo mb_substr($author['username'],0,1); endif; ?>
                </div>
                <div style="flex:1;">
                    <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
                        <span class="feedback-author <?php echo $authorIsAdmin ? 'admin' : ''; ?>">
                            <?php echo sanitize($author['username']); ?>
                            <?php if($authorIsAdmin): ?><span class="admin-badge">开发者</span><?php endif; ?>
                        </span>
                        <span class="feedback-time"><?php echo timeAgo($f['created_at']); ?></span>
                    </div>
                </div>
            </div>
            <div style="display:flex;gap:8px;flex-shrink:0;">
                <button class="icon-btn" title="删除反馈" onclick="if(confirm('确定删除此反馈？')){this.nextElementSibling.submit();}" style="color:var(--danger);"><span class="icon icon-trash"></span></button>
                <form method="POST" style="display:none;">
                    <input type="hidden" name="action" value="delete_feedback">
                    <input type="hidden" name="id" value="<?php echo $fid; ?>">
                </form>
            </div>
        </div>
        <h4 class="feedback-title"><?php echo sanitize($f['title']); ?></h4>
        <p class="feedback-content"><?php echo nl2br(sanitize($f['content'])); ?></p>
        <div class="feedback-actions">
            <span class="feedback-action" style="cursor:default;"><span class="icon icon-like"></span> 点赞 <span style="color:var(--text-primary)">(<?php echo $likes; ?>)</span></span>
            <span class="feedback-action" style="cursor:default;"><span class="icon icon-reply"></span> 回复 <span style="color:var(--text-primary)">(<?php echo $totalReplies; ?>)</span></span>
        </div>

        <?php if($totalReplies): ?>
        <div class="replies-section" style="display:flex;flex-direction:column;gap:10px;">
            <?php foreach($sortedReplies as $r):
                $rIsAdmin = $r['is_admin'] || isset($adminIds[$r['user_id']]);
                $rName = $r['username'] ?? '管理员';
                $rAvatar = $r['avatar'] ?? '';
                if(!$rName && $rIsAdmin) $rName = ADMIN_USER;
            ?>
            <div class="reply-item <?php echo $rIsAdmin ? 'admin-reply' : ''; ?>">
                <div class="user-avatar user-avatar-sm">
                    <?php if($rAvatar): ?><img src="<?php echo sanitize($rAvatar); ?>" alt=""><?php else: echo mb_substr($rName, 0, 1); endif; ?>
                </div>
                <div style="flex:1;">
                    <div class="reply-author-info">
                        <span class="reply-author-name" style="display:inline-flex;align-items:center;gap:8px;">
                            <?php echo sanitize($rName); ?>
                            <?php if($rIsAdmin): ?><span class="admin-badge">开发者</span><?php endif; ?>
                        </span>
                        <span class="reply-time">· <?php echo timeAgo($r['created_at']); ?></span>
                    </div>
                    <div class="reply-content"><?php echo nl2br(sanitize($r['content'])); ?></div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <div style="margin-top:16px;padding-top:16px;border-top:1px dashed var(--border);">
            <form method="POST">
                <input type="hidden" name="action" value="reply">
                <input type="hidden" name="feedback_id" value="<?php echo $fid; ?>">
                <div class="form-group" style="margin-bottom:10px;">
                    <label class="form-label" style="color:var(--primary);display:flex;align-items:center;gap:8px;">
                        <span class="admin-badge">开发者</span>
                        以管理员身份回复
                    </label>
                    <textarea name="content" class="form-control" rows="3" placeholder="输入回复内容，将以【开发者】标识展示在所有用户回复的最上方..." required></textarea>
                </div>
                <div style="text-align:right;">
                    <button class="btn btn-primary btn-sm"><span class="icon icon-reply"></span>发布回复</button>
                </div>
            </form>
        </div>
    </div>
    <?php endforeach;
    if(!count($list)): ?>
    <div class="data-card" style="text-align:center;padding:60px;color:var(--text-muted);">
        <div style="font-size:50px;margin-bottom:14px;opacity:0.3;">💬</div>
        <div>暂无反馈</div>
    </div>
    <?php endif; ?>
</div>

<?php
if($highlightId) echo '<script>document.addEventListener("DOMContentLoaded",function(){var el=document.getElementById("fb-'.$highlightId.'");if(el){el.scrollIntoView({behavior:"smooth",block:"center"});}});</script>';
require_once __DIR__ . '/footer.php'; ?>
