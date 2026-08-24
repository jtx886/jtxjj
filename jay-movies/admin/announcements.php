<?php
$pageTitle = '公告管理 / 邮件 / 主题';
$activeMenu = 'announcements';
require_once __DIR__ . '/header.php';

$db = Database::getInstance();
$msg = '';

// Handle actions
if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    if($action == 'add_announcement') {
        $title = trim($_POST['title'] ?? '');
        $content = trim($_POST['content'] ?? '');
        if($title && $content) {
            $db->insert('announcements', ['title'=>$title,'content'=>$content]);
            $msg = '公告发布成功！';
        } else { $msg = '请填写完整信息'; }
    } elseif($action == 'delete_announcement') {
        $id = intval($_POST['id'] ?? 0);
        if($id) { $db->delete('announcements','id=?',[$id]); $msg='公告已删除'; }
    } elseif($action == 'save_theme') {
        $color = trim($_POST['theme_color'] ?? '#8b5cf6');
        if(preg_match('/^#[0-9a-fA-F]{6}$/', $color)) {
            setSetting('theme_primary', $color);
            setSetting('theme_color', $color);
            function adjustC($h, $p){$h=str_replace('#','',$h);if(strlen($h)!=6)$h='8b5cf6';$r=max(0,min(255,hexdec(substr($h,0,2))+($p*2.55)));$g=max(0,min(255,hexdec(substr($h,2,2))+($p*2.55)));$b=max(0,min(255,hexdec(substr($h,4,2))+($p*2.55)));return '#'.sprintf('%02x%02x%02x',$r,$g,$b);}
            setSetting('theme_secondary', adjustC($color, 15));
            $msg = '主题颜色已更新！';
            echo '<script>location.reload();</script>';
        }
    } elseif($action == 'send_email_all') {
        $subject = trim($_POST['email_subject'] ?? '');
        $content = $_POST['email_content'] ?? '';
        if($subject && $content) {
            $users = $db->fetchAll("SELECT email FROM users");
            $ok = 0; $fail = 0;
            foreach($users as $u) {
                $html = '
                <div style="max-width:560px;margin:0 auto;background:#fff;border-radius:16px;overflow:hidden;box-shadow:0 10px 40px rgba(0,0,0,0.1);">
                    <div style="background:linear-gradient(135deg,#8b5cf6,#7c3aed);padding:30px;text-align:center;color:#fff;">
                        <div style="font-size:22px;font-weight:800;">Jay影视 · 官方邮件</div>
                        <div style="margin-top:6px;font-size:14px;opacity:0.9;">致所有用户</div>
                    </div>
                    <div style="padding:35px 30px;line-height:1.9;color:#333;font-size:15px;">' . $content . '</div>
                    <div style="padding:18px 30px;background:#fafafa;border-top:1px solid #eee;font-size:12px;color:#aaa;text-align:center;">© '.date('Y').' Jay影视 · 本邮件由系统发送</div>
                </div>';
                if(@sendEmail($u['email'], $subject, $html)) $ok++; else $fail++;
            }
            $msg = "邮件发送完成：成功 $ok 封，失败 $fail 封";
        } else { $msg = '请填写标题和内容'; }
    }
}

$announcements = $db->fetchAll("SELECT * FROM announcements ORDER BY id DESC");
$theme = getThemeColor();
$tab = $_GET['tab'] ?? 'announcements';
?>

<?php if($msg): ?><div class="alert alert-success"><?php echo $msg; ?></div><?php endif; ?>

<div class="page-header">
    <div>
        <h1 class="page-title">公告 · 邮件 · 主题设置</h1>
        <div class="page-desc">发布站点公告、群发用户邮件、自定义网站主题颜色</div>
    </div>
</div>

