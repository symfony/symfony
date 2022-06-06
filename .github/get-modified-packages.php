<?php

/*
 * Given a list of all packages, find the package that have been modified.
 */

if (3 > $_SERVER['argc']) {
    echo "Usage: app-packages modified-files\n";
    exit(1);
}

$allPackages = json_decode($_SERVER['argv'][1], true, 512, \JSON_THROW_ON_ERROR);
$modifiedFiles = json_decode($_SERVER['argv'][2], true, 512, \JSON_THROW_ON_ERROR);

// Sort to get the longest name first (match bridge not component)
usort($allPackages, function($a, $b) {
    return strlen($b) <=> strlen($a) ?: $a <=> $b;
});

function getPackageType(string $packageDir): string
{
    if (preg_match('@Symfony/Bridge/@', $packageDir)) {
        return 'bridge';
    }

    if (preg_match('@Symfony/Bundle/@', $packageDir)) {
        return 'bundle';
    }

    if (preg_match('@Symfony/Component/[^/]+/Bridge/@', $packageDir)) {
        return 'component_bridge';
    }

    if (preg_match('@Symfony/Component/@', $packageDir)) {
        return 'component';
    }

    if (preg_match('@Symfony/Contracts/@', $packageDir)) {
        return 'contract';
    }

    if (preg_match('@Symfony/Contracts$@', $packageDir)) {
        return 'contracts';
    }

    throw new \LogicException();
}

$newPackage = [];
$modifiedPackages = [];
foreach ($modifiedFiles as $file) {
    foreach ($allPackages as $package) {
        if (0 === strpos($file, $package)) {
            $modifiedPackages[$package] = true;
            if ('LICENSE' === substr($file, -7)) {
                /*
                 * There is never a reason to modify the LICENSE file, this diff
                 * must be adding a new package
                 */
                $newPackage[$package] = true;
            }
            break;
        }
    }
}

$output = [];
foreach ($modifiedPackages as $directory => $bool) {
    $name = json_decode(file_get_contents($directory.'/composer.json'), true)['name'] ?? 'unknown';
    $output[] = ['name' => $name, 'directory' => $directory, 'new' => $newPackage[$directory] ?? false, 'type' => getPackageType($directory)];
}

echo json_encode($output);
