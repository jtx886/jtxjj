<?php
require_once __DIR__ . '/includes/header.php';

$db = Database::getInstance();
$submitError = '';
$submitSuccess = '';

if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_feedback'])) {
    if(!isLoggedIn()) { redirect('login.php?need_login=1'); }
    $title = trim($_POST['title'] ?? '');
    $content = trim($_POST['content'] ?? '');
    if(!$title || !$content) { $submitError = '标题和内容不能为空'; }
    elseif(mb_strlen($title) < 5) { $submitError = '标题至少5个字'; }
    elseif(mb_strlen($content) < 10) { $submitError = '内容至少10个字'; }
    else {
        $db->insert('feedbacks', ['user_id'=>$_SESSION['user_id'], 'title'=>$title, 'content'=>$content]);
        $submitSuccess = '反馈提交成功！感谢您的建议。';
    }
}

// Fetch feedbacks with users, likes counts and replies
$feedbacks = $db->fetchAll("SELECT f.*, u.username, u.avatar 
    FROM feedbacks f LEFT JOIN users u ON u.id = f.user_id 
    ORDER BY f.id DESC LIMIT 50");

// Get current user liked
$likedIds = [];
if(isLoggedIn()) {
    $rows = $db->fetchAll("SELECT feedback_id FROM feedback_likes WHERE user_id = ?", [$_SESSION['user_id']]);
    foreach($rows as $r) $likedIds[$r['feedback_id']] = true;
}

// Get users map for replies
$usersById = [];
$userRows = $db->fetchAll("SELECT id, username, avatar FROM users");
foreach($userRows as $u) $usersById[$u['id']] = $u;

$adminUserIds = [];
// Detect admin users: either admin session or username match constant
foreach($userRows as $u) {
    if($u['username'] == ADMIN_USER) $adminUserIds[$u['id']] = true;
}
?>

<div class="container" style="padding-top:30px;">
    <div class="page-header">
        <div>
            <h1 class="page-title">问题反馈</h1>
            <div class="page-desc">有问题？有建议？告诉我们，让 Jay影视 变得更好！</div>
        </div>
    </div>

    <?php if(!isLoggedIn()): ?>
    <div class="alert alert-info" style="display:flex;gap:12px;align-items:center;">
        <span class="icon icon-bell"></span>
        <span><a href="login.php" style="color:var(--primary);font-weight:600;">登录</a> 后才能发布反馈和回复哦~</span>
    </div>
    <?php else: ?>
    <?php if($submitError): ?><div class="alert alert-danger" style="display:flex;gap:10px;"><?php echo $submitError; ?></div><?php endif; ?>
    <?php if($submitSuccess): ?><div class="alert alert-success" style="display:flex;gap:10px;"><?php echo $submitSuccess; ?></div><?php endif; ?>

    <div class="feedback-form">
        <h3 style="font-size:18px;margin-bottom:18px;display:flex;align-items:center;gap:10px;"><span class="icon icon-edit" style="color:var(--primary);width:22px;height:22px;"></span>发布反馈</h3>
        <form method="POST">
            <input type="hidden" name="submit_feedback" value="1">
            <div class="form-group">
                <label class="form-label">反馈标题</label>
                <input type="text" name="title" class="form-control" placeholder="请简要描述问题或建议（5-50字）" maxlength="50" required value="<?php echo sanitize($_POST['title'] ?? ''); ?>">
            </div>
            <div class="form-group">
                <label class="form-label">详细内容</label>
                <textarea name="content" class="form-control" placeholder="请详细描述问题、遇到的场景、影视名称等（10-2000字）" required minlength="10" maxlength="2000"><?php echo sanitize($_POST['content'] ?? ''); ?></textarea>
            </div>
            <div style="text-align:right;">
                <button type="submit" class="btn btn-primary"><span class="icon icon-edit"></span>提交反馈</button>
            </div>
        </form>
    </div>
    <?php endif; ?>

    <div style="margin:10px 0 20px;display:flex;align-items:center;gap:10px;">
        <h3 style="font-size:18px;">全部反馈 (<?php echo count($feedbacks); ?>)</h3>
    </div>

    <div class="feedback-list">
        <?php foreach($feedbacks as $f):
            $fid = $f['id'];
            $authorId = $f['user_id'];
            $author = isset($usersById[$authorId]) ? $usersById[$authorId] : ['username'=>'匿名用户','avatar'=>''];
            $authorAvatar = $author['avatar'] ?? '';
            $authorName = $author['username'] ?? '匿名';
            $authorIsAdmin = isset($adminUserIds[$authorId]);

            $likes = $db->fetchOne("SELECT COUNT(*) as c FROM feedback_likes WHERE feedback_id = ?", [$fid])['c'];
            $liked = isset($likedIds[$fid]);

            // Fetch replies: first the OP feedback author's reply, then ADMIN replies on top, then other users. Limit to 3 visible initially if more than 3.
            $allReplies = $db->fetchAll("SELECT r.*, u.username, u.avatar FROM feedback_replies r LEFT JOIN users u ON u.id = r.user_id WHERE r.feedback_id = ? ORDER BY r.id ASC", [$fid]);
            // Sort: admin replies first, normal later, keep order
            $adminReplies = []; $normalReplies = []; $opReplies = [];
            foreach($allReplies as $r) {
                $isAdmin = $r['is_admin'] || isset($adminUserIds[$r['user_id']]);
                if($isAdmin) $adminReplies[] = $r;
                else if($r['user_id'] == $authorId) $opReplies[] = $r;
                else $normalReplies[] = $r;
            }
            $sortedReplies = array_merge($opReplies, $adminReplies, $normalReplies);
            $totalReplies = count($sortedReplies);
            $showCount = 3;
            $hasMore = $totalReplies > $showCount;
        ?>
        <div class="feedback-card">
            <div class="feedback-header">
                <div class="user-avatar user-avatar-sm">
                    <?php if($authorAvatar): ?><img src="<?php echo sanitize($authorAvatar); ?>" alt=""><?php else: echo mb_substr($authorName, 0, 1); endif; ?>
                </div>
                <div style="flex:1;">
                    <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
                        <span class="feedback-author <?php echo $authorIsAdmin ? 'admin' : ''; ?>">
                            <?php echo sanitize($authorName); ?>
                            <?php if($authorIsAdmin): ?>
                            <span class="admin-badge">开发者</span>
                            <?php endif; ?>
                        </span>
                        <span class="feedback-time"><?php echo timeAgo($f['created_at']); ?></span>
                    </div>
                </div>
            </div>
            <h4 class="feedback-title"><?php echo sanitize($f['title']); ?></h4>
            <p class="feedback-content"><?php echo nl2br(sanitize($f['content'])); ?></p>

            <div class="feedback-actions">
                <div class="feedback-action like <?php echo $liked ? 'liked' : ''; ?>" onclick="toggleLike(this, <?php echo $fid; ?>)">
                    <span class="icon icon-like"></span>
                    <span>点赞</span>
                    <span class="count">(<?php echo $likes; ?>)</span>
                </div>
                <div class="feedback-action" onclick="toggleReplyForm(this, <?php echo $fid; ?>)">
                    <span class="icon icon-reply"></span>
                    <span>回复 (<?php echo $totalReplies; ?>)</span>
                </div>
            </div>

            <?php if($totalReplies > 0): ?>
            <div class="replies-section">
                <?php if($hasMore): ?>
                <div class="replies-more" onclick="toggleReplies(this, <?php echo $fid; ?>)">展开全部回复（共 <?php echo $totalReplies; ?> 条）</div>
                <?php endif; ?>
                <div id="replies-<?php echo $fid; ?>" class="<?php echo $hasMore ? 'replies-hidden' : ''; ?>">
                    <?php foreach($sortedReplies as $ri => $r):
                        $rUserId = $r['user_id'];
                        $rIsAdmin = $r['is_admin'] || isset($adminUserIds[$rUserId]);
                        $rName = $r['username'] ?? '匿名';
                        $rAvatar = $r['avatar'] ?? '';
                    ?>
                    <div class="reply-item <?php echo $rIsAdmin ? 'admin-reply' : ''; ?>">
                        <div class="user-avatar user-avatar-sm" style="flex-shrink:0;">
                            <?php if($rAvatar): ?><img src="<?php echo sanitize($rAvatar); ?>" alt=""><?php else: echo mb_substr($rName, 0, 1); endif; ?>
                        </div>
                        <div style="flex:1;min-width:0;">
                            <div class="reply-author-info">
                                <span class="reply-author-name <?php echo $rIsAdmin ? 'admin' : ''; ?>" style="display:inline-flex;align-items:center;gap:8px;">
                                    <?php echo sanitize($rName); ?>
                                    <?php if($rIsAdmin): ?>
                                    <span class="admin-badge">开发者</span>
                                    <?php endif; ?>
                                </span>
                                <span class="reply-time">· <?php echo timeAgo($r['created_at']); ?></span>
                            </div>
                            <div class="reply-content"><?php echo nl2br(sanitize($r['content'])); ?></div>
                        </div>
                    </div>
                    <?php
                        // Display "shown" divider for 3 items
                        if(!$hasMore && $ri + 1 == $showCount) break;
                    ?>
                    <?php endforeach; ?>
                </div>
                <?php if($hasMore): ?>
                <div style="display:flex;flex-direction:column;gap:10px;margin-top:10px;">
                    <?php foreach(array_slice($sortedReplies, 0, $showCount) as $r):
                        $rUserId = $r['user_id'];
                        $rIsAdmin = $r['is_admin'] || isset($adminUserIds[$rUserId]);
                        $rName = $r['username'] ?? '匿名';
                        $rAvatar = $r['avatar'] ?? '';
                    ?>
                    <div class="reply-item <?php echo $rIsAdmin ? 'admin-reply' : ''; ?>">
                        <div class="user-avatar user-avatar-sm" style="flex-shrink:0;">
                            <?php if($rAvatar): ?><img src="<?php echo sanitize($rAvatar); ?>" alt=""><?php else: echo mb_substr($rName, 0, 1); endif; ?>
                        </div>
                        <div style="flex:1;min-width:0;">
                            <div class="reply-author-info">
                                <span class="reply-author-name" style="display:inline-flex;align-items:center;gap:8px;">
                                    <?php echo sanitize($rName); ?>
                                    <?php if($rIsAdmin): ?>
                                    <span class="admin-badge">开发者</span>
                                    <?php endif; ?>
                                </span>
                                <span class="reply-time">· <?php echo timeAgo($r['created_at']); ?></span>
                            </div>
                            <div class="reply-content"><?php echo nl2br(sanitize($r['content'])); ?></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <?php if(isLoggedIn()): ?>
            <div class="reply-form" id="reply-form-<?php echo $fid; ?>">
                <textarea id="reply-text-<?php echo $fid; ?>" placeholder="写下您的回复..."></textarea>
                <div class="reply-form-actions">
                    <button class="btn btn-secondary btn-sm" onclick="toggleReplyForm(document.querySelector('.feedback-action[onclick*=\"toggleReplyForm(' + <?php echo $fid; ?> + ']'), <?php echo $fid; ?>)">取消</button>
                    <button class="btn btn-primary btn-sm" onclick="submitReply(<?php echo $fid; ?>)">发表回复</button>
                </div>
            </div>
            <?php endif; ?>
        </div>
        <?php endforeach;
        if(!count($feedbacks)): ?>
        <div class="data-card" style="text-align:center;padding:60px 20px;">
            <div style="font-size:60px;opacity:0.3;margin-bottom:16px;">💬</div>
            <div style="font-size:18px;font-weight:700;margin-bottom:8px;">还没有反馈</div>
            <div style="color:var(--text-muted);font-size:14px;">来做第一个反馈的人吧！</div>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
