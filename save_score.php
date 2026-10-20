<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo json_encode(['error' => 'POST required']); exit; }
if (empty($_SESSION['username']) || !hash_equals($_SESSION['csrf'] ?? '', $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')) { http_response_code(403); echo json_encode(['error' => 'Please log in again to save your score.']); exit; }
$data = json_decode(file_get_contents('php://input'), true);
$mode = $data['mode'] ?? '';
$level = $data['level'] ?? '';
$wpm = filter_var($data['wpm'] ?? null, FILTER_VALIDATE_INT);
$accuracy = filter_var($data['accuracy'] ?? null, FILTER_VALIDATE_INT);
$wordsCorrect = filter_var($data['words_correct'] ?? null, FILTER_VALIDATE_INT);
$elapsed = filter_var($data['elapsed_ms'] ?? null, FILTER_VALIDATE_INT);
$won = !empty($data['won']) ? 1 : 0;
if (!in_array($mode, ['practice', 'race'], true) || !in_array($level, ['easy', 'medium', 'hard'], true) || $wpm === false || $wpm < 0 || $wpm > 1000 || $accuracy === false || $accuracy < 0 || $accuracy > 100 || $wordsCorrect === false || $wordsCorrect < 0 || $wordsCorrect > 10 || $elapsed === false || $elapsed < 1 || $elapsed > 3600000) { http_response_code(422); echo json_encode(['error' => 'Invalid score']); exit; }
try {
    require_once __DIR__ . '/db.php';
    $pdo = database();
    $stmt = $pdo->prepare('SELECT id FROM users WHERE username = ?');
    $stmt->execute([$_SESSION['username']]);
    $userId = $stmt->fetchColumn();
    if (!$userId) { http_response_code(403); echo json_encode(['error' => 'Account not found']); exit; }
    $stmt = $pdo->prepare('INSERT INTO scores (user_id, mode, difficulty, wpm, accuracy, words_correct, elapsed_ms, won) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
    $stmt->execute([$userId, $mode, $level, $wpm, $accuracy, $wordsCorrect, $elapsed, $won]);
    echo json_encode(['saved' => true]);
} catch (Throwable $e) { http_response_code(500); echo json_encode(['error' => 'Could not save score. Please import the latest database.sql.']); }
