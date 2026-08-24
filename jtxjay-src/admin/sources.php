<?php
$pageTitle = '播放源管理';
$activeMenu = 'sources';
require_once __DIR__ . '/header.php';

$db = Database::getInstance();
$msg = '';

if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    if($action == 'add') {
        $name = trim($_POST['name'] ?? '');
        $url = trim($_POST['url'] ?? '');
        $isDef = intval($_POST['is_default'] ?? 0);
        if(!$name || !$url) { $msg = '名称和地址不能为空'; }
        else {
            if($isDef) { $db->query("UPDATE play_sources SET is_default = 0"); }
            $db->insert('play_sources', ['name'=>$name,'url'=>$url,'is_default'=>$isDef]);
            $msg = '添加成功';
        }
    } elseif($action == 'edit') {
        $id = intval($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $url = trim($_POST['url'] ?? '');
        $isDef = intval($_POST['is_default'] ?? 0);
        if(!$id || !$name || !$url) { $msg = '参数错误'; }
        else {
            if($isDef) { $db->query("UPDATE play_sources SET is_default = 0"); }
            $db->update('play_sources', ['name'=>$name,'url'=>$url,'is_default'=>$isDef], 'id = ?', [$id]);
            $msg = '修改成功';
        }
    } elseif($action == 'delete') {
        $id = intval($_POST['id'] ?? 0);
        if($id) { $db->delete('play_sources', 'id = ?', [$id]); $msg = '删除成功'; }
    } elseif($action == 'set_default') {
        $id = intval($_POST['id'] ?? 0);
        if($id) {
            $db->query("UPDATE play_sources SET is_default = 0");
            $db->update('play_sources', ['is_default' => 1], 'id = ?', [$id]);
            $msg = '已设为默认';
        }
    }
}
$sources = $db->fetchAll("SELECT * FROM play_sources ORDER BY is_default DESC, id ASC");
$editId = intval($_GET['edit'] ?? 0);
$editSource = $editId ? $db->fetchOne("SELECT * FROM play_sources WHERE id = ?", [$editId]) : null;
?>

<?php if($msg): ?>
<div class="alert alert-success"><?php echo $msg; ?></div>
<?php endif; ?>

<div class="page-header">
    <div>
        <h1 class="page-title">播放源管理</h1>
        <div class="page-desc">管理影视播放源，支持添加、修改和删除。当前共 <?php echo count($sources); ?> 个播放源</div>
    </div>
</div>

<div class="grid-2" style="margin-bottom:30px;">
    <div class="data-card">
        <div class="data-card-header">
            <div class="data-card-title"><?php echo $editSource ? '编辑播放源' : '新增播放源'; ?></div>
        </div>
        <div class="data-card-body" style="padding:24px;">
            <form method="POST">
                <input type="hidden" name="action" value="<?php echo $editSource ? 'edit' : 'add'; ?>">
                <?php if($editSource): ?><input type="hidden" name="id" value="<?php echo $editSource['id']; ?>"><?php endif; ?>
                <div class="form-group">
                    <label class="form-label">播放源名称</label>
                    <input type="text" name="name" class="form-control" placeholder="如：YYZY资源" required value="<?php echo sanitize($editSource ? $editSource['name'] : ''); ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">播放源地址（API URL）</label>
                    <input type="url" name="url" class="form-control" placeholder="如：https://api.yyzy-tv.vip/inc/apijson.php" required value="<?php echo sanitize($editSource ? $editSource['url'] : ''); ?>">
                </div>
                <div class="form-group" style="display:flex;align-items:center;gap:10px;">
                    <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
                        <input type="checkbox" name="is_default" value="1" <?php echo ($editSource && $editSource['is_default']) ? 'checked' : ''; ?>>
                        设为默认播放源
                    </label>
                </div>
                <div style="display:flex;gap:10px;">
                    <button type="submit" class="btn btn-primary"><span class="icon icon-plus"></span><?php echo $editSource ? '保存修改' : '添加播放源'; ?></button>
                    <?php if($editSource): ?><a href="sources.php" class="btn btn-secondary">取消</a><?php endif; ?>
                </div>
            </form>
        </div>
    </div>
    <div class="data-card">
        <div class="data-card-header">
            <div class="data-card-title">📘 使用说明</div>
        </div>
        <div class="data-card-body" style="padding:24px;">
            <div style="color:var(--text-secondary);line-height:1.9;font-size:14px;">
                <p><strong>1. 播放源格式：</strong>需要支持 <code>?ac=detail&wd=关键词</code> 返回标准 JSON 结构的资源站 API。</p>
                <p><strong>2. 默认解析器：</strong>系统使用 <code>svip.ffzyplay.com</code> 作为解析播放器，兼容多种类型的视频链接。</p>
                <p><strong>3. 多播放源：</strong>用户播放时可在多个源之间切换，建议至少保持 1-2 个稳定源。</p>
                <p style="margin-top:14px;padding:14px;background:rgba(139,92,246,0.08);border-radius:10px;border:1px solid rgba(139,92,246,0.2);">
                    <strong>💡 示例源：</strong>https://api.yyzy-tv.vip/inc/apijson.php
                </p>
            </div>
        </div>
    </div>
</div>

<div class="data-card">
    <div class="data-card-body" style="padding:0;">
        <table class="data-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>名称</th>
                    <th>地址</th>
                    <th>状态</th>
                    <th>添加时间</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($sources as $s): ?>
                <tr>
                    <td><?php echo $s['id']; ?></td>
                    <td style="font-weight:600;"><?php echo sanitize($s['name']); ?></td>
                    <td style="color:var(--text-secondary);font-size:12px;max-width:300px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;" title="<?php echo sanitize($s['url']); ?>"><?php echo sanitize($s['url']); ?></td>
                    <td>
                        <?php if($s['is_default']): ?><span class="badge badge-success">默认</span><?php else: ?><span class="badge badge-info">备用</span><?php endif; ?>
                    </td>
                    <td style="color:var(--text-muted);font-size:13px;"><?php echo $s['created_at']; ?></td>
                    <td>
                        <div class="actions-cell">
                            <a href="?edit=<?php echo $s['id']; ?>" class="icon-btn" title="编辑" style="color:var(--primary);"><span class="icon icon-edit"></span></a>
                            <?php if(!$s['is_default']): ?>
                            <form method="POST" style="display:inline;margin:0;padding:0;">
                                <input type="hidden" name="action" value="set_default">
                                <input type="hidden" name="id" value="<?php echo $s['id']; ?>">
                                <button type="submit" class="icon-btn" title="设为默认" onclick="return confirm('确定设为默认吗？')" style="color:var(--success);"><span class="icon icon-star"></span></button>
                            </form>
                            <form method="POST" style="display:inline;margin:0;padding:0;">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?php echo $s['id']; ?>">
                                <button type="submit" class="icon-btn" title="删除" onclick="return confirm('确定删除吗？')" style="color:var(--danger);"><span class="icon icon-trash"></span></button>
                            </form>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
