<?php
/**
 * Migrate skill_categories icons from emojis to Font Awesome class names.
 * Run this if you already imported the schema with emoji icons.
 * Usage: php migrate_icons.php
 */

// Restrict to CLI
if (php_sapi_name() !== 'cli') {
    die('This file can only be run from the command line.');
}

$host = getenv('DB_HOST') ?: 'localhost';
$user = getenv('DB_USER') ?: 'root';
$pass = getenv('DB_PASS') ?: '';
$dbName = getenv('DB_NAME') ?: 'skillloop';

echo "Connecting to MySQL at $host...\n";

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbName;charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);

    $emojiToFa = [
        '📚' => 'fa-book',
        '🎵' => 'fa-music',
        '💻' => 'fa-laptop-code',
        '🍳' => 'fa-utensils',
        '🎨' => 'fa-palette',
        '🏋️' => 'fa-dumbbell',
        '🌐' => 'fa-globe',
        '📷' => 'fa-camera',
        '✍️' => 'fa-pen-fancy',
        '💼' => 'fa-briefcase',
        '🌟' => 'fa-star',
    ];

    $stmt = $pdo->query("SELECT id, icon FROM skill_categories");
    $updated = 0;
    foreach ($stmt as $row) {
        $newIcon = $emojiToFa[$row['icon']] ?? null;
        if ($newIcon) {
            $pdo->prepare("UPDATE skill_categories SET icon = ? WHERE id = ?")->execute([$newIcon, $row['id']]);
            echo "  Updated '{$row['icon']}' -> '$newIcon' (ID: {$row['id']})\n";
            $updated++;
        }
    }

    echo "\nDone! $updated categories updated.\n";

} catch (PDOException $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}