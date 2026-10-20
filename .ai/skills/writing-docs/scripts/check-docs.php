<?php

/**
 * Check api-core's documents in docs/: file names and numbering, the index, titles and Related sections, the length
 * of prose lines, links and anchors, mentions of real applications, and the config keys, classes, namespaces, methods
 * and constants of api-core, which they cite. Prints `file:line: problem` for each and exits with 1, when there are
 * any. Run it from the repository's root:
 *
 *     php8.5 .ai/skills/writing-docs/scripts/check-docs.php
 */

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as QueryBuilder;

$root = (string) getcwd();

if (!is_file($root . '/artisan') || !is_dir($root . '/docs')) {
    fwrite(STDERR, "Run it from the root of api-core.\n");
    exit(2);
}

require $root . '/vendor/autoload.php';

$app = require $root . '/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

/** Names of real applications, which the documents mustn't mention. */
$applications = ['prytax'];

/** The longest line of prose; tables and code blocks may run longer. */
$maxLength = 120;

$problems = [];

$report = function (string $file, ?int $line, string $problem) use (&$problems): void {
    $problems[] = $line === null ? "$file: $problem" : "$file:$line: $problem";
};

/**
 * The files under a directory with the extension, relative to it and sorted.
 */
$filesIn = function (string $directory, string $extension): array {
    $files = [];
    $entries = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS)
    );

    foreach ($entries as $entry) {
        if ($entry->getExtension() === $extension) {
            $files[] = substr($entry->getPathname(), strlen($directory) + 1);
        }
    }

    sort($files);

    return $files;
};

$documents = array_map(fn (string $file): string => "docs/$file", $filesIn("$root/docs", 'md'));

$composer = json_decode((string) file_get_contents($root . '/composer.json'), true);
$prefixes = ($composer['autoload']['psr-4'] ?? []) + ($composer['autoload-dev']['psr-4'] ?? []);

/** Short names of api-core's classes, with their full names, and api-core's code as one text. */
$classesByName = [];
$sources = '';

foreach ($composer['autoload']['psr-4'] ?? [] as $prefix => $directory) {
    $directory = $root . '/' . rtrim($directory, '/');

    foreach ($filesIn($directory, 'php') as $file) {
        $class = $prefix . str_replace('/', '\\', substr($file, 0, -4));
        $classesByName[class_basename($class)][] = $class;
        $sources .= file_get_contents($directory . '/' . $file);
    }
}

/**
 * Short names of Laravel's classes, with their full names, which are read on the first use, as a citation of a short
 * name, which api-core shares with Laravel (such as `File::types()`), may mean Laravel's class.
 */
$frameworkClasses = null;
$frameworkClassesNamed = function (string $short) use ($root, $filesIn, &$frameworkClasses): array {
    if ($frameworkClasses === null) {
        $frameworkClasses = [];

        foreach ($filesIn("$root/vendor/laravel/framework/src/Illuminate", 'php') as $file) {
            $class = 'Illuminate\\' . str_replace('/', '\\', substr($file, 0, -4));
            $frameworkClasses[class_basename($class)][] = $class;
        }
    }

    return $frameworkClasses[$short] ?? [];
};

$isType = fn (string $name): bool => class_exists($name)
    || interface_exists($name)
    || trait_exists($name)
    || enum_exists($name);

$isNamespace = function (string $name) use ($prefixes, $root): bool {
    foreach ($prefixes as $prefix => $directory) {
        if (!str_starts_with($name . '\\', $prefix)) {
            continue;
        }

        $relative = str_replace('\\', '/', substr($name . '\\', strlen($prefix)));

        if (is_dir($root . '/' . rtrim($directory, '/') . '/' . $relative)) {
            return true;
        }
    }

    return false;
};

/**
 * Whether the class has the method, or, for a model, its query builders have it.
 */
$hasMethod = function (string $class, string $method): bool {
    if (method_exists($class, $method)) {
        return true;
    }

    if (!is_subclass_of($class, Model::class)) {
        return false;
    }

    return method_exists(EloquentBuilder::class, $method)
        || method_exists(QueryBuilder::class, $method)
        || method_exists($class, 'scope' . ucfirst($method));
};

$hasConstant = fn (string $class, string $name): bool => (new ReflectionClass($class))->hasConstant($name);

$checkCitations = function (
    string $document,
    int $number,
    string $line,
) use (
    $report,
    $classesByName,
    $frameworkClassesNamed,
    $sources,
    $isType,
    $isNamespace,
    $hasMethod,
    $hasConstant,
): void {
    preg_match_all('/(?<![\w\/-])api-core\.([a-z0-9_]+(?:\.[a-z0-9_]+)*)/', $line, $keys);

    foreach (array_diff($keys[1], ['php']) as $key) {
        if (!config()->has("api-core.$key") && !str_contains($sources, "'api-core.$key'")) {
            $report($document, $number, "The config key `api-core.$key` doesn't exist.");
        }
    }

    preg_match_all('/Core\\\\[A-Za-z0-9_\\\\]+/', $line, $names);

    foreach ($names[0] as $name) {
        $name = rtrim((string) preg_replace('/\\\\+/', '\\', $name), '\\');

        if (!$isType($name) && !$isNamespace($name)) {
            $report($document, $number, "The class or namespace `$name` doesn't exist.");
        }
    }

    preg_match_all('/\b([A-Z][A-Za-z0-9_]*)::([A-Za-z_][A-Za-z0-9_]*)(\()?/', $line, $members, PREG_SET_ORDER);

    foreach ($members as $member) {
        [, $short, $name] = $member;

        if ($name === 'class' || !isset($classesByName[$short])) {
            continue;
        }

        $isCall = isset($member[3]);
        $has = fn (string $class): bool => $isCall ? $hasMethod($class, $name) : $hasConstant($class, $name);

        $found = array_filter($classesByName[$short], $has) ?: array_filter($frameworkClassesNamed($short), $has);

        if ($found === []) {
            $report($document, $number, $isCall
                ? "The method `$short::$name()` doesn't exist."
                : "The constant `$short::$name` doesn't exist.");
        }
    }
};

