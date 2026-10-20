<?php
session_start();
$username = $_SESSION['username'] ?? null;
$topScores = [];
try {
    require_once __DIR__ . '/db.php';
    $scoreQuery = database()->query("SELECT u.username, s.difficulty, s.wpm, s.won FROM scores s JOIN users u ON u.id = s.user_id WHERE s.mode = 'race' ORDER BY s.won DESC, s.wpm DESC, s.accuracy DESC, s.elapsed_ms ASC LIMIT 30");
    $seenPlayers = [];
    foreach ($scoreQuery->fetchAll() as $score) {
        if (isset($seenPlayers[$score['username']])) continue;
        $seenPlayers[$score['username']] = true;
        $topScores[] = $score;
        if (count($topScores) === 3) break;
    }
} catch (Throwable $e) {
    $topScores = [];
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="theme-color" content="#071426"><title>Keydash — Typing, with a little competition</title><link rel="icon" type="image/png" href="favicon.png?v=2">
  <link rel="stylesheet" href="assets/style.css?v=profile-1">
</head>
<body>
  <header class="topbar wrap">
    <a class="brand" href="index.php"><span class="brand-mark">K</span> keydash<span class="brand-dot">.</span></a>
 <nav class="nav-links" aria-label="Main navigation"><a href="#modes">How to play</a><a href="online.php">Online</a><a href="leaderboard.php?mode=online">Leaderboard</a><?php if ($username): ?><a href="profile.php">Profile</a><span class="nav-user">Hi, <?= htmlspecialchars($username, ENT_QUOTES, 'UTF-8') ?></span><a class="button button-small button-quiet" href="logout.php">Log out</a><?php else: ?><a href="auth.php?form=login">Log in</a><a class="button button-small button-outline" href="auth.php?form=register">Create account</a><?php endif; ?></nav>
  </header>
  <main>
    <section class="hero wrap">
      <div class="hero-copy"><div class="eyebrow"><span class="live-dot"></span> YOUR NEXT PERSONAL BEST STARTS HERE</div>
        <h1>Find your<br>typing <span>flow.</span></h1>
        <p class="hero-text">Warm up at your own pace, race a bot, or find a real player online. Ten words, one quick round, and a little more speed every time.</p>
        <div class="hero-actions"><a class="button button-primary" href="#modes">Choose a mode <span aria-hidden="true">↗</span></a><a class="text-link" href="auth.php?form=register">Create a free account <span aria-hidden="true">→</span></a></div>
        <div class="hero-note"><span class="keycap">10</span> words per round <span class="note-divider">·</span> No email needed</div>
      </div>
      <div class="race-card" aria-label="Example typing race"><div class="card-top"><span class="card-label"><span class="live-dot"></span> QUICK RACE</span><span class="round-label">ROUND 01</span></div><div class="race-word">steady<span class="cursor"></span></div><div class="race-hint">TYPE THE WORD TO MOVE</div><div class="race-line"><div class="racer"><span class="avatar avatar-you">Y</span><span class="racer-name">You <b>42 WPM</b></span></div><div class="track"><i class="track-you"></i><i class="track-bot"></i></div><div class="racer"><span class="avatar avatar-bot">B</span><span class="racer-name">Bot <b>Easy</b></span></div></div><div class="card-bottom"><span>10 words</span><span class="versus">YOU <b>VS</b> BOT</span><span>Ready when you are</span></div></div>
      <div class="hero-orb orb-one"></div><div class="hero-orb orb-two"></div>
    </section>
    <section id="modes" class="modes-section"><div class="wrap"><div class="section-heading"><div><div class="eyebrow">PICK YOUR PACE</div><h2>Make every minute count.</h2></div><span class="section-aside">SHORT ROUNDS. REAL PROGRESS.</span></div>
      <div class="mode-grid">
        <article class="mode-card practice-card"><div class="mode-icon">⌨</div><div class="mode-meta">01 / PRACTICE</div><h3>Your pace.<br>Your progress.</h3><p>Build accuracy and find your rhythm with a focused 10-word round.</p><div class="difficulty"><span>CHOOSE LEVEL</span><div class="level-links"><a href="game.php?mode=practice&level=easy">Easy</a><a href="game.php?mode=practice&level=medium">Medium</a><a href="game.php?mode=practice&level=hard">Hard</a></div></div></article>
        <article class="mode-card race-mode-card"><div class="mode-icon mode-icon-blue">↗</div><div class="mode-meta">02 / BOT RACE</div><h3>Can you<br>beat the bot?</h3><p>Race an opponent with a different typing speed at every difficulty.</p><div class="difficulty"><span>CHOOSE BOT</span><div class="level-links"><a href="game.php?mode=race&level=easy">Easy</a><a href="game.php?mode=race&level=medium">Medium</a><a href="game.php?mode=race&level=hard">Hard</a></div></div></article>
        <article class="mode-card online-mode-card"><div class="mode-icon mode-icon-blue">◎</div><div class="mode-meta">03 / ONLINE DUEL</div><h3>Race a<br>real player.</h3><p>Join matchmaking, get the same ten mixed-level words, and race for a win.</p><div class="difficulty online-cta"><span>LIVE MATCHMAKING</span><a class="button button-primary" href="online.php">Find a match <span aria-hidden="true">→</span></a></div></article>
      </div><section class="lobby-preview"><div class="lobby-preview-copy"><div class="eyebrow">THE LOBBY BOARD</div><h3>Little by little, faster and faster.</h3><p>See how your latest race stacks up, then jump back in for another round.</p><a class="text-link" href="leaderboard.php">Open leaderboard <span aria-hidden="true">→</span></a></div><div class="lobby-preview-list"><?php if ($topScores): ?><?php foreach ($topScores as $index => $score): ?><div class="lobby-score"><span class="lobby-rank">0<?= $index + 1 ?></span><span class="lobby-player"><b><?= htmlspecialchars($score['username'], ENT_QUOTES, 'UTF-8') ?></b><small><?= htmlspecialchars(ucfirst($score['difficulty']), ENT_QUOTES, 'UTF-8') ?> bot race<?= $score['won'] ? ' · win' : '' ?></small></span><strong><?= (int)$score['wpm'] ?><small> WPM</small></strong></div><?php endforeach; ?><?php else: ?><div class="lobby-empty"><span class="lobby-empty-mark">K</span><span><b>Your race can be first.</b><small>Sign in and play a bot race to join the board.</small></span><a href="auth.php?form=register">Join ↗</a></div><?php endif; ?></div></section><div class="bottom-strip"><span><b>01</b> Pick a mode</span><i></i><span><b>02</b> Type ten words</span><i></i><span><b>03</b> Check your WPM</span></div>
    </div></section>
  </main>
  <footer class="wrap footer"><a class="brand" href="index.php"><span class="brand-mark">K</span> keydash<span class="brand-dot">.</span></a><span>A small practice round can make a big difference.</span><span>© <?= date('Y') ?> Keydash</span></footer>
<script defer src="assets/site.js?v=profile-1"></script></body></html>
