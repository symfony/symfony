<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Middleware;

/**
 * @author Kevin Bond <kevinbond@gmail.com>
 */
final class MessageInstanceOf implements \IteratorAggregate
{
    private $contract;

    public function __construct(string $contract)
    {
        $this->contract = $contract;
    }

    public function getIterator()
    {
        if ((new \ReflectionClass($this->contract))->isInstantiable()) {
            yield $this->contract;
        }

        foreach (\get_declared_classes() as $class) {
            $refClass = new \ReflectionClass($class);

            if ($refClass->isSubclassOf($this->contract) || ($refClass->isInterface() && $refClass->implementsInterface($this->contract))) {
                yield $class;
            }
        }
    }
}
