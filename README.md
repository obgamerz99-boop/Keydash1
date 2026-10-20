# Keydash

A small PHP/MySQL typing game starter with a blue dark theme, practice rounds, bot races, and username/password accounts.

## Start the site locally

1. Install PHP with the PDO MySQL extension and MySQL (XAMPP includes both).
2. Start MySQL in XAMPP, then import `database.sql` with phpMyAdmin or the MySQL command line.
3. If your MySQL username/password differ from `root` and a blank password, set `KEYDASH_DB_HOST`, `KEYDASH_DB_NAME`, `KEYDASH_DB_USER`, and `KEYDASH_DB_PASSWORD` in your PHP server environment.
4. In a terminal, open this folder and run `php -S localhost:8000`.
5. Visit `http://localhost:8000`.

Accounts use prepared SQL statements and PHP password hashing. Usernames are 3–24 letters, numbers, or underscores; passwords must be at least 8 characters. Import `database.sql` again after updating the site to add the scores table; it preserves existing accounts and scores.

## Game

Choose Practice or Bot Race and a difficulty. Every round draws ten words from that level's word list. The bot targets 25 WPM on Easy, 45 WPM on Medium, and 70 WPM on Hard. The game shows live player and bot scores, words completed, accuracy, and WPM. Typing and win sounds are synthesized in the browser. The background song at `assets/sabor-moreno.mp3` starts when a round begins, loops, and plays softly. Scores from logged-in players are saved to the leaderboard.

## Online duels

Online play requires an account. Two logged-in players who open Online and press **Find an opponent** are paired through MySQL matchmaking. Each gets the same shuffled ten-word round containing easy, medium, and hard words. The match page shows progress, the opponent's WPM, the player's online record, and a forfeit button. The online leaderboard ranks by best WPM and also shows each player's wins, losses, and games played.

Players can open **Profile** to change their username, choose an emoji avatar, and view their best and average WPM, bot record, and online record. The database connection adds the avatar column automatically for existing Keydash databases. Round results use an in-page dialog with the player's and opponent's WPM and progress. Sound controls stay in the upper-right corner during play.

After adding this online feature, import `database.sql` again in phpMyAdmin to create the queue, match, and online statistics tables. `CREATE TABLE IF NOT EXISTS` keeps existing accounts and scores. To try matchmaking locally, open the site in two browser profiles, sign into two different accounts, and queue from both. A public XAMPP site is only reachable on your own computer by default; to let people outside your computer join, host the PHP/MySQL site on a public web host with HTTPS and point a domain at it.
