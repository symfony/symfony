<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Symfony\Component\Dsn\Configuration;

/**
 * A function with one or more arguments. The default function is called "dsn".
 * Other function may be "failover" or "roundrobin".
 *
 * Examples:
 * - failover(redis://localhost memcached://example.com)
 * - dsn(amqp://guest:password@localhost:1234)
 * - foobar(amqp://guest:password@localhost:1234 amqp://localhost)?delay=10
 *
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 */
class DsnFunction
{
    /**
     * @var string
     */
    private $name;

    /**
     * @var array
     */
    private $arguments;

    /**
     * @var array
     */
    private $parameters;

    public function __construct(string $name, array $arguments, array $parameters = [])
    {
        $this->name = $name;
        $this->arguments = $arguments;
        $this->parameters = $parameters;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getArguments(): array
    {
        return $this->arguments;
    }

    public function getParameters(): array
    {
        return $this->parameters;
    }

    public function getParameter(string $key, $default = null)
    {
        return \array_key_exists($key, $this->parameters) ? $this->parameters[$key] : $default;
    }

    /**
     * @return DsnFunction|Dsn
     */
    public function first()
    {
        return reset($this->arguments);
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return sprintf('%s(%s)%s', $this->getName(), implode(' ', $this->getArguments()), empty($this->parameters) ? '' : '?'.http_build_query($this->parameters));
    }
}
