<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Dumper\ClosureDumper;

use Jeremeamia\SuperClosure\ClosureParser;
use Symfony\Component\DependencyInjection\Exception\DumpingClosureException;

final class SuperClosureDumper implements ClosureDumperInterface
{
    /**
     * {@inheritdoc}
     */
    public function dump(\Closure $closure)
    {
        $reflection = new \ReflectionFunction($closure);
        $closureParser = new ClosureParser($reflection);

        try {
            $closureCode = $closureParser->getCode();
        } catch (\InvalidArgumentException $e) {
            throw new DumpingClosureException($closure);
        }

        // Remove ";" from the end of code
        return substr($closureCode, 0, -1);
    }
}
