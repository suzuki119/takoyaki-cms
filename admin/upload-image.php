<?php
// ===================================================
//  CKEditor 5 用 画像アップロードエンドポイント
//  SimpleUploadAdapter が POST で "upload" フィールドに画像を送ってくる
//  成功時： {"url": "https://..."}
//  失敗時： {"error": {"message": "..."}}
// ===================================================
require_once '../config.php';
require_login();

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => ['message' => 'Method not allowed']]);
    exit;
}

$file = $_FILES['upload'] ?? null;

if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['error' => ['message' => 'アップロードに失敗しました']]);
    exit;
}

$ext     = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
$allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

if (!in_array($ext, $allowed)) {
    echo json_encode(['error' => ['message' => '使用できる形式：jpg / png / gif / webp']]);
    exit;
}

if ($file['size'] > 2 * 1024 * 1024) {
    echo json_encode(['error' => ['message' => '画像サイズは2MB以下にしてください']]);
    exit;
}

$filename = uniqid() . '.' . $ext;

if (!move_uploaded_file($file['tmp_name'], UPLOAD_DIR . $filename)) {
    echo json_encode(['error' => ['message' => '保存に失敗しました']]);
    exit;
}

echo json_encode(['url' => UPLOAD_URL . $filename]);
