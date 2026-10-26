<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Secrets;

use Symfony\Component\DependencyInjection\EnvVarLoaderInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\String\LazyString;
use Symfony\Component\VarExporter\VarExporter;

/**
 * @author Tobias Schultze <http://tobion.de>
 * @author Jérémy Derussé <jeremy@derusse.com>
 * @author Nicolas Grekas <p@tchwork.com>
 */
class SodiumVault extends AbstractVault implements EnvVarLoaderInterface
{
    private ?string $encryptionKey = null;
    private string|\Stringable|null $decryptionKey = null;
    private string $pathPrefix;
    private ?string $secretsDir;

    /**
     * @param $decryptionKey A string or a stringable object that defines the private key to use to decrypt the vault
     *                       or null to store generated keys in the provided $secretsDir
     */
    public function __construct(string $secretsDir, #[\SensitiveParameter] string|\Stringable|null $decryptionKey = null)
    {
        $this->pathPrefix = rtrim(strtr($secretsDir, '/', \DIRECTORY_SEPARATOR), \DIRECTORY_SEPARATOR).\DIRECTORY_SEPARATOR.basename($secretsDir).'.';
        $this->decryptionKey = $decryptionKey;
        $this->secretsDir = $secretsDir;
    }

    public function generateKeys(bool $override = false): bool
    {
        $this->lastMessage = null;

        if (null === $this->encryptionKey && '' !== $this->decryptionKey = (string) $this->decryptionKey) {
            $this->lastMessage = 'Cannot generate keys when a decryption key has been provided while instantiating the vault.';

            return false;
        }

        try {
            $this->loadKeys();
        } catch (\RuntimeException) {
            // ignore failures to load keys
        }

        if ('' !== $this->decryptionKey && !is_file($this->pathPrefix.'encrypt.public.php')) {
            $this->export('encrypt.public', $this->encryptionKey);
        }

        if (!$override && null !== $this->encryptionKey) {
            $this->lastMessage = \sprintf('Sodium keys already exist at "%s*.{public,private}" and won\'t be overridden.', $this->getPrettyPath($this->pathPrefix));

            return false;
        }

        $this->decryptionKey = sodium_crypto_box_keypair();
        $this->encryptionKey = sodium_crypto_box_publickey($this->decryptionKey);

        $this->exportKeys();

        $this->lastMessage = \sprintf('Sodium keys have been generated at "%s*.public/private.php".', $this->getPrettyPath($this->pathPrefix));

        return true;
    }

    /**
     * Seals the given secrets with a new pair of keys.
     *
     * The previous private key is kept in a side file until every secret is sealed with the new public key, so that secrets stay readable and the rotation can be resumed if it is interrupted.
     *
     * @param array<string, string> $secrets Plain values keyed by secret name
     *
     * @internal
     */
    public function rotate(#[\SensitiveParameter] array $secrets): bool
    {
        $this->lastMessage = null;

        if (null === $this->encryptionKey && '' !== $this->decryptionKey = (string) $this->decryptionKey) {
            $this->lastMessage = 'Cannot generate keys when a decryption key has been provided while instantiating the vault.';

            return false;
        }

        $plaintexts = [];
        foreach ($this->list() as $name => $value) {
            if (!\is_string($plaintexts[$name] = $secrets[$name] ?? null)) {
                $this->lastMessage = \sprintf('Cannot rotate secrets because "%s" could not be decrypted.', $name);

                return false;
            }
        }

        try {
            $this->loadKeys();
        } catch (\RuntimeException) {
            // ignore failures to load keys
        }

        $oldKeyFile = $this->pathPrefix.'decrypt.private.old.php';
        $currentKey = \SODIUM_CRYPTO_BOX_KEYPAIRBYTES === \strlen($this->decryptionKey) ? $this->decryptionKey : null;

        $newKey = sodium_crypto_box_keypair();

        if (null === $oldKey = $this->loadOldKey()) {
            if (null !== $currentKey && $plaintexts) {
                if (false !== $mode = @fileperms($this->pathPrefix.'decrypt.private.php')) {
                    @touch($oldKeyFile);
                    @chmod($oldKeyFile, $mode & 0o777);
                }

                $this->export('decrypt.private.old', $currentKey);
            }
        } elseif (null !== $currentKey && $currentKey !== $oldKey) {
            // The interrupted rotation already wrote its new key and might have sealed some secrets with it
            $newKey = $currentKey;
        }

        $this->decryptionKey = $newKey;
        $this->encryptionKey = sodium_crypto_box_publickey($newKey);

        if ($newKey !== $currentKey) {
            $this->exportKeys();
        } else {
            $this->export('encrypt.public', $this->encryptionKey);
        }

        foreach ($plaintexts as $name => $value) {
            $this->seal($name, $value);
        }

        if (is_file($oldKeyFile) && !@unlink($oldKeyFile)) {
            throw new \RuntimeException(\sprintf('Unable to remove the previous decryption key "%s".', $oldKeyFile));
        }

        $this->lastMessage = \sprintf('Sodium keys have been generated at "%s*.public/private.php".', $this->getPrettyPath($this->pathPrefix));

        return true;
    }

    public function seal(string $name, #[\SensitiveParameter] string $value): void
    {
        $this->lastMessage = null;
        $this->validateName($name);
        $this->loadKeys();
        $filename = $this->getFilename($name);
        $this->export($filename, sodium_crypto_box_seal($value, $this->encryptionKey ?? sodium_crypto_box_publickey($this->decryptionKey)), true);

        $list = $this->list();

        if (!\array_key_exists($name, $list)) {
            $list[$name] = null;
            uksort($list, 'strnatcmp');
            $this->exportList($list);
        }

        $this->lastMessage = \sprintf('Secret "%s" encrypted in "%s"; you can commit it.', $name, $this->getPrettyPath(\dirname($this->pathPrefix).\DIRECTORY_SEPARATOR));
    }

    public function reveal(string $name): ?string
    {
        $this->lastMessage = null;
        $this->validateName($name);

        $filename = $this->getFilename($name);
        if (!is_file($file = $this->pathPrefix.$filename.'.php')) {
            $this->lastMessage = \sprintf('Secret "%s" not found in "%s".', $name, $this->getPrettyPath(\dirname($this->pathPrefix).\DIRECTORY_SEPARATOR));

            return null;
        }

        if (!\function_exists('sodium_crypto_box_seal')) {
            $this->lastMessage = \sprintf('Secret "%s" cannot be revealed as the "sodium" PHP extension missing. Try running "composer require paragonie/sodium_compat" if you cannot enable the extension."', $name);

            return null;
        }

        $this->loadKeys();

        if ('' === $this->decryptionKey) {
            $this->lastMessage = \sprintf('Secret "%s" cannot be revealed as no decryption key was found in "%s".', $name, $this->getPrettyPath(\dirname($this->pathPrefix).\DIRECTORY_SEPARATOR));

            return null;
        }

        $sealed = include $file;

        if (false === ($value = sodium_crypto_box_seal_open($sealed, $this->decryptionKey)) && null !== $oldKey = $this->loadOldKey()) {
            // A rotation was interrupted before every secret was sealed with the new key
            $value = sodium_crypto_box_seal_open($sealed, $oldKey);
        }

        if (false === $value) {
            $this->lastMessage = \sprintf('Secret "%s" cannot be revealed as the wrong decryption key was provided for "%s".', $name, $this->getPrettyPath(\dirname($this->pathPrefix).\DIRECTORY_SEPARATOR));

            return null;
        }

        return $value;
    }

    public function remove(string $name): bool
    {
        $this->lastMessage = null;
        $this->validateName($name);

        $filename = $this->getFilename($name);
        if (!is_file($file = $this->pathPrefix.$filename.'.php')) {
            $this->lastMessage = \sprintf('Secret "%s" not found in "%s".', $name, $this->getPrettyPath(\dirname($this->pathPrefix).\DIRECTORY_SEPARATOR));

            return false;
        }

        $list = $this->list();
        unset($list[$name]);
        $this->exportList($list);

        $this->lastMessage = \sprintf('Secret "%s" removed from "%s".', $name, $this->getPrettyPath(\dirname($this->pathPrefix).\DIRECTORY_SEPARATOR));

        return @unlink($file) || !file_exists($file);
    }

    public function list(bool $reveal = false): array
    {
        $this->lastMessage = null;

        if (!is_file($file = $this->pathPrefix.'list.php')) {
            return [];
        }

        $secrets = include $file;

        if (!$reveal) {
            return $secrets;
        }

        foreach ($secrets as $name => $value) {
            $secrets[$name] = $this->reveal($name);
        }

        return $secrets;
    }

    public function loadEnvVars(): array
    {
        $envs = [];
        $reveal = $this->reveal(...);

        foreach ($this->list() as $name => $value) {
            $envs[$name] = LazyString::fromCallable($reveal, $name);
        }

        return $envs;
    }

    private function loadKeys(): void
    {
        if (!\function_exists('sodium_crypto_box_seal')) {
            throw new \LogicException('The "sodium" PHP extension is required to deal with secrets. Alternatively, try running "composer require paragonie/sodium_compat" if you cannot enable the extension.".');
        }

        if (null !== $this->encryptionKey || '' !== $this->decryptionKey = (string) $this->decryptionKey) {
            return;
        }

        if (is_file($this->pathPrefix.'decrypt.private.php')) {
            $this->decryptionKey = (string) include $this->pathPrefix.'decrypt.private.php';
        }

        if (\SODIUM_CRYPTO_BOX_KEYPAIRBYTES !== \strlen($this->decryptionKey) && null !== $oldKey = $this->loadOldKey()) {
            // A rotation was interrupted while writing the new private key
            $this->decryptionKey = $oldKey;
        }

        if (is_file($this->pathPrefix.'encrypt.public.php')) {
            $this->encryptionKey = (string) include $this->pathPrefix.'encrypt.public.php';
        } elseif ('' !== $this->decryptionKey) {
            $this->encryptionKey = sodium_crypto_box_publickey($this->decryptionKey);
        } else {
            throw new \RuntimeException(\sprintf('Encryption key not found in "%s".', \dirname($this->pathPrefix)));
        }
    }

    private function export(string $filename, string $data, bool $atomic = false): void
    {
        $b64 = 'decrypt.private' === $filename ? '// SYMFONY_DECRYPTION_SECRET='.base64_encode($data)."\n" : '';
        $name = basename($this->pathPrefix.$filename);
        $data = str_replace('%', '\x', rawurlencode($data));
        $data = \sprintf("<?php // %s on %s\n\n%sreturn \"%s\";\n", $name, date('r'), $b64, $data);

        $this->createSecretsDir();

        if ($atomic) {
            // A secret file truncated by an interruption could not be recovered
            (new Filesystem())->dumpFile($this->pathPrefix.$filename.'.php', $data);
        } elseif (false === file_put_contents($this->pathPrefix.$filename.'.php', $data, \LOCK_EX)) {
            $e = error_get_last();
            throw new \ErrorException($e['message'] ?? 'Failed to write secrets data.', 0, $e['type'] ?? \E_USER_WARNING);
        }
    }

    private function exportKeys(): void
    {
        // Until the public key is written again after the private key, loadKeys() derives it from the private key, so that the two always match
        @unlink($this->pathPrefix.'encrypt.public.php');
        $this->export('decrypt.private', $this->decryptionKey);
        $this->export('encrypt.public', $this->encryptionKey);
    }

    private function exportList(array $list): void
    {
        // An interrupted write never leaves a truncated list behind
        (new Filesystem())->dumpFile($this->pathPrefix.'list.php', \sprintf("<?php\n\nreturn %s;\n", VarExporter::export($list)));
    }

    private function loadOldKey(): ?string
    {
        if (!is_file($file = $this->pathPrefix.'decrypt.private.old.php')) {
            return null;
        }

        $key = include $file;

        return \is_string($key) && \SODIUM_CRYPTO_BOX_KEYPAIRBYTES === \strlen($key) ? $key : null;
    }

    private function createSecretsDir(): void
    {
        if ($this->secretsDir && !is_dir($this->secretsDir) && !@mkdir($this->secretsDir, 0o777, true) && !is_dir($this->secretsDir)) {
            throw new \RuntimeException(\sprintf('Unable to create the secrets directory (%s).', $this->secretsDir));
        }

        $this->secretsDir = null;
    }

    private function getFilename(string $name): string
    {
        // The MD5 hash allows making secrets case-sensitive. The filename is not enough on Windows.
        return $name.'.'.substr(md5($name), 0, 6);
    }
}
