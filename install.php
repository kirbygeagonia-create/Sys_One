<?php
/**
 * SkillLoop Database Installer
 * Run: php install.php
 * Imports schema.sql into MySQL directly via PDO
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
    // Connect without specifying a database
    $pdo = new PDO("mysql:host=$host;charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);

    // Create database if not exists
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbName` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    echo "Database '$dbName' ready.\n";

    // Switch to the database
    $pdo->exec("USE `$dbName`");

    // Read and execute schema.sql
    $schemaPath = __DIR__ . '/sql/schema.sql';
    if (!file_exists($schemaPath)) {
        die("Schema file not found at: $schemaPath\n");
    }

    $sql = file_get_contents($schemaPath);
    $statements = array_filter(
        array_map('trim', explode(';', $sql)),
        fn($s) => !empty($s) && !str_starts_with($s, '--')
    );

    $count = 0;
    foreach ($statements as $statement) {
        if (stripos($statement, 'CREATE TABLE') === 0 || stripos($statement, 'INSERT INTO') === 0) {
            $pdo->exec($statement);
            $count++;
        }
    }

    echo "Schema imported: $count statements executed.\n";

    // Verify tables
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    echo "Tables created: " . implode(', ', $tables) . "\n";

    $catCount = $pdo->query("SELECT COUNT(*) FROM skill_categories")->fetchColumn();
    $skillCount = $pdo->query("SELECT COUNT(*) FROM skills")->fetchColumn();
    echo "Seed data: $catCount categories, $skillCount skills.\n";

    echo "\nSuccess! Database is ready.\n";

} catch (PDOException $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "\nTroubleshooting:\n";
    echo "  - Is MySQL running? Check Services (services.msc) for 'MySQL'\n";
    echo "  - Is MySQL on a different port? Try setting DB_HOST=localhost:3307\n";
    echo "  - Wrong password? Set DB_PASS=yourpassword before running\n";
    echo "  - Using XAMPP? MySQL is usually at localhost, user: root, pass: ''\n";
    exit(1);
}