<div class="tabs-wrap">
    <div class="tabs-nav">
        <div class="tab-item <?php echo $tab=='announcements'?'active':''; ?>" onclick="switchTab(this,'announcements')"><span class="icon icon-bell" style="width:16px;height:16px;"></span> 公告管理</div>
        <div class="tab-item <?php echo $tab=='email'?'active':''; ?>" onclick="switchTab(this,'email')"><span class="icon icon-edit" style="width:16px;height:16px;"></span> 群发邮件</div>
        <div class="tab-item <?php echo $tab=='theme'?'active':''; ?>" onclick="switchTab(this,'theme')"><span class="icon icon-settings" style="width:16px;height:16px;"></span> 主题颜色</div>
    </div>

    <div class="tab-content <?php echo $tab=='announcements'?'active':''; ?>" id="tab-announcements">
        <div class="grid-2" style="margin-bottom:24px;">
            <div class="data-card">
                <div class="data-card-header"><div class="data-card-title">发布新公告</div></div>
                <div class="data-card-body" style="padding:24px;">
                    <form method="POST">
                        <input type="hidden" name="action" value="add_announcement">
                        <div class="form-group">
                            <label class="form-label">公告标题</label>
                            <input type="text" name="title" class="form-control" placeholder="例如：网站维护通知" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">公告内容</label>
                            <textarea name="content" class="form-control" rows="6" placeholder="公告详细内容，支持换行" required></textarea>
                            <div class="form-hint">公告将以弹窗形式显示在首页，用户可勾选"不再提示"</div>
                        </div>
                        <button type="submit" class="btn btn-primary"><span class="icon icon-plus"></span>发布公告</button>
                    </form>
                </div>
            </div>
            <div class="data-card">
                <div class="data-card-header"><div class="data-card-title">📌 使用说明</div></div>
                <div class="data-card-body" style="padding:24px;color:var(--text-secondary);line-height:1.9;font-size:14px;">
                    <p><strong>显示规则：</strong></p>
                    <p>• 每次发布新公告，所有用户进入首页时会弹窗显示</p>
                    <p>• 用户可勾选"不再提示此公告"，之后不再显示这条</p>
                    <p>• 若又发布新公告，则下一次仍会弹窗显示新的</p>
                    <p>• 公告<strong>只在首页显示</strong>，其他页面不受影响</p>
                    <p style="margin-top:12px;color:var(--warning);">💡 提示：删除或修改历史公告不影响用户的"不再提示"状态。发布新公告即可覆盖旧的。</p>
                </div>
            </div>
        </div>
        <div class="data-card">
            <div class="data-card-header"><div class="data-card-title">历史公告（共 <?php echo count($announcements); ?> 条）</div></div>
            <div class="data-card-body" style="padding:0;">
                <table class="data-table">
                    <thead><tr><th style="width:80px;">ID</th><th>标题</th><th>内容</th><th style="width:160px;">发布时间</th><th style="width:100px;">操作</th></tr></thead>
                    <tbody>
                        <?php foreach($announcements as $a): ?>
                        <tr>
                            <td><?php echo $a['id']; ?></td>
                            <td style="font-weight:600;color:var(--primary);"><?php echo sanitize($a['title']); ?></td>
                            <td style="color:var(--text-secondary);font-size:13px;line-height:1.7;max-width:500px;"><?php echo nl2br(sanitize(mb_substr($a['content'],0,120))); ?><?php echo mb_strlen($a['content'])>120?'...':''; ?></td>
                            <td style="font-size:13px;color:var(--text-muted);"><?php echo timeAgo($a['created_at']); ?></td>
                            <td>
                                <form method="POST" onsubmit="return confirm('确定删除吗？')">
                                    <input type="hidden" name="action" value="delete_announcement">
                                    <input type="hidden" name="id" value="<?php echo $a['id']; ?>">
                                    <button type="submit" class="icon-btn" title="删除" style="color:var(--danger);"><span class="icon icon-trash"></span></button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach;
                        if(!count($announcements)): ?>
                        <tr><td colspan="5" style="text-align:center;padding:50px;color:var(--text-muted);">暂无公告</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="tab-content <?php echo $tab=='email'?'active':''; ?>" id="tab-email">
        <div class="data-card">
            <div class="data-card-header"><div class="data-card-title">📧 给所有用户发送通知邮件</div></div>
            <div class="data-card-body" style="padding:24px;max-width:800px;">
                <form method="POST" onsubmit="return confirm('确定发送给所有注册用户吗？（共 <?php echo $totalUsers; ?> 位）');">
                    <input type="hidden" name="action" value="send_email_all">
                    <div class="form-group">
                        <label class="form-label">收件人数</label>
                        <input type="text" class="form-control" value="共 <?php echo $totalUsers; ?> 位注册用户" disabled>
                    </div>
                    <div class="form-group">
                        <label class="form-label">邮件标题</label>
                        <input type="text" name="email_subject" class="form-control" placeholder="例如：Jay影视 重要通知" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">邮件内容（支持 HTML）</label>
                        <textarea name="email_content" class="form-control" rows="10" placeholder="请输入邮件正文内容，支持 HTML 标签" required></textarea>
                        <div class="form-hint">邮件模板已自动套入 Jay影视 精美版式，直接填写内容即可</div>
                    </div>
                    <button type="submit" class="btn btn-primary"><span class="icon icon-bell"></span>立即群发</button>
                </form>
            </div>
        </div>
    </div>

    <div class="tab-content <?php echo $tab=='theme'?'active':''; ?>" id="tab-theme">
        <div class="grid-2">
            <div class="data-card">
                <div class="data-card-header"><div class="data-card-title">🎨 主题颜色设置</div></div>
                <div class="data-card-body" style="padding:24px;">
                    <form method="POST">
                        <input type="hidden" name="action" value="save_theme">
                        <div class="form-group">
                            <label class="form-label">选择主题主色调</label>
                            <div class="input-with-color">
                                <input type="color" name="theme_color" class="color-picker" value="<?php echo $theme; ?>">
                                <input type="text" class="form-control" id="colorCode" value="<?php echo $theme; ?>" onchange="document.querySelector('[name=theme_color]').value=this.value;">
                            </div>
                            <div class="form-hint">选择任意颜色，全站按钮、链接、标识都会同步更新</div>
                        </div>
                        <div class="form-group">
                            <label class="form-label">推荐颜色</label>
                            <div style="display:flex;gap:12px;flex-wrap:wrap;">
                                <?php
                                $presets = ['#8b5cf6'=>'紫色','#ec4899'=>'粉红','#ef4444'=>'红色','#f59e0b'=>'橙色','#eab308'=>'黄色','#10b981'=>'绿色','#06b6d4'=>'青色','#3b82f6'=>'蓝色','#6366f1'=>'靛蓝','#0f172a'=>'深色','#1f2937'=>'灰色'];
                                foreach($presets as $c => $n): ?>
                                <div onclick="document.querySelector('[name=theme_color]').value='<?php echo $c; ?>';document.getElementById('colorCode').value='<?php echo $c; ?>';" title="<?php echo $n; ?>" style="width:44px;height:44px;background:<?php echo $c; ?>;border-radius:12px;cursor:pointer;border:3px solid <?php echo $theme==$c?'#fff':'transparent'; ?>;box-shadow:0 4px 12px rgba(0,0,0,0.2);transition:all 0.2s;" onmouseenter="this.style.transform='scale(1.1)'" onmouseleave="this.style.transform='scale(1)'"></div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary" style="background:<?php echo $theme; ?>;"><span class="icon icon-edit"></span>应用主题</button>
                    </form>
                </div>
            </div>
            <div class="data-card">
                <div class="data-card-header"><div class="data-card-title">👀 预览效果</div></div>
                <div class="data-card-body" style="padding:24px;">
                    <div style="display:flex;gap:16px;flex-wrap:wrap;">
                        <button class="btn btn-primary" style="background:<?php echo $theme; ?>;"><span class="icon icon-play"></span>主按钮</button>
                        <button class="btn btn-outline" style="border-color:<?php echo $theme; ?>;color:<?php echo $theme; ?>;">边框按钮</button>
                        <div style="padding:10px 14px;background:<?php echo $theme; ?>15;color:<?php echo $theme; ?>;border-radius:10px;font-weight:600;border:1px solid <?php echo $theme; ?>33;">标签样式</div>
                    </div>
                    <div style="margin-top:24px;padding:20px;background:var(--bg-card);border-radius:14px;border:1px solid var(--border);">
                        <div style="height:6px;background:<?php echo $theme; ?>;border-radius:4px;width:80%;margin-bottom:14px;"></div>
                        <div style="height:4px;background:var(--bg-hover);border-radius:4px;width:100%;margin-bottom:8px;"></div>
                        <div style="height:4px;background:var(--bg-hover);border-radius:4px;width:60%;"></div>
                    </div>
                    <div style="margin-top:24px;display:flex;gap:16px;flex-wrap:wrap;">
                        <div class="logo-icon" style="width:50px;height:50px;background:linear-gradient(135deg, <?php echo $theme; ?>, <?php echo adjustC2($theme,-20); ?>);border-radius:14px;position:relative;"></div>
                        <div style="width:40px;height:40px;border-radius:50%;background:linear-gradient(135deg, <?php echo $theme; ?>, <?php echo adjustC2($theme,-20); ?>);display:flex;align-items:center;justify-content:center;font-weight:700;color:#fff;">杰</div>
                        <div style="padding:6px 12px;background:linear-gradient(135deg,#ef4444,#dc2626);color:#fff;border-radius:6px;font-size:11px;font-weight:700;box-shadow:0 2px 8px rgba(239,68,68,0.3);">开发者</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<script>
function adjustC2(hex, percent) {
    hex = hex.replace('#','');
    if(hex.length != 6) hex = '8b5cf6';
    var r = Math.max(0, Math.min(255, parseInt(hex.substring(0,2),16) + percent*2.55));
    var g = Math.max(0, Math.min(255, parseInt(hex.substring(2,4),16) + percent*2.55));
    var b = Math.max(0, Math.min(255, parseInt(hex.substring(4,6),16) + percent*2.55));
    return '#' + [r,g,b].map(v=>Math.round(v).toString(16).padStart(2,'0')).join('');
}
</script>
<?php
function adjustC2($hex, $p){$hex=str_replace('#','',$hex);if(strlen($hex)!=6)$hex='8b5cf6';$r=max(0,min(255,hexdec(substr($hex,0,2))+$p*2.55));$g=max(0,min(255,hexdec(substr($hex,2,2))+$p*2.55));$b=max(0,min(255,hexdec(substr($hex,4,2))+$p*2.55));return '#'.sprintf('%02x%02x%02x',$r,$g,$b);}
require_once __DIR__ . '/footer.php'; ?>
