<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Runtime\ResolvedApp\Symfony;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Runtime\ResolvedAppInterface;

/**
 * @author Nicolas Grekas <p@tchwork.com>
 */
class CommandResolved implements ResolvedAppInterface
{
    private $command;
    private $resolvedApp;

    public function __construct(Command $command, ResolvedAppInterface $resolvedApp)
    {
        $this->command = $command;
        $this->resolvedApp = $resolvedApp;
    }

    public function __invoke(): object
    {
        if (!($app = ($this->resolvedApp)()) instanceof \Closure) {
            return $app;
        }

        $parameters = (new \ReflectionFunction($app))->getParameters();
        $types = [];

        foreach ($parameters as $parameter) {
            $type = $parameter->getType();
            $types[] = $type instanceof \ReflectionNamedType ? $type->getName() : null;
        }

        if ([InputInterface::class, OutputInterface::class] === $types) {
            return $this->command->setCode($app);
        }

        return $app;
    }
}
