<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
$userId = (int)($_SESSION['user_id'] ?? 0);
$username = $_SESSION['username'] ?? '';
$csrf = $_SESSION['csrf'] ?? '';
session_write_close();

function reply(array $data, int $status = 200) {
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}
function onlineWords(): array {
    $banks = [
        'easy' => ['sunny','little','green','happy','water','bright','smile','garden','warm','dream','apple','quiet','music','river','cloud','gentle','paper','fresh','table','kind'],
        'medium' => ['steady','journey','between','curious','morning','capture','another','balance','picture','forward','breathe','thought','perfect','natural','freedom','whisper','explore','without','purpose','careful'],
        'hard' => ['rhythm','awkward','zephyr','subtle','sphinx','vibrant','jigsaw','complex','oxygen','zealous','cryptic','knapsack','juxtapose','wavelength','quizzical','mischievous','phenomenon','labyrinth','exquisite','unfamiliar'],
    ];
    $result = [];
    foreach (['easy' => 4, 'medium' => 3, 'hard' => 3] as $level => $count) {
        shuffle($banks[$level]);
        foreach (array_slice($banks[$level], 0, $count) as $word) $result[] = ['word' => $word, 'level' => $level];
    }
    shuffle($result);
    return $result;
}
function playerStats(PDO $pdo, int $id): array {
    $stmt = $pdo->prepare('SELECT wins, losses, games_played FROM online_stats WHERE user_id = ?');
    $stmt->execute([$id]);
    $stats = $stmt->fetch();
    return $stats ?: ['wins' => 0, 'losses' => 0, 'games_played' => 0];
}
function waitEstimate(PDO $pdo): int {
    $value = $pdo->query('SELECT AVG(wait_seconds) FROM (SELECT (player1_wait_seconds + player2_wait_seconds) / 2 AS wait_seconds FROM online_matches WHERE completed_at IS NOT NULL ORDER BY id DESC LIMIT 50) recent')->fetchColumn();
    return $value === null ? 30 : max(8, min(90, (int)round((float)$value)));
}
function activeMatch(PDO $pdo, int $id): ?array {
    $stmt = $pdo->prepare("SELECT * FROM online_matches WHERE status = 'playing' AND (player1_id = ? OR player2_id = ?) ORDER BY id DESC LIMIT 1");
    $stmt->execute([$id, $id]);
    $row = $stmt->fetch();
    return $row ?: null;
}
function recentFinishedMatch(PDO $pdo, int $id): ?array {
    $stmt = $pdo->prepare("SELECT * FROM online_matches WHERE status = 'completed' AND completed_at > DATE_SUB(CURRENT_TIMESTAMP, INTERVAL 10 MINUTE) AND (player1_id = ? OR player2_id = ?) ORDER BY completed_at DESC LIMIT 1");
    $stmt->execute([$id, $id]);
    $row = $stmt->fetch();
    return $row ?: null;
}
function matchPayload(PDO $pdo, array $match, int $id): array {
    $first = (int)$match['player1_id'] === $id;
    $opponentId = $first ? (int)$match['player2_id'] : (int)$match['player1_id'];
    $nameStmt = $pdo->prepare('SELECT username, avatar FROM users WHERE id = ?');
    $nameStmt->execute([$opponentId]);
    $opponent = $nameStmt->fetch() ?: ['username' => 'Opponent', 'avatar' => '⌨️'];
    $winner = $match['winner_id'] === null ? null : (int)$match['winner_id'];
    return [
        'status' => $match['status'], 'match_id' => (int)$match['id'],
        'opponent' => $opponent['username'], 'opponent_avatar' => $opponent['avatar'],
        'words' => json_decode($match['words_json'], true) ?: [],
        'my_words' => (int)$match[$first ? 'player1_words' : 'player2_words'],
        'opponent_words' => (int)$match[$first ? 'player2_words' : 'player1_words'],
        'my_wpm' => (int)$match[$first ? 'player1_wpm' : 'player2_wpm'],
        'opponent_wpm' => (int)$match[$first ? 'player2_wpm' : 'player1_wpm'],
        'winner_id' => $winner, 'i_won' => $winner === null ? null : $winner === $id,
        'my_stats' => playerStats($pdo, $id), 'opponent_stats' => playerStats($pdo, $opponentId),
    ];
}
function applyMatchResult(PDO $pdo, array $match, int $winnerId): void {
    $pdo->prepare("UPDATE online_matches SET status = 'completed', winner_id = ?, completed_at = CURRENT_TIMESTAMP WHERE id = ? AND status = 'playing'")->execute([$winnerId, $match['id']]);
    $loserId = (int)$match['player1_id'] === $winnerId ? (int)$match['player2_id'] : (int)$match['player1_id'];
    $winStmt = $pdo->prepare('INSERT INTO online_stats (user_id, wins, losses, games_played) VALUES (?, 1, 0, 1) ON DUPLICATE KEY UPDATE wins = wins + 1, games_played = games_played + 1');
    $lossStmt = $pdo->prepare('INSERT INTO online_stats (user_id, wins, losses, games_played) VALUES (?, 0, 1, 1) ON DUPLICATE KEY UPDATE losses = losses + 1, games_played = games_played + 1');
    $winStmt->execute([$winnerId]); $lossStmt->execute([$loserId]);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || (!$userId && $username === '') || !hash_equals($csrf, $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')) reply(['error' => 'Please log in again before playing online.'], 403);
$request = json_decode(file_get_contents('php://input'), true) ?: [];
$action = $request['action'] ?? '';
try {
    require_once __DIR__ . '/db.php';
    $pdo = database();
    if (!$userId && $username !== '') {
        $lookup = $pdo->prepare('SELECT id FROM users WHERE username = ?');
        $lookup->execute([$username]);
        $userId = (int)$lookup->fetchColumn();
    }
    if (!$userId) reply(['error' => 'Account not found. Please log in again.'], 403);
    if ($action === 'stats') reply(['stats' => playerStats($pdo, $userId)]);
    if ($action === 'join') {
        $lock = (int)$pdo->query("SELECT GET_LOCK('keydash_matchmaking', 5)")->fetchColumn();
        if ($lock !== 1) reply(['error' => 'Matchmaking is busy. Please try again.'], 503);
        try {
            $pdo->beginTransaction();
            $pdo->exec('DELETE FROM online_queue WHERE joined_at < DATE_SUB(CURRENT_TIMESTAMP, INTERVAL 3 MINUTE)');
            $existing = activeMatch($pdo, $userId);
            if ($existing) { $pdo->commit(); reply(matchPayload($pdo, $existing, $userId)); }
            $find = $pdo->prepare('SELECT user_id, joined_at FROM online_queue WHERE user_id <> ? ORDER BY joined_at ASC LIMIT 1 FOR UPDATE');
            $find->execute([$userId]);
            $queued = $find->fetch();
            if ($queued) {
                $opponentId = (int)$queued['user_id'];
                $waitSeconds = max(0, time() - strtotime($queued['joined_at']));
                $pdo->prepare('DELETE FROM online_queue WHERE user_id IN (?, ?)')->execute([$opponentId, $userId]);
                $words = json_encode(onlineWords(), JSON_UNESCAPED_UNICODE);
                $insert = $pdo->prepare("INSERT INTO online_matches (player1_id, player2_id, words_json, player1_wait_seconds, status) VALUES (?, ?, ?, ?, 'playing')");
                $insert->execute([$opponentId, $userId, $words, $waitSeconds]);
                $matchId = (int)$pdo->lastInsertId();
                $pdo->commit();
                $match = $pdo->query('SELECT * FROM online_matches WHERE id = ' . $matchId)->fetch();
                reply(matchPayload($pdo, $match, $userId));
            }
            $pdo->prepare('INSERT INTO online_queue (user_id) VALUES (?) ON DUPLICATE KEY UPDATE joined_at = CURRENT_TIMESTAMP')->execute([$userId]);
            $pdo->commit();
            reply(['status' => 'waiting', 'estimated_wait' => waitEstimate($pdo), 'queue_count' => max(0, (int)$pdo->query('SELECT COUNT(*) FROM online_queue')->fetchColumn() - 1)]);
        } finally { $pdo->query("SELECT RELEASE_LOCK('keydash_matchmaking')"); }
    }
    if ($action === 'cancel') {
        $pdo->prepare('DELETE FROM online_queue WHERE user_id = ?')->execute([$userId]);
        reply(['status' => 'cancelled']);
    }
    if ($action === 'poll') {
        $match = activeMatch($pdo, $userId);
        if ($match) reply(matchPayload($pdo, $match, $userId));
        $stmt = $pdo->prepare('SELECT 1 FROM online_queue WHERE user_id = ?');
        $stmt->execute([$userId]);
        if ($stmt->fetchColumn()) reply(['status' => 'waiting', 'estimated_wait' => waitEstimate($pdo), 'queue_count' => max(0, (int)$pdo->query('SELECT COUNT(*) FROM online_queue')->fetchColumn() - 1)]);
        $finished = recentFinishedMatch($pdo, $userId);
        if ($finished) reply(matchPayload($pdo, $finished, $userId));
        reply(['status' => 'idle']);
    }
    if ($action === 'progress') {
        $matchId = filter_var($request['match_id'] ?? null, FILTER_VALIDATE_INT);
        $words = filter_var($request['words'] ?? null, FILTER_VALIDATE_INT);
        $wpm = filter_var($request['wpm'] ?? null, FILTER_VALIDATE_INT);
        if (!$matchId || $words === false || $words < 0 || $words > 10 || $wpm === false || $wpm < 0 || $wpm > 1000) reply(['error' => 'Invalid race update.'], 422);
        $pdo->beginTransaction();
        $stmt = $pdo->prepare('SELECT * FROM online_matches WHERE id = ? FOR UPDATE');
        $stmt->execute([$matchId]); $match = $stmt->fetch();
        if (!$match || !in_array($userId, [(int)($match['player1_id'] ?? 0), (int)($match['player2_id'] ?? 0)], true)) { $pdo->rollBack(); reply(['error' => 'Match not found.'], 404); }
        if ($match['status'] === 'playing') {
            $first = (int)$match['player1_id'] === $userId;
            $wordsCol = $first ? 'player1_words' : 'player2_words';
            $wpmCol = $first ? 'player1_wpm' : 'player2_wpm';
            $pdo->prepare("UPDATE online_matches SET {$wordsCol} = GREATEST({$wordsCol}, ?), {$wpmCol} = ? WHERE id = ?")->execute([$words, $wpm, $matchId]);
            if ($words >= 10) applyMatchResult($pdo, $match, $userId);
        }
        $pdo->commit();
        $stmt->execute([$matchId]); $updated = $stmt->fetch();
        reply(matchPayload($pdo, $updated, $userId));
    }
    if ($action === 'forfeit') {
        $matchId = filter_var($request['match_id'] ?? null, FILTER_VALIDATE_INT);
        if (!$matchId) reply(['error' => 'Invalid match.'], 422);
        $pdo->beginTransaction();
        $stmt = $pdo->prepare('SELECT * FROM online_matches WHERE id = ? FOR UPDATE');
        $stmt->execute([$matchId]); $match = $stmt->fetch();
        if (!$match || !in_array($userId, [(int)($match['player1_id'] ?? 0), (int)($match['player2_id'] ?? 0)], true)) { $pdo->rollBack(); reply(['error' => 'Match not found.'], 404); }
        if ($match['status'] === 'playing') {
            $winnerId = (int)$match['player1_id'] === $userId ? (int)$match['player2_id'] : (int)$match['player1_id'];
            applyMatchResult($pdo, $match, $winnerId);
        }
        $pdo->commit();
        $stmt->execute([$matchId]); $updated = $stmt->fetch();
        reply(matchPayload($pdo, $updated, $userId));
    }
    reply(['error' => 'Unknown action.'], 400);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    reply(['error' => 'The online match service could not reach the database. Import the latest database.sql and make sure MySQL is running.'], 500);
}
