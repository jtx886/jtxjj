<?php
require_once __DIR__ . '/../includes/functions.php';
header('Content-Type: application/json');

if(!isLoggedIn()) { echo json_encode(['success'=>false,'message'=>'请先登录','need_login'=>true]); exit; }

$db = Database::getInstance();
$userId = $_SESSION['user_id'];
$action = $_POST['action'] ?? '';

if($action == 'add') {
    $mediaId = intval($_POST['media_id'] ?? 0);
    $mediaType = trim($_POST['media_type'] ?? '');
    $title = trim($_POST['title'] ?? '');
    $poster = trim($_POST['poster'] ?? '');
    $season = intval($_POST['season'] ?? 1);
    $episode = intval($_POST['episode'] ?? 1);
    if(!$mediaId || !$mediaType) { echo json_encode(['success'=>false,'message'=>'参数错误']); exit; }
    $exist = $db->fetchOne("SELECT id FROM watch_history WHERE user_id = ? AND media_id = ? AND media_type = ? LIMIT 1", [$userId, $mediaId, $mediaType]);
    if($exist) {
        $db->update('watch_history', [
            'season' => $season, 'episode' => $episode,
            'last_watch' => date('Y-m-d H:i:s')
        ], 'id = ?', [$exist['id']]);
        echo json_encode(['success'=>true,'message'=>'已更新','id'=>$exist['id']]);
    } else {
        $id = $db->insert('watch_history', [
            'user_id' => $userId, 'media_id' => $mediaId, 'media_type' => $mediaType,
            'media_title' => $title, 'media_poster' => $poster,
            'season' => $season, 'episode' => $episode
        ]);
        echo json_encode(['success'=>true,'message'=>'记录成功','id'=>$id]);
    }
} elseif($action == 'update_seconds') {
    $id = intval($_POST['id'] ?? 0);
    if(!$id) { echo json_encode(['success'=>false]); exit; }
    $db->query("UPDATE watch_history SET play_seconds = play_seconds + 10, last_watch = ? WHERE id = ? AND user_id = ?",
        [date('Y-m-d H:i:s'), $id, $userId]);
    echo json_encode(['success'=>true]);
} elseif($action == 'remove') {
    $id = intval($_POST['id'] ?? 0);
    $db->delete('watch_history', 'id = ? AND user_id = ?', [$id, $userId]);
    echo json_encode(['success'=>true,'message'=>'删除成功']);
} elseif($action == 'clear') {
    $db->delete('watch_history', 'user_id = ?', [$userId]);
    echo json_encode(['success'=>true,'message'=>'清空成功']);
} else {
    echo json_encode(['success'=>false,'message'=>'未知操作']);
}
?>
