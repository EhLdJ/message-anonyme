<?php
// src/helpers.php

function json_response($data, $status=200){
    header('Content-Type: application/json');
    http_response_code($status);
    echo json_encode($data);
    exit;
}

function sanitize_text($s){
    // keep newlines, remove suspicious tags
    $s = strip_tags($s);
    $s = trim($s);
    // optionally more cleaning (emoji safe)
    return mb_substr($s, 0, 10000);
}

function hash_ip($ip){
    if(!$ip) return null;
    // use a server secret if you want more privacy
    return hash('sha256', $ip);
}

/**
 * Bad-words simple filter: returns true if message contains banned words.
 * You should replace with a robust list or third-party moderation service.
 */
function contains_bad_words($text){
    $bad = ['putain','merde','con','nique']; // minimal example: extend/replace for production
    $t = mb_strtolower($text);
    foreach($bad as $w){
        if(mb_strpos($t, $w) !== false) return true;
    }
    return false;
}

/**
 * rate-limit: allow at most $limit calls per window seconds per ip
 */
function rate_limit_check_and_record(PDO $pdo, $ip_hash, $endpoint, $limit=5, $window=60){
    $now = date('Y-m-d H:i:00'); // group by minute
    // Try insert
    $sql = "INSERT INTO rate_limits (ip_hash, endpoint, count, window_start)
            VALUES (:ip, :endpoint, 1, :window)
            ON DUPLICATE KEY UPDATE count = count + 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':ip'=>$ip_hash, ':endpoint'=>$endpoint, ':window'=>$now]);
    // fetch count
    $s2 = $pdo->prepare("SELECT count FROM rate_limits WHERE ip_hash=:ip AND endpoint=:endpoint AND window_start=:window");
    $s2->execute([':ip'=>$ip_hash, ':endpoint'=>$endpoint, ':window'=>$now]);
    $row = $s2->fetch(PDO::FETCH_ASSOC);
    $count = $row ? intval($row['count']) : 0;
    return $count <= $limit;
}
