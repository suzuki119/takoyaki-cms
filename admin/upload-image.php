<?php
// ===================================================
//  CKEditor 5 用 画像アップロードエンドポイント
//  SimpleUploadAdapter が POST の "upload" フィールドに画像を送ってくる。
//    成功時: {"url": "https://..."}
//    失敗時: {"error": {"message": "..."}}
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

if (!is_array($file)) {
    echo json_encode(['error' => ['message' => 'ファイルが送信されていません']]);
    exit;
}

// 検証・リサイズ・保存は config.php に集約してある
// （本文内画像は一覧に出さないので -thumb 変種は作らない）
$result = handle_image_upload($file, false);

if ($result['error'] !== null) {
    echo json_encode(['error' => ['message' => $result['error']]], JSON_UNESCAPED_UNICODE);
    exit;
}

$upload_url = defined('UPLOAD_URL') ? UPLOAD_URL : $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST'] . '/uploads/';
echo json_encode(['url' => $upload_url . $result['filename']]);
