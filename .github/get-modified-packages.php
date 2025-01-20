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
    return match (true) {
        str_contains($packageDir, 'Symfony/Bridge/') => 'bridge',
        str_contains($packageDir, 'Symfony/Bundle/') => 'bundle',
        preg_match('@Symfony/Component/[^/]+/Bridge/@', $packageDir) => 'component_bridge',
        str_contains($packageDir, 'Symfony/Component/') => 'component',
        str_contains($packageDir, 'Symfony/Contracts/') => 'contract',
        str_ends_with($packageDir, 'Symfony/Contracts') => 'contracts',
        default => throw new \LogicException(),
    };
}

$newPackage = [];
$modifiedPackages = [];
foreach ($modifiedFiles as $file) {
    foreach ($allPackages as $package) {
        if (str_starts_with($file, $package)) {
            $modifiedPackages[$package] = true;
            if (str_ends_with($file, 'LICENSE')) {
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
    $composerData = json_decode(file_get_contents($directory.'/composer.json'), true);
    $name = $composerData['name'] ?? 'unknown';
    $requiresDeprecationContracts = isset($composerData['require']['symfony/deprecation-contracts']);
    $output[] = ['name' => $name, 'directory' => $directory, 'new' => $newPackage[$directory] ?? false, 'type' => getPackageType($directory), 'requires_deprecation_contracts' => $requiresDeprecationContracts];
}

echo json_encode($output);
