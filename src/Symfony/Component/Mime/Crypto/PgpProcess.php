<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mime\Crypto;

use Symfony\Component\Mime\Exception\RuntimeException;
use Symfony\Component\Process\Exception\RuntimeException as ProcessRuntimeException;
use Symfony\Component\Process\Process;

/**
 * @author Florent Morselli <florent.morselli@spomky-labs.com>
 *
 * Original idea by PuLLi <the@pulli.dev>
 *
 * @internal
 */
final class PgpProcess
{
    private const DEFAULT_TIMEOUT = 60;

    public function __construct(
        private readonly string $binaryPath = 'gpg',
        private readonly string $tempPrefix = 'GPGMIME',
        private readonly string $cipherAlgorithm = 'AES256',
        private readonly string $digestAlgorithm = 'SHA512',
        private readonly ?float $timeout = self::DEFAULT_TIMEOUT,
    ) {
    }

    /**
     * @param array{binary?: string, cipher_algorithm?: string, digest_algorithm?: string, temp_prefix?: string, timeout?: float|null, hide_recipients?: bool} $options
     */
    public static function fromOptions(array $options): self
    {
        $arguments = [];
        foreach (['binary' => 'binaryPath', 'temp_prefix' => 'tempPrefix', 'cipher_algorithm' => 'cipherAlgorithm', 'digest_algorithm' => 'digestAlgorithm', 'timeout' => 'timeout'] as $option => $argument) {
            if (\array_key_exists($option, $options)) {
                $arguments[$argument] = $options[$option];
            }
        }

        return new self(...$arguments);
    }

