<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Serializer\Exception;

final class AggregatedException extends RuntimeException implements AggregableExceptionInterface
{
    /**
     * This property is public for performance reasons to reduce the number of hot path function calls.
     *
     * @var bool
     *
     * @internal
     */
    public $isEmpty = true;

    /**
     * @var array<string, AggregableExceptionInterface>
     */
    private $collection;

    public function __construct()
    {
        parent::__construct('Errors occurred during the denormalization process');
    }

    public function put(string $param, AggregableExceptionInterface $exception): self
    {
        $this->isEmpty = false;
        $this->collection[$param] = $exception;

        return $this;
    }

    /**
     * @return array<string, AggregableExceptionInterface>
     */
    public function all(): array
    {
        $result = [];

        foreach ($this->collection as $param => $exception) {
            if ($exception instanceof self) {
                foreach ($exception->all() as $nestedParams => $nestedException) {
                    $result["$param.$nestedParams"] = $nestedException;
                }

                continue;
            }

            $result[$param] = $exception;
        }

        return $result;
    }
}
