<?php
$base = dirname(__DIR__);
$dirs = [
    $base,
    $base . DIRECTORY_SEPARATOR . 'admin',
    $base . DIRECTORY_SEPARATOR . 'api' . DIRECTORY_SEPARATOR . 'v1',
    $base . DIRECTORY_SEPARATOR . 'core',
    $base . DIRECTORY_SEPARATOR . 'models',
    $base . DIRECTORY_SEPARATOR . 'bot',
    $base . DIRECTORY_SEPARATOR . 'jobs',
];

$errors = [];
$ok     = 0;

foreach ($dirs as $dir) {
    foreach (glob($dir . DIRECTORY_SEPARATOR . '*.php') ?: [] as $file) {
        if (basename($file) === 'check_syntax.php') continue;
        try {
            token_get_all(file_get_contents($file), TOKEN_PARSE);
            $ok++;
        } catch (\ParseError $e) {
            $errors[] = basename($file) . ': ' . $e->getMessage() . ' (line ' . $e->getLine() . ')';
        }
    }
}

echo "Checked: $ok files\n";
if ($errors) {
    echo "ERRORS (" . count($errors) . "):\n";
    foreach ($errors as $e) {
        echo "  $e\n";
    }
} else {
    echo "All $ok files passed - no syntax errors!\n";
}
