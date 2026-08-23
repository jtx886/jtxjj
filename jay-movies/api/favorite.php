<?php
require_once __DIR__ . '/../includes/functions.php';
header('Content-Type: application/json');

if(!isLoggedIn()) { echo json_encode(['success'=>false,'message'=>'请先登录','need_login'=>true]); exit; }

$db = Database::getInstance();
$userId = $_SESSION['user_id'];
$action = $_POST['action'] ?? $_GET['action'] ?? '';

if($action == 'add') {
    $mediaId = intval($_POST['media_id'] ?? 0);
    $mediaType = trim($_POST['media_type'] ?? '');
    $title = trim($_POST['title'] ?? '');
    $poster = trim($_POST['poster'] ?? '');
    $year = trim($_POST['year'] ?? '');
    if(!$mediaId || !$mediaType) { echo json_encode(['success'=>false,'message'=>'参数错误']); exit; }
    $exist = $db->fetchOne("SELECT id FROM favorites WHERE user_id = ? AND media_id = ? AND media_type = ?", [$userId, $mediaId, $mediaType]);
    if($exist) { echo json_encode(['success'=>false,'message'=>'已收藏过了']); exit; }
    $db->insert('favorites', [
        'user_id' => $userId, 'media_id' => $mediaId, 'media_type' => $mediaType,
        'media_title' => $title, 'media_poster' => $poster, 'media_year' => $year
    ]);
    echo json_encode(['success'=>true,'message'=>'收藏成功']);
} elseif($action == 'remove') {
    $id = intval($_POST['id'] ?? 0);
    $mediaId = intval($_POST['media_id'] ?? 0);
    $mediaType = trim($_POST['media_type'] ?? '');
    if($id) {
        $db->delete('favorites', 'id = ? AND user_id = ?', [$id, $userId]);
    } elseif($mediaId && $mediaType) {
        $db->delete('favorites', 'media_id = ? AND media_type = ? AND user_id = ?', [$mediaId, $mediaType, $userId]);
    } else {
        echo json_encode(['success'=>false,'message'=>'参数错误']); exit;
    }
    echo json_encode(['success'=>true,'message'=>'已取消收藏']);
} elseif($action == 'list') {
    $list = $db->fetchAll("SELECT * FROM favorites WHERE user_id = ? ORDER BY id DESC", [$userId]);
    echo json_encode(['success'=>true,'data'=>$list]);
} else {
    echo json_encode(['success'=>false,'message'=>'未知操作']);
}
?>
