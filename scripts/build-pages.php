<?php
declare(strict_types=1);

$projectRoot = dirname(__DIR__);
$sourceRoot = $projectRoot . '/public';
$targetRoot = $projectRoot . '/_site';
$basePath = $argv[1] ?? '/Sushi-Relax/';

if (!str_starts_with($basePath, '/') || !str_ends_with($basePath, '/')) {
    fwrite(STDERR, "Base path must start and end with a slash.\n");
    exit(1);
}

function remove_directory(string $directory): void
{
    if (!is_dir($directory)) {
        return;
    }

    $items = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );

    foreach ($items as $item) {
        $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
    }

    rmdir($directory);
}

remove_directory($targetRoot);
mkdir($targetRoot, 0775, true);

$files = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($sourceRoot, FilesystemIterator::SKIP_DOTS)
);

foreach ($files as $file) {
    if (!$file->isFile()) {
        continue;
    }

    $relativePath = substr($file->getPathname(), strlen($sourceRoot) + 1);
    $extension = strtolower(pathinfo($relativePath, PATHINFO_EXTENSION));

    // GitHub Pages is static hosting: do not expose PHP source or Apache rules.
    if ($extension === 'php' || basename($relativePath) === '.htaccess') {
        continue;
    }

    $destination = $targetRoot . '/' . $relativePath;
    if (!is_dir(dirname($destination))) {
        mkdir(dirname($destination), 0775, true);
    }

    $contents = file_get_contents($file->getPathname());
    if ($contents === false) {
        throw new RuntimeException('Cannot read ' . $file->getPathname());
    }

    if ($extension === 'html') {
        $contents = preg_replace(
            '~\b(href|src|action|data-product-url)=(["\'])/(?!/|Sushi-Relax/)~',
            '$1=$2' . $basePath,
            $contents
        ) ?? $contents;
    } elseif ($extension === 'css') {
        $contents = preg_replace(
            '~url\((["\']?)/(?!/|Sushi-Relax/)~',
            'url($1' . $basePath,
            $contents
        ) ?? $contents;
    } elseif ($extension === 'js') {
        $contents = preg_replace(
            '~(["\'])/(?!/|Sushi-Relax/)(?=(?:api/|assets/|products/|qr-menu/|(?:index|about|contacts|delivery|privacy|reservation|checkout|success)\.html|#))~',
            '$1' . $basePath,
            $contents
        ) ?? $contents;
    }

    file_put_contents($destination, $contents);
}

file_put_contents($targetRoot . '/.nojekyll', '');
echo "GitHub Pages build created in {$targetRoot} with base {$basePath}\n";
