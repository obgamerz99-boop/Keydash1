<?php
session_start();
if (empty($_SESSION['username'])) { header('Location: auth.php?form=login'); exit; }
if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
require_once __DIR__ . '/db.php';
$pdo = database();
$find = $pdo->prepare('SELECT id, username, avatar, created_at FROM users WHERE username = ?');
$find->execute([$_SESSION['username']]);
$user = $find->fetch();
if (!$user) { $_SESSION = []; header('Location: auth.php?form=login'); exit; }
$avatars = ['⌨️', '🌙', '⚡', '🐱', '🦊', '🐼', '🐸', '🐙', '🦄', '🚀', '🎧', '🌊'];
$message = '';
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf'], $_POST['csrf'] ?? '')) $error = 'Your session expired. Refresh and try again.';
    else {
        $newName = trim($_POST['username'] ?? '');
        $avatar = $_POST['avatar'] ?? '⌨️';
        if (!preg_match('/^[A-Za-z0-9_]{3,24}$/', $newName)) $error = 'Username must be 3–24 characters using letters, numbers, or underscores.';
        elseif (!in_array($avatar, $avatars, true)) $error = 'Choose one of the available profile pictures.';
        else {
            try {
                $save = $pdo->prepare('UPDATE users SET username = ?, avatar = ? WHERE id = ?');
                $save->execute([$newName, $avatar, $user['id']]);
                $_SESSION['username'] = $newName;
                $user['username'] = $newName; $user['avatar'] = $avatar;
                $message = 'Your profile is saved.';
            } catch (PDOException $e) { $error = $e->getCode() === '23000' ? 'That username is already taken.' : 'Could not save your profile. Please try again.'; }
        }
    }
}
$summary = $pdo->prepare("SELECT COUNT(*) AS played, COALESCE(MAX(wpm), 0) AS best_wpm, COALESCE(AVG(wpm), 0) AS average_wpm, COALESCE(SUM(CASE WHEN mode = 'race' AND won = 1 THEN 1 ELSE 0 END), 0) AS bot_wins, COALESCE(SUM(CASE WHEN mode = 'race' AND won = 0 THEN 1 ELSE 0 END), 0) AS bot_losses FROM scores WHERE user_id = ?");
$summary->execute([$user['id']]); $career = $summary->fetch();
$onlineQuery = $pdo->prepare('SELECT wins, losses, games_played FROM online_stats WHERE user_id = ?');
$onlineQuery->execute([$user['id']]); $online = $onlineQuery->fetch() ?: ['wins' => 0, 'losses' => 0, 'games_played' => 0];
function esc($value): string { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="theme-color" content="#071426"><title>Your profile — Keydash</title><link rel="icon" type="image/png" href="favicon.png?v=2"><link rel="stylesheet" href="assets/style.css?v=profile-1"></head><body class="game-page"><header class="topbar wrap"><a class="brand" href="index.php"><span class="brand-mark">K</span> keydash<span class="brand-dot">.</span></a><nav class="nav-links"><a href="leaderboard.php">Leaderboard</a><a href="online.php">Online</a><a href="index.php">Lobby</a><a class="button button-small button-quiet" href="logout.php">Log out</a></nav></header><main class="profile-shell"><div class="play-heading"><div class="eyebrow"><span class="live-dot"></span> YOUR KEYDASH CARD</div><h1>Player profile.</h1><p>Pick a look, choose your username, and track your best rounds.</p></div><div class="profile-layout"><section class="profile-card"><div class="profile-card-top"><div class="profile-big-avatar"><?= esc($user['avatar']) ?></div><div><span class="stat-label">PLAYER</span><h2><?= esc($user['username']) ?></h2><span class="profile-subtitle">Joined <?= date('M Y', strtotime($user['created_at'] ?? 'now')) ?></span></div></div><?php if ($error): ?><div class="auth-error" role="alert"><?= esc($error) ?></div><?php elseif ($message): ?><div class="profile-success" role="status"><?= esc($message) ?></div><?php endif; ?><form method="post" class="profile-form"><input type="hidden" name="csrf" value="<?= esc($_SESSION['csrf']) ?>"><label for="username">Username</label><input id="username" name="username" required minlength="3" maxlength="24" pattern="[A-Za-z0-9_]+" value="<?= esc($user['username']) ?>"><fieldset><legend>Choose your profile picture</legend><div class="avatar-picker"><?php foreach ($avatars as $choice): ?><label class="avatar-choice"><input type="radio" name="avatar" value="<?= esc($choice) ?>" <?= $user['avatar'] === $choice ? 'checked' : '' ?>><span><?= esc($choice) ?></span></label><?php endforeach; ?></div></fieldset><button class="button button-primary" type="submit">Save profile <span>→</span></button></form></section><section class="profile-record"><div class="eyebrow">YOUR RACE RECORD</div><h2>Every round counts.</h2><div class="profile-stat-grid"><div><span>BEST WPM</span><b><?= (int)$career['best_wpm'] ?></b></div><div><span>AVG WPM</span><b><?= (int)round((float)$career['average_wpm']) ?></b></div><div><span>ROUNDS PLAYED</span><b><?= (int)$career['played'] ?></b></div><div><span>BOT WINS</span><b><?= (int)$career['bot_wins'] ?></b></div><div><span>BOT LOSSES</span><b><?= (int)$career['bot_losses'] ?></b></div><div><span>ONLINE W/L</span><b><?= (int)$online['wins'] ?>/<?= (int)$online['losses'] ?></b></div><div><span>ONLINE PLAYED</span><b><?= (int)$online['games_played'] ?></b></div></div><a class="button button-quiet" href="leaderboard.php">View leaderboard <span>→</span></a></section></div></main><footer class="wrap footer"><a class="brand" href="index.php"><span class="brand-mark">K</span> keydash<span class="brand-dot">.</span></a><span>Your next best score is one round away.</span></footer><script defer src="assets/site.js?v=profile-1"></script></body></html>
