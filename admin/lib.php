<?php
// Shared helpers for the website and the admin panel. Prints nothing.
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

// Turns what you type into digits with country code.
// 0581554433 -> 971581554433 (UAE). Other countries: type +92 312 1234567
function norm_phone($raw){
    $raw = trim((string)$raw);
    $plus = (strpos($raw, '+') === 0);
    $d = preg_replace('/\D+/', '', $raw);
    if ($plus) return $d;
    if (strpos($d, '00') === 0) return substr($d, 2);
    if (strpos($d, '0') === 0) return '971' . substr($d, 1);
    return $d;
}
function show_phone($digits){
    if (preg_match('/^971(\d{9})$/', (string)$digits, $m)) {
        $n = '0' . $m[1];
        return substr($n, 0, 3) . ' ' . substr($n, 3, 3) . ' ' . substr($n, 6);
    }
    return '+' . $digits;
}
function settings_defaults(){
    return ['phone'=>'971581554433','whatsapp'=>'971581554433','head_code'=>'','body_start'=>'','body_end'=>''];
}
function load_settings(){
    $f = __DIR__ . '/data.php';
    $d = is_file($f) ? include $f : [];
    return array_merge(settings_defaults(), is_array($d) ? $d : []);
}
function write_php_array($file, $arr){
    $code = "<?php\nreturn " . var_export($arr, true) . ";\n";
    $ok = file_put_contents($file, $code, LOCK_EX) !== false;
    if (function_exists('opcache_invalidate')) @opcache_invalidate($file, true);
    return $ok;
}
function save_settings($s){
    $f = __DIR__ . '/data.php';
    if (is_file($f)) @copy($f, __DIR__ . '/data.prev.php');
    return write_php_array($f, $s);
}
