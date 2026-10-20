<?php
declare(strict_types=1);
require __DIR__ . '/lib.php';

$secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
$base = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/') . '/';
session_name('frs_admin');
session_set_cookie_params(['lifetime'=>0,'path'=>$base,'secure'=>$secure,'httponly'=>true,'samesite'=>'Lax']);
session_start();
header('X-Frame-Options: DENY');
header('X-Robots-Tag: noindex, nofollow');
header('Cache-Control: no-store');
header('Referrer-Policy: same-origin');

$cfgFile = __DIR__ . '/config.php';
$cfg = include $cfgFile;
if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
$msg = ''; $err = '';

function csrf_ok(){ return isset($_POST['csrf']) && hash_equals($_SESSION['csrf'], (string)$_POST['csrf']); }
$stateDir = __DIR__ . '/state'; if (!is_dir($stateDir)) @mkdir($stateDir, 0755, true);
$af = $stateDir . '/attempts.json';
$ip = $_SERVER['REMOTE_ADDR'] ?? '0';
function att_load($af){ $a = is_file($af) ? json_decode((string)file_get_contents($af), true) : []; return is_array($a) ? $a : []; }
function att_save($af, $a){ @file_put_contents($af, json_encode($a), LOCK_EX); }

$logged = !empty($_SESSION['ok']);
$S = load_settings();
$act = $_POST['act'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_ok()) {
        $err = 'Your session expired. Please try again.';
    } elseif ($act === 'login') {
        $a = att_load($af); $r = $a[$ip] ?? ['c'=>0,'u'=>0];
        if (($r['u'] ?? 0) > time()) {
            $err = 'Too many failed attempts. Try again in ' . ceil(($r['u'] - time()) / 60) . ' minutes.';
        } else {
            $u = hash_equals((string)$cfg['user'], trim((string)($_POST['user'] ?? '')));
            $p = password_verify((string)($_POST['pass'] ?? ''), (string)$cfg['hash']);
            if ($u && $p) {
                session_regenerate_id(true);
                $_SESSION['ok'] = 1; $_SESSION['csrf'] = bin2hex(random_bytes(16));
                unset($a[$ip]); att_save($af, $a);
                header('Location: ' . $base); exit;
            }
            $r['c'] = ($r['c'] ?? 0) + 1;
            if ($r['c'] >= 5) { $r['u'] = time() + 900; $r['c'] = 0; }
            $a[$ip] = $r; att_save($af, $a);
            usleep(600000);
            $err = 'Incorrect username or password.';
        }
    } elseif ($logged) {
        $must = !empty($cfg['must_change']);
        if ($act === 'logout') {
            $_SESSION = []; session_destroy(); header('Location: ' . $base); exit;
        } elseif ($act === 'pass') {
            $cur = (string)($_POST['cur'] ?? ''); $n1 = (string)($_POST['n1'] ?? ''); $n2 = (string)($_POST['n2'] ?? '');
            if (!password_verify($cur, (string)$cfg['hash'])) $err = 'Current password is not correct.';
            elseif (strlen($n1) < 10) $err = 'New password must be at least 10 characters.';
            elseif ($n1 !== $n2) $err = 'New passwords do not match.';
            else {
                $cfg['hash'] = password_hash($n1, PASSWORD_DEFAULT); $cfg['must_change'] = false;
                $msg = write_php_array($cfgFile, $cfg) ? 'Password changed.' : 'Could not save. Check folder permissions.';
            }
        } elseif ($must) {
            $err = 'Please change the temporary password first.';
        } elseif ($act === 'contact') {
            $ph = norm_phone($_POST['phone'] ?? ''); $wa = norm_phone($_POST['whatsapp'] ?? '');
            if ($wa === '') $wa = $ph;
            if (!preg_match('/^\d{9,15}$/', $ph) || !preg_match('/^\d{9,15}$/', $wa)) {
                $err = 'Please enter valid numbers. UAE: 058 155 4433. Other countries: +92 312 1234567.';
            } else {
                $S['phone'] = $ph; $S['whatsapp'] = $wa;
                $msg = save_settings($S) ? 'Contact details saved. The website is updated.' : 'Could not save. Check folder permissions.';
            }
        } elseif ($act === 'head') {
            $S['head_code'] = str_replace("\0", '', (string)($_POST['head_code'] ?? ''));
            $msg = save_settings($S) ? 'Head code saved.' : 'Could not save. Check folder permissions.';
        } elseif ($act === 'body') {
            $S['body_start'] = str_replace("\0", '', (string)($_POST['body_start'] ?? ''));
            $S['body_end'] = str_replace("\0", '', (string)($_POST['body_end'] ?? ''));
            $msg = save_settings($S) ? 'Body code saved.' : 'Could not save. Check folder permissions.';
        }
    }
    $S = load_settings(); $cfg = include $cfgFile; $logged = !empty($_SESSION['ok']);
}
$tab = $_GET['tab'] ?? 'contact';
if (!in_array($tab, ['contact','head','body','account'], true)) $tab = 'contact';
if (!empty($cfg['must_change'])) $tab = 'account';
$T = $_SESSION['csrf'];
?><!DOCTYPE html>
<html lang="en"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>Website Admin</title>
<style>
*{box-sizing:border-box}body{margin:0;font:16px/1.55 system-ui,-apple-system,"Segoe UI",Arial,sans-serif;background:#f4f5f7;color:#222}
.top{background:#14181f;color:#fff;padding:14px 20px;display:flex;justify-content:space-between;align-items:center;gap:12px}
.top b{font-size:18px}.top a,.top button{color:#fff;background:none;border:1px solid #444c58;border-radius:6px;padding:8px 14px;cursor:pointer;font:inherit;text-decoration:none}
.wrap{max-width:760px;margin:0 auto;padding:20px}
.tabs{display:flex;flex-wrap:wrap;gap:8px;margin-bottom:18px}
.tabs a{padding:10px 16px;border-radius:6px;background:#fff;border:1px solid #dde1e6;text-decoration:none;color:#222;font-weight:600}
.tabs a.on{background:#f7931e;border-color:#f7931e;color:#14181f}
.card{background:#fff;border:1px solid #dde1e6;border-radius:8px;padding:22px}
h1,h2{margin:0 0 6px;font-size:22px}p.h{color:#5b6573;margin:0 0 18px;font-size:15px}
label{display:block;font-weight:700;margin:16px 0 6px}
input,textarea{width:100%;border:1.5px solid #cfd5dc;border-radius:6px;padding:12px;font:inherit;background:#fff}
textarea{font-family:ui-monospace,Consolas,monospace;font-size:14px;min-height:200px}
.btn{margin-top:18px;background:#f7931e;color:#14181f;border:0;border-radius:6px;padding:13px 26px;font:700 16px system-ui;cursor:pointer}
.ok{background:#e6f6ec;border:1px solid #9bd3ae;color:#14532d;padding:12px 14px;border-radius:6px;margin-bottom:16px}
.er{background:#fdeaea;border:1px solid #f0a9a9;color:#7f1d1d;padding:12px 14px;border-radius:6px;margin-bottom:16px}
.warn{background:#fff4e0;border:1px solid #f3cf8e;padding:12px 14px;border-radius:6px;margin-bottom:16px}
.now{background:#f4f5f7;border-radius:6px;padding:12px 14px;font-size:15px;margin-top:18px}
.login{max-width:380px;margin:12vh auto;padding:0 20px}
small{color:#5b6573}
</style></head><body>
<?php if (!$logged): ?>
<div class="login"><div class="card">
<h1>Website Admin</h1><p class="h">Fast Recovery Services</p>
<?php if ($err) echo '<div class="er">'.h($err).'</div>'; ?>
<form method="post" autocomplete="off">
<input type="hidden" name="act" value="login"><input type="hidden" name="csrf" value="<?= h($T) ?>">
<label>Username</label><input name="user" required autofocus autocomplete="username">
<label>Password</label><input name="pass" type="password" required autocomplete="current-password">
<button class="btn" type="submit">Log in</button>
</form></div></div>
<?php else: ?>
<div class="top"><b>Website Admin</b><span><a href="../" target="_blank" rel="noopener">View website</a>
<form method="post" style="display:inline"><input type="hidden" name="act" value="logout"><input type="hidden" name="csrf" value="<?= h($T) ?>"><button type="submit">Log out</button></form></span></div>
<div class="wrap">
<?php if (!empty($cfg['must_change'])): ?><div class="warn"><b>First login:</b> please set your own password below before using the other options.</div><?php endif; ?>
<div class="tabs">
<?php foreach (['contact'=>'Contact details','head'=>'Head code','body'=>'Body code','account'=>'Password'] as $k=>$v): ?>
<a class="<?= $tab===$k?'on':'' ?>" href="?tab=<?= $k ?>"><?= $v ?></a>
<?php endforeach; ?>
</div>
<?php if ($msg) echo '<div class="ok">'.h($msg).'</div>'; if ($err) echo '<div class="er">'.h($err).'</div>'; ?>
<div class="card">
<?php if ($tab === 'contact'): ?>
<h2>Contact details</h2><p class="h">Changes every phone number, call button and WhatsApp link on the website.</p>
<form method="post"><input type="hidden" name="act" value="contact"><input type="hidden" name="csrf" value="<?= h($T) ?>">
<label>Phone number (call buttons)</label><input name="phone" value="<?= h(show_phone($S['phone'])) ?>" required>
<label>WhatsApp number</label><input name="whatsapp" value="<?= h(show_phone($S['whatsapp'])) ?>" required>
<small>UAE numbers: type like 058 155 4433. Other countries: start with + and the country code, for example +92 312 1234567.</small><br>
<button class="btn" type="submit">Save contact details</button></form>
<div class="now"><b>Live now:</b> shows <?= h(show_phone($S['phone'])) ?> | call link tel:+<?= h($S['phone']) ?> | WhatsApp wa.me/<?= h($S['whatsapp']) ?></div>
<?php elseif ($tab === 'head'): ?>
<h2>Head code</h2><p class="h">Added just before &lt;/head&gt; on the website. Use it for Google Tag Manager, Google Analytics, Google Ads tags or Search Console verification tags.</p>
<form method="post"><input type="hidden" name="act" value="head"><input type="hidden" name="csrf" value="<?= h($T) ?>">
<textarea name="head_code" spellcheck="false" placeholder="Paste your code here"><?= h($S['head_code']) ?></textarea>
<button class="btn" type="submit">Save head code</button></form>
<?php elseif ($tab === 'body'): ?>
<h2>Body code</h2><p class="h">Paste only code you trust. It runs on every visitor's browser.</p>
<form method="post"><input type="hidden" name="act" value="body"><input type="hidden" name="csrf" value="<?= h($T) ?>">
<label>Right after &lt;body&gt; opens</label><small>Example: the Google Tag Manager noscript snippet.</small>
<textarea name="body_start" spellcheck="false"><?= h($S['body_start']) ?></textarea>
<label>Just before &lt;/body&gt; closes</label><small>Example: chat widgets or tracking scripts that should load last.</small>
<textarea name="body_end" spellcheck="false"><?= h($S['body_end']) ?></textarea>
<button class="btn" type="submit">Save body code</button></form>
<?php else: ?>
<h2>Change password</h2><p class="h">Use at least 10 characters.</p>
<form method="post" autocomplete="off"><input type="hidden" name="act" value="pass"><input type="hidden" name="csrf" value="<?= h($T) ?>">
<label>Current password</label><input name="cur" type="password" required autocomplete="current-password">
<label>New password</label><input name="n1" type="password" required minlength="10" autocomplete="new-password">
<label>Repeat new password</label><input name="n2" type="password" required minlength="10" autocomplete="new-password">
<button class="btn" type="submit">Change password</button></form>
<?php endif; ?>
</div></div>
<?php endif; ?></body></html>
