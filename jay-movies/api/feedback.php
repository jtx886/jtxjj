<?php
require_once __DIR__ . '/../includes/functions.php';
header('Content-Type: application/json');

$db = Database::getInstance();
$action = $_POST['action'] ?? '';

if($action == 'submit') {
    if(!isLoggedIn()) { echo json_encode(['success'=>false,'message'=>'请先登录','need_login'=>true]); exit; }
    $userId = $_SESSION['user_id'];
    $title = trim($_POST['title'] ?? '');
    $content = trim($_POST['content'] ?? '');
    if(!$title || !$content) { echo json_encode(['success'=>false,'message'=>'请填写标题和内容']); exit; }
    $db->insert('feedbacks', ['user_id'=>$userId,'title'=>$title,'content'=>$content]);
    echo json_encode(['success'=>true,'message'=>'反馈提交成功']);
} elseif($action == 'reply') {
    if(!isLoggedIn()) { echo json_encode(['success'=>false,'message'=>'请先登录','need_login'=>true]); exit; }
    $userId = $_SESSION['user_id'];
    $feedbackId = intval($_POST['feedback_id'] ?? 0);
    $content = trim($_POST['content'] ?? '');
    if(!$feedbackId || !$content) { echo json_encode(['success'=>false,'message'=>'参数错误']); exit; }
    $isAdmin = 0;
    // Admin check: for simplicity check session admin OR admin username
    $user = currentUser();
    if($user && $user['username'] == ADMIN_USER) $isAdmin = 1;
    if(isAdmin()) $isAdmin = 1;
    $db->insert('feedback_replies', [
        'feedback_id'=>$feedbackId,'user_id'=>$userId,'content'=>$content,'is_admin'=>$isAdmin
    ]);
    echo json_encode(['success'=>true,'message'=>'回复成功']);
} elseif($action == 'like') {
    if(!isLoggedIn()) { echo json_encode(['success'=>false,'message'=>'请先登录','need_login'=>true]); exit; }
    $userId = $_SESSION['user_id'];
    $feedbackId = intval($_POST['feedback_id'] ?? 0);
    if(!$feedbackId) { echo json_encode(['success'=>false,'message'=>'参数错误']); exit; }
    $exist = $db->fetchOne("SELECT id FROM feedback_likes WHERE user_id = ? AND feedback_id = ?", [$userId, $feedbackId]);
    if($exist) {
        $db->delete('feedback_likes', 'id = ?', [$exist['id']]);
    } else {
        $db->insert('feedback_likes', ['user_id'=>$userId,'feedback_id'=>$feedbackId]);
    }
    $count = $db->fetchOne("SELECT COUNT(*) as c FROM feedback_likes WHERE feedback_id = ?", [$feedbackId])['c'];
    echo json_encode(['success'=>true,'count'=>$count]);
} else {
    echo json_encode(['success'=>false,'message'=>'未知操作']);
}
?>
