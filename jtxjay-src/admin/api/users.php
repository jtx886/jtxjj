<?php
require_once __DIR__ . '/../../includes/functions.php';
header('Content-Type: application/json');

if(!isAdmin()) { echo json_encode(['success'=>false,'message'=>'未授权']); exit; }

$db = Database::getInstance();
$action = $_POST['action'] ?? '';

if($action == 'ban') {
    $userId = intval($_POST['user_id'] ?? 0);
    $reason = trim($_POST['reason'] ?? '违反社区规则');
    $days = intval($_POST['days'] ?? 7);
    if(!$userId) { echo json_encode(['success'=>false,'message'=>'参数错误']); exit; }
    $start = date('Y-m-d H:i:s');
    $end = $days == 0 ? null : date('Y-m-d H:i:s', time() + $days * 86400);
    $endDisplay = $days == 0 ? '永久' : date('Y-m-d H:i:s', strtotime($end));
    $db->update('users', [
        'banned' => 1, 'ban_reason' => $reason, 'ban_start' => $start, 'ban_end' => $end
    ], 'id = ?', [$userId]);

    // Send email to user
    $user = $db->fetchOne("SELECT * FROM users WHERE id = ?", [$userId]);
    if($user) {
        $subject = '【Jay影视】账号封禁通知';
        $body = '
        <div style="max-width:520px;margin:0 auto;background:#fff;border-radius:16px;overflow:hidden;box-shadow:0 10px 40px rgba(0,0,0,0.1);">
            <div style="background:linear-gradient(135deg,#ef4444,#dc2626);padding:40px 30px;text-align:center;color:#fff;">
                <div style="display:inline-flex;align-items:center;gap:10px;font-size:24px;font-weight:800;">
                    🚫 账号封禁通知
                </div>
            </div>
            <div style="padding:40px 30px;">
                <div style="font-size:16px;color:#333;line-height:1.9;">
                    <p>尊敬的 <strong>' . sanitize($user['username']) . '</strong>：</p>
                    <p>很抱歉地通知您，根据 Jay影视 社区规则，您的账号已被封禁。</p>
                    <div style="background:#fef2f2;border:1px solid #fecaca;border-radius:10px;padding:20px;margin:20px 0;">
                        <div style="margin-bottom:10px;"><strong>封禁原因：</strong>' . sanitize($reason) . '</div>
                        <div style="margin-bottom:10px;"><strong>封禁开始：</strong>' . $start . '</div>
                        <div><strong>解除时间：</strong>' . $endDisplay . '</div>
                    </div>
                    <p style="color:#888;font-size:13px;">如有异议，请通过站内反馈渠道联系管理员。</p>
                </div>
            </div>
            <div style="padding:20px 30px;background:#fafafa;border-top:1px solid #eee;font-size:12px;color:#aaa;text-align:center;">
                © ' . date('Y') . ' Jay影视
            </div>
        </div>';
        @sendEmail($user['email'], $subject, $body);
    }
    echo json_encode(['success'=>true,'message'=>'封禁成功，已通知用户']);
} elseif($action == 'unban') {
    $userId = intval($_POST['user_id'] ?? 0);
    if(!$userId) { echo json_encode(['success'=>false,'message'=>'参数错误']); exit; }
    $db->update('users', ['banned' => 0, 'ban_reason' => '', 'ban_start' => null, 'ban_end' => null], 'id = ?', [$userId]);
    echo json_encode(['success'=>true,'message'=>'解封成功']);
} elseif($action == 'send_mail') {
    $userId = intval($_POST['user_id'] ?? 0);
    $subject = trim($_POST['subject'] ?? '');
    $content = $_POST['content'] ?? '';
    if(!$userId || !$subject || !$content) { echo json_encode(['success'=>false,'message'=>'参数不完整']); exit; }
    $user = $db->fetchOne("SELECT * FROM users WHERE id = ?", [$userId]);
    if(!$user) { echo json_encode(['success'=>false,'message'=>'用户不存在']); exit; }
    $html = '
    <div style="max-width:560px;margin:0 auto;background:#fff;border-radius:16px;overflow:hidden;box-shadow:0 10px 40px rgba(0,0,0,0.1);">
        <div style="background:linear-gradient(135deg,#8b5cf6,#7c3aed);padding:30px;text-align:center;color:#fff;">
            <div style="font-size:22px;font-weight:800;">Jay影视 · 官方通知</div>
        </div>
        <div style="padding:35px 30px;line-height:1.9;color:#333;">' . $content . '</div>
        <div style="padding:18px 30px;background:#fafafa;border-top:1px solid #eee;font-size:12px;color:#aaa;text-align:center;">© ' . date('Y') . ' Jay影视</div>
    </div>';
    $sent = @sendEmail($user['email'], $subject, $html);
    echo json_encode(['success'=>$sent, 'message'=>$sent?'邮件发送成功':'邮件发送失败']);
} else {
    echo json_encode(['success'=>false,'message'=>'未知操作']);
}
?>
