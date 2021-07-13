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
use Symfony\Component\VarExporter\VarExporter;

/**
 * @author Tobias Schultze <http://tobion.de>
 * @author Jérémy Derussé <jeremy@derusse.com>
 * @author Nicolas Grekas <p@tchwork.com>
 *
 * @internal
 */
class SodiumVault extends AbstractVault implements EnvVarLoaderInterface
{
    private $encryptionKey;
    private $decryptionKey;
    private $pathPrefix;
    private $secretsDir;

    /**
     * @param string|\Stringable|null $decryptionKey A string or a stringable object that defines the private key to use to decrypt the vault
     *                                               or null to store generated keys in the provided $secretsDir
     */
    public function __construct(string $secretsDir, $decryptionKey = null)
    {
        if (null !== $decryptionKey && !\is_string($decryptionKey) && !(\is_object($decryptionKey) && method_exists($decryptionKey, '__toString'))) {
            throw new \TypeError(sprintf('Decryption key should be a string or an object that implements the __toString() method, "%s" given.', \gettype($decryptionKey)));
        }

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
        } catch (\RuntimeException $e) {
            // ignore failures to load keys
        }

        if ('' !== $this->decryptionKey && !file_exists($this->pathPrefix.'encrypt.public.php')) {
            $this->export('encrypt.public', $this->encryptionKey);
        }

        if (!$override && null !== $this->encryptionKey) {
            $this->lastMessage = sprintf('Sodium keys already exist at "%s*.{public,private}" and won\'t be overridden.', $this->getPrettyPath($this->pathPrefix));

            return false;
        }

        $this->decryptionKey = sodium_crypto_box_keypair();
        $this->encryptionKey = sodium_crypto_box_publickey($this->decryptionKey);

        $this->export('encrypt.public', $this->encryptionKey);
        $this->export('decrypt.private', $this->decryptionKey);

        $this->lastMessage = sprintf('Sodium keys have been generated at "%s*.public/private.php".', $this->getPrettyPath($this->pathPrefix));

        return true;
    }

    public function seal(string $name, string $value): void
    {
        $this->lastMessage = null;
        $this->validateName($name);
        $this->loadKeys();
        $this->export($name.'.'.substr(md5($name), 0, 6), sodium_crypto_box_seal($value, $this->encryptionKey ?? sodium_crypto_box_publickey($this->decryptionKey)));

        $list = $this->list();
        $list[$name] = null;
        uksort($list, 'strnatcmp');
        file_put_contents($this->pathPrefix.'list.php', sprintf("<?php\n\nreturn %s;\n", VarExporter::export($list)), \LOCK_EX);

        $this->lastMessage = sprintf('Secret "%s" encrypted in "%s"; you can commit it.', $name, $this->getPrettyPath(\dirname($this->pathPrefix).\DIRECTORY_SEPARATOR));
    }

    public function reveal(string $name): ?string
    {
        $this->lastMessage = null;
        $this->validateName($name);

        if (!file_exists($file = $this->pathPrefix.$name.'.'.substr_replace(md5($name), '.php', -26))) {
            $this->lastMessage = sprintf('Secret "%s" not found in "%s".', $name, $this->getPrettyPath(\dirname($this->pathPrefix).\DIRECTORY_SEPARATOR));

            return null;
        }

        if (!\function_exists('sodium_crypto_box_seal')) {
            $this->lastMessage = sprintf('Secret "%s" cannot be revealed as the "sodium" PHP extension missing. Try running "composer require paragonie/sodium_compat" if you cannot enable the extension."', $name);

            return null;
        }

        $this->loadKeys();

        if ('' === $this->decryptionKey) {
            $this->lastMessage = sprintf('Secret "%s" cannot be revealed as no decryption key was found in "%s".', $name, $this->getPrettyPath(\dirname($this->pathPrefix).\DIRECTORY_SEPARATOR));

            return null;
        }

        if (false === $value = sodium_crypto_box_seal_open(include $file, $this->decryptionKey)) {
            $this->lastMessage = sprintf('Secret "%s" cannot be revealed as the wrong decryption key was provided for "%s".', $name, $this->getPrettyPath(\dirname($this->pathPrefix).\DIRECTORY_SEPARATOR));

            return null;
        }

        return $value;
    }

    public function remove(string $name): bool
    {
        $this->lastMessage = null;
        $this->validateName($name);

        if (!file_exists($file = $this->pathPrefix.$name.'.'.substr_replace(md5($name), '.php', -26))) {
            $this->lastMessage = sprintf('Secret "%s" not found in "%s".', $name, $this->getPrettyPath(\dirname($this->pathPrefix).\DIRECTORY_SEPARATOR));

            return false;
        }

        $list = $this->list();
        unset($list[$name]);
        file_put_contents($this->pathPrefix.'list.php', sprintf("<?php\n\nreturn %s;\n", VarExporter::export($list)), \LOCK_EX);

        $this->lastMessage = sprintf('Secret "%s" removed from "%s".', $name, $this->getPrettyPath(\dirname($this->pathPrefix).\DIRECTORY_SEPARATOR));

        return @unlink($file) || !file_exists($file);
    }

    public function list(bool $reveal = false): array
    {
        $this->lastMessage = null;

        if (!file_exists($file = $this->pathPrefix.'list.php')) {
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
        return $this->list(true);
    }

    private function loadKeys(): void
    {
        if (!\function_exists('sodium_crypto_box_seal')) {
            throw new \LogicException('The "sodium" PHP extension is required to deal with secrets. Alternatively, try running "composer require paragonie/sodium_compat" if you cannot enable the extension.".');
        }

        if (null !== $this->encryptionKey || '' !== $this->decryptionKey = (string) $this->decryptionKey) {
            return;
        }

        if (file_exists($this->pathPrefix.'decrypt.private.php')) {
            $this->decryptionKey = (string) include $this->pathPrefix.'decrypt.private.php';
        }

        if (file_exists($this->pathPrefix.'encrypt.public.php')) {
            $this->encryptionKey = (string) include $this->pathPrefix.'encrypt.public.php';
        } elseif ('' !== $this->decryptionKey) {
            $this->encryptionKey = sodium_crypto_box_publickey($this->decryptionKey);
        } else {
            throw new \RuntimeException(sprintf('Encryption key not found in "%s".', \dirname($this->pathPrefix)));
        }
    }

    private function export(string $file, string $data): void
    {
        $name = basename($this->pathPrefix.$file);
        $data = str_replace('%', '\x', rawurlencode($data));
        $data = sprintf("<?php // %s on %s\n\nreturn \"%s\";\n", $name, date('r'), $data);

        $this->createSecretsDir();

        if (false === file_put_contents($this->pathPrefix.$file.'.php', $data, \LOCK_EX)) {
            $e = error_get_last();
            throw new \ErrorException($e['message'] ?? 'Failed to write secrets data.', 0, $e['type'] ?? \E_USER_WARNING);
        }
    }

    private function createSecretsDir(): void
    {
        if ($this->secretsDir && !is_dir($this->secretsDir) && !@mkdir($this->secretsDir, 0777, true) && !is_dir($this->secretsDir)) {
            throw new \RuntimeException(sprintf('Unable to create the secrets directory (%s).', $this->secretsDir));
        }

        $this->secretsDir = null;
    }
}
