<?php

declare(strict_types=1);

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Scheduler\Cron;

use Psr\Log\LoggerInterface;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class CronGenerator
{
    private const DEFAULT_PATH = '/etc/cron.d';

    private $fs;
    private $logger;

    public function __construct(Filesystem $fs, LoggerInterface $logger = null)
    {
        $this->fs = $fs;
        $this->logger = $logger;
    }

    public function generate(string $filename = 'sf_app', string $path = self::DEFAULT_PATH): void
    {
        $file = sprintf('%s/%s', $path, $filename);

        if ($this->fs->exists($file)) {
            $this->log(sprintf('The "%s" cron already exist', $filename));

            return;
        }

        $this->fs->mkdir($file);
    }

    public function write($content, string $filename = 'sf_app', string $path = self::DEFAULT_PATH): void
    {
        $file = sprintf('%s/%s', $path, $filename);

        $this->fs->dumpFile($file, $content);
    }

    private function log(string $message, array $context = []): void
    {
        if (null === $this->logger) {
            return;
        }

        $this->logger->warning($message, $context);
    }
}