/**
 * The anchors of a document's headings, as GitHub makes them.
 */
$anchorCache = [];
$anchorsOf = function (string $path) use (&$anchorCache): array {
    if (isset($anchorCache[$path])) {
        return $anchorCache[$path];
    }

    $anchors = [];
    $seen = [];
    $inCode = false;

    foreach (file($path, FILE_IGNORE_NEW_LINES) ?: [] as $line) {
        if (str_starts_with(ltrim($line), '```')) {
            $inCode = !$inCode;
        }

        if ($inCode || !preg_match('/^#{1,6}\s+(.+)$/', $line, $heading)) {
            continue;
        }

        $text = (string) preg_replace('/[^\p{L}\p{N} _-]/u', '', mb_strtolower(trim($heading[1])));
        $slug = str_replace(' ', '-', $text);
        $anchors[] = isset($seen[$slug]) ? "$slug-{$seen[$slug]}" : $slug;
        $seen[$slug] = ($seen[$slug] ?? 0) + 1;
    }

    return $anchorCache[$path] = $anchors;
};

/**
 * The files, which the document's link points to (null for external links), and the problem of the link, if any.
 */
$resolveLink = function (string $document, string $target) use ($root, $anchorsOf): array {
    if (preg_match('/^[a-z][a-z0-9+.-]*:/i', $target)) {
        return [null, null];
    }

    [$path, $anchor] = array_pad(explode('#', $target, 2), 2, null);
    $file = realpath($path === '' ? "$root/$document" : $root . '/' . dirname($document) . '/' . $path);

    if ($file === false) {
        return [null, "The link to $target points to no file."];
    }

    if ($anchor !== null && str_ends_with($file, '.md') && !in_array($anchor, $anchorsOf($file), true)) {
        return [$file, "The link to $target points to no heading."];
    }

    return [$file, null];
};

$numbers = [];

foreach ($documents as $document) {
    if (!preg_match('/^(\d{2})-[a-z0-9]+(?:-[a-z0-9]+)*\.md$/', basename($document), $matches)) {
        $report($document, null, "The name doesn't match NN-kebab-case.md.");

        continue;
    }

    $numbers[dirname($document)][] = (int) $matches[1];
}

foreach ($numbers as $directory => $taken) {
    sort($taken);

    if ($taken !== range(0, count($taken) - 1)) {
        $numbering = implode(', ', $taken);
        $report($directory, null, "The documents are numbered $numbering, not from 00 without gaps.");
    }
}

$indexed = [];

foreach ($documents as $document) {
    $lines = file("$root/$document", FILE_IGNORE_NEW_LINES) ?: [];
    $isIndex = $document === 'docs/00-index.md';

    if (!str_starts_with($lines[0] ?? '', '# ')) {
        $report($document, 1, 'The first line isn\'t a "# Title".');
    }

    if (!$isIndex && !in_array('## Related', $lines, true)) {
        $report($document, null, 'There is no "## Related" section.');
    }

    $inCode = false;

    foreach ($lines as $index => $line) {
        $number = $index + 1;

        if (str_starts_with(ltrim($line), '```')) {
            $inCode = !$inCode;

            continue;
        }

        foreach ($applications as $application) {
            if (stripos($line, $application) !== false) {
                $report($document, $number, "It mentions the application $application.");
            }
        }

        $checkCitations($document, $number, $line);

        if ($inCode) {
            continue;
        }

        if (mb_strlen($line) > $maxLength && !str_starts_with(ltrim($line), '|')) {
            $report($document, $number, 'The line is ' . mb_strlen($line) . " characters long, over $maxLength.");
        }

        preg_match_all('/\]\(([^)\s]+)\)/', $line, $links);

        foreach ($links[1] as $target) {
            [$file, $problem] = $resolveLink($document, $target);

            if ($problem !== null) {
                $report($document, $number, $problem);
            }

            if ($isIndex && $file !== null) {
                $indexed[] = $file;
            }
        }
    }
}

foreach ($documents as $document) {
    if ($document !== 'docs/00-index.md' && !in_array(realpath("$root/$document"), $indexed, true)) {
        $report($document, null, 'docs/00-index.md doesn\'t list it.');
    }
}

if ($problems === []) {
    echo count($documents) . " documents checked, no problems.\n";
    exit(0);
}

echo implode("\n", $problems) . "\n" . count($problems) . " problems.\n";
exit(1);
