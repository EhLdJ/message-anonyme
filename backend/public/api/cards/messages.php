<?php
// public/api/cards/messages.php
require_once __DIR__ . '/../../src/config.php';
$config = require __DIR__ . '/../../src/config.php';
$pdo = require __DIR__ . '/../../src/db.php';
require_once __DIR__ . '/../../src/helpers.php';
require_once __DIR__ . '/../../src/image_gen.php';

// Allow CORS for dev (adjust in prod)
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type");
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204); exit;
}

// parse slug from URL
// expected URI: /api/cards/{slug}/messages
$uri = $_SERVER['REQUEST_URI'];
$scriptName = dirname($_SERVER['SCRIPT_NAME']); // /api/cards
$path = substr($uri, strlen($scriptName));
$parts = array_values(array_filter(explode('/', $path)));
if(count($parts) < 2){
    json_response(['error'=>'missing slug'], 400);
}
$slug = $parts[0]; // first part should be slug

// Only POST supported
if($_SERVER['REQUEST_METHOD'] !== 'POST'){
    json_response(['error'=>'method_not_allowed'], 405);
}

// read JSON body
$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
if(!is_array($data)){
    json_response(['error'=>'invalid_json'], 400);
}

$message_text = isset($data['message_text']) ? sanitize_text($data['message_text']) : '';
$pseudo = isset($data['pseudo']) ? trim($data['pseudo']) : null;
$palette_raw = isset($data['palette']) ? $data['palette'] : null;

// basic validations
if(empty($message_text)){
    json_response(['error'=>'empty_message'], 400);
}
if(mb_strlen($message_text) > intval($config['max_message_length'])){
    json_response(['error'=>'message_too_long'], 400);
}

// recaptcha check (optional)
if($config['enable_recaptcha']){
    $token = $data['recaptcha_token'] ?? null;
    if(!$token) json_response(['error'=>'recaptcha_missing'], 400);
    // verify with Google
    $resp = file_get_contents("https://www.google.com/recaptcha/api/siteverify?secret={$config['recaptcha_secret']}&response={$token}");
    $j = json_decode($resp, true);
    if(!$j || empty($j['success']) || $j['score'] < 0.3){
        json_response(['error'=>'recaptcha_failed'], 400);
    }
}

// rate-limit by IP
$ip = $_SERVER['REMOTE_ADDR'] ?? null;
$ip_hash = hash_ip($ip);
$ok = rate_limit_check_and_record($pdo, $ip_hash, 'send_message', 6, 60);
if(!$ok){
    json_response(['error'=>'rate_limited'], 429);
}

// bad words
if(contains_bad_words($message_text)){
    json_response(['error'=>'message_contains_prohibited_words'], 400);
}

// find card
$stmt = $pdo->prepare("SELECT id, allow_anonymous FROM cards WHERE slug = :slug LIMIT 1");
$stmt->execute([':slug'=>$slug]);
$card = $stmt->fetch();
if(!$card){
    json_response(['error'=>'card_not_found'], 404);
}
if(!$card['allow_anonymous']){
    json_response(['error'=>'card_not_accept_anonymous'], 403);
}

// create unique filename
$uploadDir = rtrim($config['upload_dir'], '/');
if(!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
$filename = time() . '_' . bin2hex(random_bytes(6)) . '.png';
$outPath = rtrim($uploadDir, '/') . '/' . $filename;
$palette = [];

// parse palette: accept either JSON string or array with bg/text
if(is_string($palette_raw)){
    $tmp = json_decode($palette_raw, true);
    if(is_array($tmp)) $palette = $tmp;
} elseif (is_array($palette_raw)) {
    $palette = $palette_raw;
}

// generate image
$okGen = generate_message_image($message_text, $palette, $outPath);
if(!$okGen){
    json_response(['error'=>'image_generation_failed'], 500);
}

// store message in DB
$insert = $pdo->prepare("INSERT INTO anonymous_messages (card_id, pseudo, message_text, image_path, ip_hash) VALUES (:card_id, :pseudo, :message_text, :image_path, :ip_hash)");
$insert->execute([
    ':card_id' => $card['id'],
    ':pseudo' => $pseudo,
    ':message_text' => $message_text,
    ':image_path' => $filename, // store filename only; build URL when serving
    ':ip_hash' => $ip_hash
]);
$messageId = $pdo->lastInsertId();

// Optionally: push notification (not implemented) or email
// Return JSON
$imageUrl = rtrim($config['base_url'], '/') . '/uploads/messages/' . $filename;
json_response([
    'success' => true,
    'message_id' => (int)$messageId,
    'image_url' => $imageUrl,
    'note' => 'Le message a été reçu. L\'image est accessible via image_url'
], 201);
