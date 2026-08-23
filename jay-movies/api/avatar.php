<?php
require_once __DIR__ . '/../includes/functions.php';
header('Content-Type: application/json');

if(!isLoggedIn()) { echo json_encode(['success'=>false,'message'=>'请先登录','need_login'=>true]); exit; }

$db = Database::getInstance();
$userId = $_SESSION['user_id'];

if(!isset($_FILES['avatar']) || $_FILES['avatar']['error']) {
    echo json_encode(['success'=>false,'message'=>'请选择图片']); exit;
}

$file = $_FILES['avatar'];
$size = $file['size'];
if($size > 2 * 1024 * 1024) { echo json_encode(['success'=>false,'message'=>'图片不能大于2MB']); exit; }

$info = @getimagesize($file['tmp_name']);
if(!$info) { echo json_encode(['success'=>false,'message'=>'无效图片']); exit; }

$extMap = [1=>'gif',2=>'jpg',3=>'png'];
$ext = isset($extMap[$info[2]]) ? $extMap[$info[2]] : 'png';
if($ext == 'jpg' && $info['mime'] == 'image/jpeg') $ext = 'jpg';
elseif($ext == 'png' && $info['mime'] == 'image/png') $ext = 'png';
elseif($ext == 'gif' && $info['mime'] == 'image/gif') $ext = 'gif';

$uploadDir = __DIR__ . '/../uploads/avatars/';
if(!is_dir($uploadDir)) @mkdir($uploadDir, 0755, true);

$fileName = 'avatar_' . $userId . '_' . time() . '.' . $ext;
$savePath = $uploadDir . $fileName;
if(!move_uploaded_file($file['tmp_name'], $savePath)) {
    echo json_encode(['success'=>false,'message'=>'保存失败']); exit;
}

// Delete old
$old = $db->fetchOne("SELECT avatar FROM users WHERE id = ?", [$userId]);
if($old && $old['avatar']) {
    $oldPath = __DIR__ . '/../' . ltrim(str_replace(SITE_URL, '', $old['avatar']), '/');
    if(file_exists($oldPath) && strpos($oldPath, 'avatars/') !== false) @unlink($oldPath);
}

// Compress/save using GD
list($w, $h, $type) = $info;
$dstW = 256; $dstH = 256;
$dst = imagecreatetruecolor($dstW, $dstH);
$src = null;
if($type == 1) $src = imagecreatefromgif($savePath);
elseif($type == 2) $src = imagecreatefromjpeg($savePath);
else $src = imagecreatefrompng($savePath);
if($src) {
    imagealphablending($dst, false); imagesavealpha($dst, true);
    if($type == 3) { imagefill($dst, 0, 0, imagecolorallocatealpha($dst, 0, 0, 0, 127)); }
    imagecopyresampled($dst, $src, 0, 0, 0, 0, $dstW, $dstH, $w, $h);
    if($type == 1) imagegif($dst, $savePath);
    elseif($type == 2) imagejpeg($dst, $savePath, 90);
    else imagepng($dst, $savePath, 8);
    imagedestroy($src); imagedestroy($dst);
}

$url = SITE_URL . 'uploads/avatars/' . $fileName;
$db->update('users', ['avatar' => $url], 'id = ?', [$userId]);
echo json_encode(['success'=>true,'url'=>$url,'message'=>'上传成功']);
?>
