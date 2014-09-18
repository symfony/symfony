<?php

namespace Symfony\Component\DependencyInjection\Exception;

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
