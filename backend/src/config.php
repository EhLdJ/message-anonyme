<?php
// src/config.php
// Very simple .env loader (no composer required). Ensure .env exists.
$env = __DIR__ . '/../.env';
if(!file_exists($env)){
    throw new Exception('.env file missing');
}
$vars = file($env, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
foreach($vars as $line){
    if(strpos(trim($line), '#')===0) continue;
    [$k,$v] = array_map('trim', explode('=', $line, 2) + [null, null]);
    if($k) putenv("$k=$v");
}

return [
    'db_host' => getenv('DB_HOST') ?: '127.0.0.1',
    'db_name' => getenv('DB_NAME') ?: 'message_anonyme',
    'db_user' => getenv('DB_USER') ?: 'root',
    'db_pass' => getenv('DB_PASS') ?: '',
    'base_url' => getenv('BASE_URL') ?: 'http://localhost:8080',
    'upload_dir' => getenv('UPLOAD_DIR') ?: __DIR__ . '/../uploads/messages',
    'max_message_length' => intval(getenv('MAX_MESSAGE_LENGTH') ?: 2000),
    'enable_recaptcha' => strtolower(getenv('ENABLE_RECAPTCHA') ?: 'false') === 'true',
    'recaptcha_secret' => getenv('RECAPTCHA_SECRET') ?: '',
];