    public function sign(string $data, string $pgpKey, #[\SensitiveParameter] ?string $passphrase = null): string
    {
        $temporaryGnupgHome = $this->createTemporaryGnupgHome();
        $passphraseFile = null;

        try {
            if ($passphrase) {
                $passphraseFile = $this->writePassphraseFile($passphrase);
            }

            $this->storeKey($pgpKey, $passphraseFile, $temporaryGnupgHome);

            $command = [
                '--auto-key-locate',
                'local',
                '--armor',
                '--digest-algo',
                $this->digestAlgorithm,
                '--detach-sign',
                '--textmode',
            ];

            if (null !== $passphraseFile) {
                $command[] = '--pinentry-mode';
                $command[] = 'loopback';
                $command[] = '--passphrase-file';
                $command[] = $this->convertToMsysPath($passphraseFile);
            }

            $output = $this->execute($command, $data, $temporaryGnupgHome);
        } finally {
            if (null !== $passphraseFile) {
                @unlink($passphraseFile);
            }
            $this->removeDirectory($temporaryGnupgHome);
        }

        return $output;
    }

    /**
     * @param array<string, string> $pgpKeys          The public keys to encrypt the data with. The array keys are the user email addresses and the values are the file paths to the public keys; each file must hold exactly one key, with a user ID for its address.
     * @param string[]              $hiddenRecipients The email addresses whose key ID must be hidden (gpg --hidden-recipient-file) instead of disclosed (gpg --recipient-file)
     */
    public function encrypt(string $data, array $pgpKeys, array $hiddenRecipients = []): string
    {
        $temporaryGnupgHome = $this->createTemporaryGnupgHome();

        try {
            foreach ($pgpKeys as $recipient => $filePath) {
                $this->checkRecipientKey($recipient, $filePath, $temporaryGnupgHome);
            }

            $command = [
                '--auto-key-locate',
                'local',
                '--encrypt',
                '--cipher-algo',
                $this->cipherAlgorithm,
                '--armor',
            ];

            $hiddenKeyFiles = [];
            $visibleKeyFiles = [];
            foreach ($pgpKeys as $recipient => $filePath) {
                if (\in_array($recipient, $hiddenRecipients, true)) {
                    $hiddenKeyFiles[] = $filePath;
                } else {
                    $visibleKeyFiles[] = $filePath;
                }
            }

            // gpg keeps the last flag given for a key, so a key shared with a visible recipient stays visible instead of revealing a hidden one.
            foreach ($hiddenKeyFiles as $filePath) {
                $command[] = '--hidden-recipient-file';
                $command[] = $this->convertToMsysPath($filePath);
            }
            foreach ($visibleKeyFiles as $filePath) {
                $command[] = '--recipient-file';
                $command[] = $this->convertToMsysPath($filePath);
            }

            $output = $this->execute($command, $data, $temporaryGnupgHome);
        } finally {
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
        $process = new Process($command, null, ['GNUPGHOME' => $this->convertToMsysPath($gnupgHome)]);
        $process->setTimeout($this->timeout);

        if ($input) {
            $process->setInput($input);
        }

        try {
            $process->mustRun();

            return $process->getOutput();
        } catch (ProcessRuntimeException $e) {
            throw new RuntimeException($e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * gpg only uses the first key of a --recipient-file, so a file with several keys, or one keyed to another address, would silently encrypt to the wrong key.
     */
    private function checkRecipientKey(string $recipient, string $filePath, string $gnupgHome): void
    {
        $output = $this->execute(['--with-colons', '--import-options', 'show-only', '--import', '--', $this->convertToMsysPath($filePath)], null, $gnupgHome);

        $keyCount = 0;
        $hasRecipientUserId = false;
        foreach (explode("\n", $output) as $line) {
            $fields = explode(':', $line);
            if ('pub' === $fields[0]) {
                ++$keyCount;
            } elseif ('uid' === $fields[0] && 'r' !== ($fields[1] ?? '') && 0 === strcasecmp($recipient, $this->getUserIdMailbox($fields[9] ?? ''))) {
                $hasRecipientUserId = true;
            }
        }

        if (1 !== $keyCount) {
            throw new RuntimeException(\sprintf('The PGP public key file "%s" must hold exactly one key, %d found.', $filePath, $keyCount));
        }

        if (!$hasRecipientUserId) {
            throw new RuntimeException(\sprintf('The PGP public key in "%s" has no user ID for "%s".', $filePath, $recipient));
        }
    }

    private function getUserIdMailbox(string $userId): string
    {
        $userId = preg_replace_callback('/\\\\x([0-9a-fA-F]{2})/', static fn (array $matches) => \chr(hexdec($matches[1])), $userId);

        return preg_match('/<([^>]*)>/', $userId, $matches) ? $matches[1] : $userId;
    }

    private function storeKey(string $filePath, ?string $passphraseFile, string $gnupgHome): void
    {
        $command = [];

        if (null !== $passphraseFile) {
            $command[] = '--pinentry-mode';
            $command[] = 'loopback';
            $command[] = '--passphrase-file';
            $command[] = $this->convertToMsysPath($passphraseFile);
        }

        $command[] = '--import';
        $command[] = '--';
        $command[] = $this->convertToMsysPath($filePath);

        $this->execute($command, null, $gnupgHome);
    }

    private function writePassphraseFile(#[\SensitiveParameter] string $passphrase): string
    {
        $passphraseFile = tempnam(sys_get_temp_dir(), $this->tempPrefix.'_pass_');
        if (false === $passphraseFile) {
            throw new RuntimeException('Unable to create temporary passphrase file.');
        }

        chmod($passphraseFile, 0o600);
        file_put_contents($passphraseFile, $passphrase);

        return $passphraseFile;
    }

    private function createTemporaryGnupgHome(): string
    {
        $temporaryDir = sys_get_temp_dir().\DIRECTORY_SEPARATOR.$this->tempPrefix.bin2hex(random_bytes(8));

        if (!mkdir($temporaryDir, 0o700)) {
            throw new RuntimeException('Unable to create temporary GNUPGHOME directory.');
        }

        return $temporaryDir;
    }

    private function convertToMsysPath(string $path): string
    {
        if ('\\' === \DIRECTORY_SEPARATOR && preg_match('/^([a-zA-Z]):/', $path, $matches)) {
            return '/'.strtolower($matches[1]).str_replace('\\', '/', substr($path, 2));
        }

        return $path;
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
}
