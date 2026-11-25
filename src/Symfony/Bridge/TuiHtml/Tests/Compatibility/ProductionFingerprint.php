<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\TuiHtml\Tests\Compatibility;

final class ProductionFingerprint
{
    public static function capture(): array
    {
        $files = [];
        $counts = ['php' => 0, 'css' => 0, 'composer' => 0];
        foreach (['bridge' => \dirname(__DIR__, 2), 'engine' => \dirname((new \ReflectionClass(\Symfony\Component\HtmlRenderer\HtmlRenderer::class))->getFileName()), 'tui' => \dirname((new \ReflectionClass(\Symfony\Component\Tui\Tui::class))->getFileName())] as $label => $directory) {
            foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS)) as $file) {
                $relative = substr($file->getPathname(), \strlen($directory) + 1);
                $kind = 'composer.json' === $file->getFilename() ? 'composer' : $file->getExtension();
                if (isset($counts[$kind]) && !preg_match('~(?:^|/)(Tests|vendor)/~', $relative)) {
                    $files[$label.'/'.$relative] = hash_file('sha256', $file->getPathname());
                    ++$counts[$kind];
                }
            }
        }
        ksort($files);

        return ['sha256' => hash('sha256', json_encode($files, \JSON_THROW_ON_ERROR)), 'production_files' => \count($files), 'by_kind' => $counts];
    }
}
