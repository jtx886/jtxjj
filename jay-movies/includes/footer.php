</main>

<footer class="footer">
    <div class="container">
        <div class="footer-inner">
            <div class="footer-brand">
                <div class="logo">
                    <div class="logo-icon"></div>
                    <span>Jay影视</span>
                </div>
                <p class="footer-desc">
                    Jay影视是您的在线影视娱乐首选平台，提供海量高清电影、电视剧、动漫和综艺节目，免费在线观看，支持多终端自适应播放。
                </p>
            </div>
            <div>
                <h4 class="footer-col-title">影视分类</h4>
                <ul class="footer-links">
                    <li><a href="category.php?type=movie">热门电影</a></li>
                    <li><a href="category.php?type=tv">热播剧集</a></li>
                    <li><a href="category.php?type=anime">动漫专区</a></li>
                    <li><a href="category.php?type=variety">综艺娱乐</a></li>
                </ul>
            </div>
            <div>
                <h4 class="footer-col-title">帮助中心</h4>
                <ul class="footer-links">
                    <li><a href="feedback.php">问题反馈</a></li>
                    <li><a href="#">关于我们</a></li>
                    <li><a href="#">联系客服</a></li>
                    <li><a href="#">免责声明</a></li>
                </ul>
            </div>
            <div>
                <h4 class="footer-col-title">用户服务</h4>
                <ul class="footer-links">
                    <li><a href="register.php">注册账号</a></li>
                    <li><a href="login.php">登录账号</a></li>
                    <li><a href="profile.php">个人中心</a></li>
                    <li><a href="admin/login.php">管理员入口</a></li>
                </ul>
            </div>
        </div>
        <div class="footer-bottom">
            &copy; <?php echo date('Y'); ?> <?php echo SITE_NAME; ?>. All Rights Reserved. 仅供学习交流使用
        </div>
    </div>
</footer>

<nav class="mobile-nav">
    <div class="mobile-nav-inner">
        <a href="index.php" class="mobile-nav-item <?php echo $activeNav=='index'?'active':''; ?>">
            <span class="icon icon-home"></span>
            <span>首页</span>
        </a>
        <a href="search.php" class="mobile-nav-item <?php echo $activeNav=='search'?'active':''; ?>">
            <span class="icon icon-search"></span>
            <span>搜索</span>
        </a>
        <a href="category.php?type=movie" class="mobile-nav-item <?php echo $activeNav=='movie'?'active':''; ?>">
            <span class="icon icon-movie"></span>
            <span>电影</span>
        </a>
        <a href="category.php?type=tv" class="mobile-nav-item <?php echo $activeNav=='tv'?'active':''; ?>">
            <span class="icon icon-tv"></span>
            <span>剧集</span>
        </a>
        <a href="profile.php" class="mobile-nav-item <?php echo $activeNav=='profile'?'active':''; ?>">
            <span class="icon icon-user"></span>
            <span>我的</span>
        </a>
    </div>
</nav>

<div id="toastContainer" class="toast-container"></div>

<?php
// Announcement popup - only on homepage
if($currentPage == 'index' && isLoggedIn()):
    $db = Database::getInstance();
    $userId = $_SESSION['user_id'];
    $announcement = $db->fetchOne("SELECT * FROM announcements ORDER BY id DESC LIMIT 1");
    if($announcement):
        $dismissed = $db->fetchOne("SELECT id FROM announcement_dismissed WHERE user_id = ? AND announcement_id = ?", [$userId, $announcement['id']]);
        if(!$dismissed):
?>
<div class="modal-overlay active" id="announcementModal">
    <div class="modal">
        <div class="modal-header">
            <div class="modal-title"><span class="icon icon-bell"></span>站点公告</div>
            <button class="modal-close" onclick="dismissAnnouncement(false)"><span class="icon icon-close"></span></button>
        </div>
        <div class="modal-body">
            <h3 style="margin-bottom:12px;font-size:18px;color:var(--primary)"><?php echo sanitize($announcement['title']); ?></h3>
            <div style="color:var(--text-secondary);line-height:1.8;font-size:14px;">
                <?php echo nl2br(sanitize($announcement['content'])); ?>
            </div>
            <div style="margin-top:20px;display:flex;align-items:center;gap:10px;">
                <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-size:14px;color:var(--text-secondary);">
                    <input type="checkbox" id="dontShowAgain" style="width:18px;height:18px;cursor:pointer;">
                    不再提示此公告
                </label>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-primary" onclick="dismissAnnouncement(document.getElementById('dontShowAgain').checked)">我知道了</button>
        </div>
    </div>
</div>
<?php
        endif;
    endif;
endif;
?>

<script src="assets/js/main.js"></script>
<script>
function toggleDropdown(id) {
    var el = document.getElementById(id);
    var isActive = el.classList.contains('active');
    document.querySelectorAll('.dropdown.active').forEach(function(d){d.classList.remove('active');});
    if(!isActive) el.classList.add('active');
}
document.addEventListener('click', function(e) {
    if(!e.target.closest('.dropdown')) {
        document.querySelectorAll('.dropdown.active').forEach(function(d){d.classList.remove('active');});
    }
});
function dismissAnnouncement(dontShow) {
    document.getElementById('announcementModal').classList.remove('active');
    if(dontShow) {
        var xhr = new XMLHttpRequest();
        xhr.open('POST', 'api/announcement.php', true);
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        xhr.send('action=dismiss');
    }
}
function showToast(msg, type) {
    type = type || 'info';
    var c = document.getElementById('toastContainer');
    var t = document.createElement('div');
    t.className = 'toast ' + type;
    t.innerHTML = '<span>' + msg + '</span>';
    c.appendChild(t);
    setTimeout(function(){ t.style.opacity='0'; t.style.transform='translateX(40px)'; setTimeout(function(){t.remove();},300); }, 3000);
}
</script>
</body>
</html>
