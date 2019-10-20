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

/**
 * @author Nicolas Grekas <p@tchwork.com>
 *
 * @internal
 */
class DotenvVault extends AbstractVault
{
    private $dotenvFile;

    public function __construct(string $dotenvFile)
    {
        $this->dotenvFile = strtr($dotenvFile, '/', \DIRECTORY_SEPARATOR);
    }

    public function generateKeys(bool $override = false): bool
    {
        $this->lastMessage = 'The dotenv vault doesn\'t encrypt secrets thus doesn\'t need keys.';

        return false;
    }

    public function seal(string $name, string $value): void
    {
        $this->lastMessage = null;
        $this->validateName($name);
        $k = $name.'_SECRET';
        $v = str_replace("'", "'\\''", $value);

        $content = file_exists($this->dotenvFile) ? file_get_contents($this->dotenvFile) : '';
        $content = preg_replace("/^$k=((\\\\'|'[^']++')++|.*)/m", "$k='$v'", $content, -1, $count);

        if (!$count) {
            $content .= "$k='$v'\n";
        }

        file_put_contents($this->dotenvFile, $content);

        $this->lastMessage = sprintf('Secret "%s" %s in "%s".', $name, $count ? 'added' : 'updated', $this->getPrettyPath($this->dotenvFile));
    }

    public function reveal(string $name): ?string
    {
        $this->lastMessage = null;
        $this->validateName($name);
        $k = $name.'_SECRET';
        $v = \is_string($_SERVER[$k] ?? null) ? $_SERVER[$k] : ($_ENV[$k] ?? null);

        if (null === $v) {
            $this->lastMessage = sprintf('Secret "%s" not found in "%s".', $name, $this->getPrettyPath($this->dotenvFile));

            return null;
        }

        return $v;
    }

    public function remove(string $name): bool
    {
        $this->lastMessage = null;
        $this->validateName($name);
        $k = $name.'_SECRET';

        $content = file_exists($this->dotenvFile) ? file_get_contents($this->dotenvFile) : '';
        $content = preg_replace("/^$k=((\\\\'|'[^']++')++|.*)\n?/m", '', $content, -1, $count);

        if ($count) {
            file_put_contents($this->dotenvFile, $content);
            $this->lastMessage = sprintf('Secret "%s" removed from file "%s".', $name, $this->getPrettyPath($this->dotenvFile));

            return true;
        }

        $this->lastMessage = sprintf('Secret "%s" not found in "%s".', $name, $this->getPrettyPath($this->dotenvFile));

        return false;
    }

    public function list(bool $reveal = false): array
    {
        $this->lastMessage = null;
        $secrets = [];

        foreach ($_ENV as $k => $v) {
            if (preg_match('/^(\w+)_SECRET$/D', $k, $m)) {
                $secrets[$m[1]] = $reveal ? $v : null;
            }
        }

        foreach ($_SERVER as $k => $v) {
            if (\is_string($v) && preg_match('/^(\w+)_SECRET$/D', $k, $m)) {
                $secrets[$m[1]] = $reveal ? $v : null;
            }
        }

        return $secrets;
    }
}
