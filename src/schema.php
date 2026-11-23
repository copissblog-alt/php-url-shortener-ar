<?php
declare(strict_types=1);

function migrate(PDO $pdo): void
{
    $v = (int) $pdo->query('PRAGMA user_version')->fetchColumn();
    if ($v >= 1) {
        return;
    }
    $pdo->exec(<<<'SQL'
        CREATE TABLE IF NOT EXISTS users (
            id            INTEGER PRIMARY KEY,
            username      TEXT NOT NULL UNIQUE,
            password_hash TEXT NOT NULL,
            created_at    INTEGER NOT NULL
        );
        CREATE TABLE IF NOT EXISTS links (
            id          INTEGER PRIMARY KEY,
            code        TEXT NOT NULL UNIQUE,
            url         TEXT NOT NULL,
            title       TEXT NOT NULL DEFAULT '',
            active      INTEGER NOT NULL DEFAULT 1,
            clicks      INTEGER NOT NULL DEFAULT 0,
            created_at  INTEGER NOT NULL
        );
        CREATE TABLE IF NOT EXISTS clicks (
            id        INTEGER PRIMARY KEY,
            link_id   INTEGER NOT NULL REFERENCES links(id) ON DELETE CASCADE,
            ts        INTEGER NOT NULL,
            day       TEXT NOT NULL,
            referrer  TEXT NOT NULL DEFAULT '',
            device    TEXT NOT NULL DEFAULT '',
            browser   TEXT NOT NULL DEFAULT '',
            ip_hash   TEXT NOT NULL DEFAULT ''
        );
        CREATE INDEX IF NOT EXISTS clicks_link_day ON clicks(link_id, day);
        CREATE TABLE IF NOT EXISTS login_attempts (
            ip_hash TEXT NOT NULL,
            ts      INTEGER NOT NULL
        );
        CREATE INDEX IF NOT EXISTS login_attempts_ip ON login_attempts(ip_hash, ts);
        PRAGMA user_version = 1;
    SQL);
}
