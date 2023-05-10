<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Doctrine\Middleware\Debug;

use Doctrine\DBAL\ParameterType;

/**
 * @author Laurent VOULLEMIER <laurent.voullemier@gmail.com>
 *
 * @internal
 */
class Query
{
    private $params = [];
    private $types = [];

    private $start;
    private $duration;

    private $sql;

    public function __construct(string $sql)
    {
        $this->sql = $sql;
    }

    public function start(): void
    {
        $this->start = microtime(true);
    }

    public function stop(): void
    {
        if (null !== $this->start) {
            $this->duration = microtime(true) - $this->start;
        }
    }

    /**
     * @param string|int $param
     * @param mixed      $variable
     */
    public function setParam($param, &$variable, int $type): void
    {
        // Numeric indexes start at 0 in profiler
        $idx = \is_int($param) ? $param - 1 : $param;

        $this->params[$idx] = &$variable;
        $this->types[$idx] = $type;
    }

    /**
     * @param string|int $param
     * @param mixed      $value
     */
    public function setValue($param, $value, int $type): void
    {
        // Numeric indexes start at 0 in profiler
        $idx = \is_int($param) ? $param - 1 : $param;

        $this->params[$idx] = $value;
        $this->types[$idx] = $type;
    }

    /**
     * @param array<string|int, string|int|float> $values
     */
    public function setValues(array $values): void
    {
        foreach ($values as $param => $value) {
            $this->setValue($param, $value, ParameterType::STRING);
        }
    }

    public function getSql(): string
    {
        return $this->sql;
    }

    /**
     * @return array<int, string|int|float}>
     */
    public function getParams(): array
    {
        return $this->params;
    }

    /**
     * @return array<int, int>
     */
    public function getTypes(): array
    {
        return $this->types;
    }

    /**
     * Query duration in seconds.
     */
    public function getDuration(): ?float
    {
        return $this->duration;
    }

    public function __clone()
    {
        $copy = [];
        foreach ($this->params as $param => $valueOrVariable) {
            $copy[$param] = $valueOrVariable;
        }
        $this->params = $copy;
    }
}
