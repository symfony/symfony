<?php
/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Dsn\Configuration;

/**
 * Base DSN object.
 *
 * Example:
 * - null://
 * - redis:?host[h1]&host[h2]&host[/foo:]
 *
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 */
class Dsn
{
    /**
     * @var string|null
     */
    private $scheme;

    /**
     * @var array
     */
    private $parameters = [];

    public function __construct(?string $scheme, array $parameters = [])
    {
        $this->scheme = $scheme;
        $this->parameters = $parameters;
    }

    public function getScheme(): ?string
    {
        return $this->scheme;
    }

    public function getParameters(): array
    {
        return $this->parameters;
    }

    public function getParameter(string $key, $default = null)
    {
        return \array_key_exists($key, $this->parameters) ? $this->parameters[$key] : $default;
    }

    public function getHost(): ?string
    {
        return null;
    }

    public function getPort(): ?int
    {
        return null;
    }

    public function getPath(): ?string
    {
        return null;
    }

    public function getUser(): ?string
    {
        return null;
    }

    public function getPassword(): ?string
    {
        return null;
    }
}
