<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Intl\Util;

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Intl\Exception\RuntimeException;

/**
 * @internal
 */
final class GitRepository
{
    private string $path;

    public function __construct(string $path)
    {
        $this->path = $path;

        $this->getUrl();
    }

    public static function download(string $remote, string $targetDir): self
    {
        self::exec('which git', 'The command "git" is not installed.');

        $filesystem = new Filesystem();

        if (!$filesystem->exists($targetDir.'/.git')) {
            $filesystem->remove($targetDir);
            $filesystem->mkdir($targetDir);

            self::exec(sprintf('git clone %s %s', escapeshellarg($remote), escapeshellarg($targetDir)));
        }

        return new self(realpath($targetDir));
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function getUrl(): string
    {
        return $this->getLastLine($this->execInPath('git config --get remote.origin.url'));
    }

    public function getLastCommitHash(): string
    {
        return $this->getLastLine($this->execInPath('git log -1 --format="%H"'));
    }

    public function getLastAuthor(): string
    {
        return $this->getLastLine($this->execInPath('git log -1 --format="%an"'));
    }

    public function getLastAuthoredDate(): \DateTimeImmutable
    {
        return new \DateTimeImmutable($this->getLastLine($this->execInPath('git log -1 --format="%ai"')));
    }

    public function getLastTag(?callable $filter = null): string
    {
        $tags = $this->execInPath('git tag -l --sort=v:refname');

        if (null !== $filter) {
            $tags = array_filter($tags, $filter);
        }

        return $this->getLastLine($tags);
    }

    public function checkout(string $branch): void
    {
        $this->execInPath(sprintf('git checkout %s', escapeshellarg($branch)));
    }

    private function execInPath(string $command): array
    {
        return self::exec(sprintf('cd %s && %s', escapeshellarg($this->path), $command));
    }

    private static function exec(string $command, ?string $customErrorMessage = null): array
    {
        exec(sprintf('%s 2>&1', $command), $output, $result);

        if (0 !== $result) {
            throw new RuntimeException($customErrorMessage ?? sprintf('The "%s" command failed.', $command));
        }

        return $output;
    }

    private function getLastLine(array $output): string
    {
        return array_pop($output);
    }
}
