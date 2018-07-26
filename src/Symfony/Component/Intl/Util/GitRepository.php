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
    private $path;

    /**
     * @param string $path
     */
    public function __construct($path)
    {
        $this->path = $path;

        $this->getUrl();
    }

    /**
     * @param string $remote
     * @param string $targetDir
     *
     * @return GitRepository
     */
    public static function download($remote, $targetDir)
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

    public function getPath()
    {
        return $this->path;
    }

    public function getUrl()
    {
        return $this->getLastLine($this->execInPath('git config --get remote.origin.url'));
    }

    public function getLastCommitHash()
    {
        return $this->getLastLine($this->execInPath('git log -1 --format="%H"'));
    }

    public function getLastAuthor()
    {
        return $this->getLastLine($this->execInPath('git log -1 --format="%an"'));
    }

    public function getLastAuthoredDate()
    {
        return new \DateTime($this->getLastLine($this->execInPath('git log -1 --format="%ai"')));
    }

    public function getLastTag(callable $filter = null)
    {
        $tags = $this->execInPath('git tag -l --sort=v:refname');

        if (null !== $filter) {
            $tags = array_filter($tags, $filter);
        }

        return $this->getLastLine($tags);
    }

    public function checkout($branch)
    {
        $this->execInPath(sprintf('git checkout %s', escapeshellarg($branch)));
    }

    private function execInPath($command)
    {
        return self::exec(sprintf('cd %s && %s', escapeshellarg($this->path), $command));
    }

    private static function exec($command, $customErrorMessage = null)
    {
        exec(sprintf('%s 2>&1', $command), $output, $result);

        if (0 !== $result) {
            throw new RuntimeException(null !== $customErrorMessage ? $customErrorMessage : sprintf('The `%s` command failed.', $command));
        }

        return $output;
    }

    private function getLastLine(array $output)
    {
        return array_pop($output);
    }
}
