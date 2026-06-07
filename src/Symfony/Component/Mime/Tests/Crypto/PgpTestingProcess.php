<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mime\Tests\Crypto;

/*
 * @author Florent Morselli <florent.morselli@spomky-labs.com>
 *
 * Original idea by PuLLi <the@pulli.dev>
 */
use Symfony\Component\Mime\Exception\RuntimeException;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

final class PgpTestingProcess
{
    public function __construct(
        private readonly string $binaryPath = 'gpg',
        private readonly string $tempPrefix = 'GPGMIME',
    ) {
    }

    public function verify(string $data, string $signature, string $pgpKey): bool
    {
        $temporarySignature = tempnam(sys_get_temp_dir(), $this->tempPrefix);
        file_put_contents($temporarySignature, $signature);
        $temporaryData = tempnam(sys_get_temp_dir(), $this->tempPrefix);
        file_put_contents($temporaryData, $data);
        $temporaryGnupgHome = $this->createTemporaryGnupgHome();
        $this->storeKeys([$pgpKey => null], $temporaryGnupgHome);

        $command = [
            '--auto-key-locate',
            'local',
            '--verify',
            $this->convertToMsysPath($temporarySignature),
            $this->convertToMsysPath($temporaryData),
        ];

        try {
            $this->execute($command, $data, $temporaryGnupgHome);

            return true;
        } catch (\Throwable) {
            return false;
        } finally {
            unlink($temporarySignature);
            unlink($temporaryData);
            $this->removeDirectory($temporaryGnupgHome);
        }
    }

    public function decrypt(string $data, string $pgpKey, #[\SensitiveParameter] ?string $passphrase = null): string
    {
        $temporaryData = tempnam(sys_get_temp_dir(), $this->tempPrefix);
        file_put_contents($temporaryData, $data);
        $temporaryGnupgHome = $this->createTemporaryGnupgHome();
        $this->storeKeys([$pgpKey => $passphrase], $temporaryGnupgHome);

        $command = [
            '--auto-key-locate',
            'local',
        ];
        if ($passphrase) {
            $command[] = '--pinentry-mode';
            $command[] = 'loopback';
            $command[] = '--passphrase';
            $command[] = $passphrase;
        }
        $command[] = '--decrypt';
        $command[] = $this->convertToMsysPath($temporaryData);

        try {
            $output = $this->execute($command, $data, $temporaryGnupgHome);
        } finally {
            unlink($temporaryData);
            $this->removeDirectory($temporaryGnupgHome);
        }

        return $output;
    }

    /**
     * @param string[] $command
     */
    private function execute(array $command, ?string $input, string $gnupgHome): string
    {
        array_unshift($command, $this->binaryPath);
        $process = new Process($command);
        $process->setEnv(['GNUPGHOME' => $this->convertToMsysPath($gnupgHome)]);
        if ($input) {
            $process->setInput($input);
        }
        try {
            $process->mustRun();
        } catch (ProcessFailedException $e) {
            throw new RuntimeException($e->getMessage());
        }

        return $process->getOutput();
    }

    /**
     * @param array<string, string|null> $filePaths
     */
    private function storeKeys(#[\SensitiveParameter] array $filePaths, string $gnupgHome): void
    {
        foreach ($filePaths as $filePath => $passphrase) {
            $command = [];
            if (null !== $passphrase) {
                $command[] = '--pinentry-mode';
                $command[] = 'loopback';
                $command[] = '--passphrase';
                $command[] = $passphrase;
            }
            $command[] = '--import';
            $command[] = $this->convertToMsysPath($filePath);
            $this->execute($command, null, $gnupgHome);
        }
    }

    private function createTemporaryGnupgHome(): string
    {
        $temporaryDir = sys_get_temp_dir().\DIRECTORY_SEPARATOR.$this->tempPrefix.bin2hex(random_bytes(8));
        if (!mkdir($temporaryDir, 0o700) && !is_dir($temporaryDir)) {
            throw new RuntimeException('Unable to create temporary GNUPGHOME directory.');
        }

        return $temporaryDir;
    }

    private function removeDirectory(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($files as $file) {
            if ($file->isDir()) {
                rmdir($file->getPathname());
            } else {
                unlink($file->getPathname());
            }
        }

        rmdir($path);
    }

    private function convertToMsysPath(string $path): string
    {
        if ('\\' === \DIRECTORY_SEPARATOR && preg_match('/^([a-zA-Z]):/', $path, $matches)) {
            return '/'.strtolower($matches[1]).str_replace('\\', '/', substr($path, 2));
        }

        return $path;
    }
}
