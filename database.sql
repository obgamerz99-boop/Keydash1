CREATE DATABASE IF NOT EXISTS keydash CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE keydash;

CREATE TABLE IF NOT EXISTS users (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(24) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    avatar VARCHAR(16) NOT NULL DEFAULT '⌨️',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS scores (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    mode ENUM('practice', 'race') NOT NULL,
    difficulty ENUM('easy', 'medium', 'hard') NOT NULL,
    wpm SMALLINT UNSIGNED NOT NULL,
    accuracy TINYINT UNSIGNED NOT NULL,
    words_correct TINYINT UNSIGNED NOT NULL,
    elapsed_ms INT UNSIGNED NOT NULL,
    won TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_scores_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX leaderboard_idx (mode, won, wpm, accuracy)
);

CREATE TABLE IF NOT EXISTS online_queue (
    user_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
    joined_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_queue_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX queue_joined_idx (joined_at)
);

CREATE TABLE IF NOT EXISTS online_matches (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    player1_id BIGINT UNSIGNED NOT NULL,
    player2_id BIGINT UNSIGNED NOT NULL,
    words_json JSON NOT NULL,
    status ENUM('playing', 'completed') NOT NULL DEFAULT 'playing',
    player1_words TINYINT UNSIGNED NOT NULL DEFAULT 0,
    player2_words TINYINT UNSIGNED NOT NULL DEFAULT 0,
    player1_wpm SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    player2_wpm SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    player1_wait_seconds SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    player2_wait_seconds SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    winner_id BIGINT UNSIGNED NULL,
    started_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    completed_at TIMESTAMP NULL DEFAULT NULL,
    CONSTRAINT fk_match_player1 FOREIGN KEY (player1_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_match_player2 FOREIGN KEY (player2_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_match_winner FOREIGN KEY (winner_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX match_player1_status_idx (player1_id, status),
    INDEX match_player2_status_idx (player2_id, status),
    INDEX match_leaderboard_idx (status, winner_id)
);

CREATE TABLE IF NOT EXISTS online_stats (
    user_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
    wins INT UNSIGNED NOT NULL DEFAULT 0,
    losses INT UNSIGNED NOT NULL DEFAULT 0,
    games_played INT UNSIGNED NOT NULL DEFAULT 0,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_online_stats_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX online_stats_board_idx (wins, games_played)
);
