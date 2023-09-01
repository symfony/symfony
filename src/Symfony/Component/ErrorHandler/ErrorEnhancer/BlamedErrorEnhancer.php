<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\ErrorHandler\ErrorEnhancer;

use Symfony\Component\Process\Exception\ExceptionInterface;
use Symfony\Component\Process\Process;

/**
 * @author Dany Maillard <danymaillard93b@gmail.com>
 */
class BlamedErrorEnhancer implements ErrorEnhancerInterface
{
    private ?bool $isEnabled = null;

    public function enhance(\Throwable $error): ?\Throwable
    {
        if (!$this->isEnabled()) {
            return null;
        }

        $blamedTrace = array_map($this->blameStep(...), $error->getTrace());

        (new \ReflectionProperty(\Error::class, 'trace'))->setValue($error, $blamedTrace);

        return $error;
    }

    private function blameStep(array $step): array
    {
        ['file' => $file, 'line' => $line] = $step;

        return $file && $line ? $step + $this->blame($file, $line) : $step;
    }

    private function blame(string $file, int $line): array
    {
        $process = Process::fromShellCommandline(sprintf('git blame %s -L %s,+1 -p', $file, $line));
        $process->run();

        if (!$process->isSuccessful()) {
            return [];
        }

        preg_match(<<<PATTERN
            /author (?<author>.*)
            author-mail <(?<authormail>.*)>
            author-time (?<authortime>.*)
            author-tz (?<authortz>.*)
            committer (?<commiter>.*)
            committer-mail <(?<commitermail>.*)>
            committer-time (?<commitertime>.*)
            committer-tz (?<commitertz>.*)
            summary (?<summary>.*)/
            PATTERN,
            $process->getOutput(),
            $matches,
        );

        return [
            'author' => [
                'name' => $matches['author'],
                'mail' => $matches['authormail'],
                'time' => new \DateTimeImmutable('@'.$matches['authortime'], new \DateTimeZone($matches['authortz'])),
            ],
            'commiter' => [
                'name' => $matches['commiter'],
                'mail' => $matches['commitermail'],
                'time' => new \DateTimeImmutable('@'.$matches['commitertime'], new \DateTimeZone($matches['commitertz'])),
            ],
            'summary' => $matches['summary'],
        ];
    }

    private function isEnabled(): bool
    {
        if (null !== $this->isEnabled) {
            return $this->isEnabled;
        }

        if (!class_exists(Process::class)) {
            return $this->isEnabled = false;
        }

        try {
            Process::fromShellCommandline('type git')->mustRun();
        } catch (ExceptionInterface $e) {
            return $this->isEnabled = false;
        }

        return $this->isEnabled = true;
    }
}
