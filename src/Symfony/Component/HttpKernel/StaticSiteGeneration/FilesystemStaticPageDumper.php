<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\StaticSiteGeneration;

use Symfony\Component\Filesystem\Filesystem;

/**
 * @author Thomas Bibaut <bibaut.t@gmail.com>
 */
final class FilesystemStaticPageDumper implements StaticPageDumperInterface
{
    private ?Filesystem $filesystem = null;

    public function __construct(
        private string $projectDir,
    ) {
    }

    public function dump(string $uri, string $content, ?string $format = null): void
    {
        $fileName = '/' === $uri ? 'index.html' : $uri;

        if ($format && !str_ends_with($uri, '.'.$format)) {
            $fileName = \sprintf('%s.%s', $uri, $format);
        }

        $staticPagesDirectory = \sprintf('%s/%s/static-pages', $this->projectDir, $this->getPublicDirectory());

        $this->filesystem ??= new Filesystem();
        $this->filesystem->dumpFile(\sprintf('%s/%s', $staticPagesDirectory, $fileName), $content);
    }

    private function getPublicDirectory(): string
    {
        $defaultPublicDir = 'public';
        $composerFilePath = \sprintf('%s/composer.json', $this->projectDir);

        if (!file_exists($composerFilePath)) {
            return $defaultPublicDir;
        }

        $this->filesystem ??= new Filesystem();
        $composerConfig = json_decode($this->filesystem->readFile($composerFilePath), true, flags: \JSON_THROW_ON_ERROR);

        return $composerConfig['extra']['public-dir'] ?? $defaultPublicDir;
    }
}
