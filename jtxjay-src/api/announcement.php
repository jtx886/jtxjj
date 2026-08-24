<?php
require_once __DIR__ . '/../includes/functions.php';
header('Content-Type: application/json');

if(!isLoggedIn()) { echo json_encode(['success'=>false,'message'=>'请先登录','need_login'=>true]); exit; }

$db = Database::getInstance();
$userId = $_SESSION['user_id'];
$action = $_POST['action'] ?? '';

if($action == 'dismiss') {
    $last = $db->fetchOne("SELECT id FROM announcements ORDER BY id DESC LIMIT 1");
    if($last) {
        $exist = $db->fetchOne("SELECT id FROM announcement_dismissed WHERE user_id = ? AND announcement_id = ?", [$userId, $last['id']]);
        if(!$exist) {
            $db->insert('announcement_dismissed', ['user_id'=>$userId, 'announcement_id'=>$last['id']]);
        }
    }
    echo json_encode(['success'=>true]);
} else {
    echo json_encode(['success'=>false]);
}
?>
