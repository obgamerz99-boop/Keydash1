<?php
session_start();
require_once __DIR__ . '/db.php';
$form = ($_GET['form'] ?? $_POST['form'] ?? 'login') === 'register' ? 'register' : 'login';
$error = '';
if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form = ($_POST['form'] ?? '') === 'register' ? 'register' : 'login';
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    if (!hash_equals($_SESSION['csrf'], $_POST['csrf'] ?? '')) $error = 'Your session expired. Please try again.';
    elseif (!preg_match('/^[A-Za-z0-9_]{3,24}$/', $username)) $error = 'Username must be 3–24 characters using letters, numbers, or underscores.';
    elseif (strlen($password) < 8) $error = 'Password must be at least 8 characters.';
    else {
        try {
            $pdo = database();
            if ($form === 'register') {
                $stmt = $pdo->prepare('INSERT INTO users (username, password_hash) VALUES (?, ?)');
                $stmt->execute([$username, password_hash($password, PASSWORD_DEFAULT)]);
                session_regenerate_id(true);
                $_SESSION['username'] = $username;
                $_SESSION['user_id'] = (int)$pdo->lastInsertId();
                header('Location: index.php'); exit;
            }
            $stmt = $pdo->prepare('SELECT username, password_hash FROM users WHERE username = ?');
            $stmt->execute([$username]);
            $account = $stmt->fetch();
            if ($account && password_verify($password, $account['password_hash'])) {
                session_regenerate_id(true);
                $_SESSION['username'] = $account['username'];
                $idStmt = $pdo->prepare('SELECT id FROM users WHERE username = ?');
                $idStmt->execute([$account['username']]);
                $_SESSION['user_id'] = (int)$idStmt->fetchColumn();
                header('Location: index.php'); exit;
            }
            $error = 'That username and password did not match.';
        } catch (PDOException $e) {
            $error = $form === 'register' && $e->getCode() === '23000' ? 'That username is already taken.' : 'Could not connect to the database. Check the setup guide and try again.';
        }
    }
}
$title = $form === 'register' ? 'Create your account' : 'Welcome back';
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="theme-color" content="#071426"><title><?= htmlspecialchars($title) ?> — Keydash</title><link rel="icon" type="image/png" href="favicon.png?v=2"><link rel="stylesheet" href="assets/style.css"></head><body><header class="topbar wrap"><a class="brand" href="index.php"><span class="brand-mark">K</span> keydash<span class="brand-dot">.</span></a><nav class="nav-links"><a href="index.php">← Home</a></nav></header><main class="auth-shell"><section class="auth-card"><div class="eyebrow"><span class="live-dot"></span> YOUR PERSONAL BEST AWAITS</div><h1><?= htmlspecialchars($title) ?></h1><p><?= $form === 'register' ? 'Save your spot and come back for another round.' : 'Log in and get back to your next best score.' ?></p><?php if ($error): ?><div class="auth-error" role="alert"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?><form method="post" action="auth.php"><input type="hidden" name="form" value="<?= $form ?>"><input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf'], ENT_QUOTES, 'UTF-8') ?>"><label for="username">Username</label><input id="username" name="username" required minlength="3" maxlength="24" pattern="[A-Za-z0-9_]+" autocomplete="username" value="<?= htmlspecialchars($_POST['username'] ?? '', ENT_QUOTES, 'UTF-8') ?>" placeholder="Your username"><label for="password">Password</label><input id="password" name="password" type="password" required minlength="8" autocomplete="<?= $form === 'register' ? 'new-password' : 'current-password' ?>" placeholder="At least 8 characters"><button class="button button-primary" type="submit"><?= $form === 'register' ? 'Create account' : 'Log in' ?> <span aria-hidden="true">→</span></button></form><div class="auth-switch"><?= $form === 'register' ? 'Already have an account? <a href="auth.php?form=login">Log in</a>' : 'New here? <a href="auth.php?form=register">Create an account</a>' ?></div></section><div class="auth-foot">Username and password only. No email required.</div></main><footer class="wrap footer"><a class="brand" href="index.php"><span class="brand-mark">K</span> keydash<span class="brand-dot">.</span></a><span>© <?= date('Y') ?> Keydash</span></footer></body></html>
