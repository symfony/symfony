<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Exception;

/**
 * This exception is thrown when closure is not dumpable, e.g. if closure depends on context
 *
 * @author Nikita Konstantinov <unk91nd@gmail.com>
 */
final class DumpingClosureException extends \RuntimeException implements ExceptionInterface
{
    public function __construct(\Closure $closure)
    {
        $reflection = new \ReflectionFunction($closure);

        parent::__construct(sprintf(
            'Closure defined in %s at line %d could not be dumped',
            $reflection->getFileName(),
            $reflection->getStartLine()
        ));
    }
}
