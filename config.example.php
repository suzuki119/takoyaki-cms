<?php
// ===================================================
//  Takoyaki CMS v2.0.0 設定ファイル（テンプレート）
//
//  このファイルを config.php にコピーして使ってください。
//    cp config.example.php config.php
//
//  config.php は .gitignore で除外されます。
//
//  ここには「環境ごとに変わる値」だけを置いています。
//  DB接続や記事取得などの共通ヘルパーは functions.php にあり、
//  このファイルの末尾で読み込んでいます。
//  各ページからは今まで通り config.php を読み込めばOKです。
// ===================================================

// --- DB接続情報 ---
define('DB_HOST', 'localhost');
define('DB_NAME', 'your_database_name');
define('DB_USER', 'your_db_user');
define('DB_PASS', 'your_db_password');
define('DB_CHARSET', 'utf8mb4');

// --- サイト設定 ---
// このCMSを設置したURL（末尾スラッシュなし）
define('SITE_URL', 'http://localhost:8888/takoyaki-cms');
define('UPLOAD_DIR', __DIR__ . '/uploads/');
define('UPLOAD_URL', SITE_URL . '/uploads/');

// --- 画像アップロード設定 ---
define('MAX_UPLOAD_SIZE',   5 * 1024 * 1024); // 5MB
define('IMAGE_MAX_WIDTH',   1600);            // 元画像の最大幅（超えるとリサイズ）
define('IMAGE_THUMB_WIDTH', 300);             // サムネイル変種の幅

// --- タイムゾーン ---
// PHP と MySQL の時刻をそろえる。予約公開の判定がずれるのを防ぐため必須。
define('CMS_TIMEZONE', 'Asia/Tokyo');
date_default_timezone_set(CMS_TIMEZONE);

// --- スキルのカテゴリ（skill.php の表示順もこの順序になる） ---
const SKILL_CATEGORIES = ['プログラミング', 'デザイン', 'その他'];


// ===================================================
//  共通ヘルパーの読み込み
//  （DB接続・記事取得などの関数は functions.php 側にあります）
// ===================================================
require_once __DIR__ . '/functions.php';
