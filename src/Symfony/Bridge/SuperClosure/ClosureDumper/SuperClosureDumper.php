<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\SuperClosure\ClosureDumper;

use Jeremeamia\SuperClosure\ClosureParser;
use Symfony\Component\DependencyInjection\Dumper\ClosureDumper\ClosureDumperInterface;
use Symfony\Component\DependencyInjection\Exception\DumpingClosureException;

/**
 * @author Nikita Konstantinov <unk91nd@gmail.com>
 */
class SuperClosureDumper implements ClosureDumperInterface
{
    /**
     * {@inheritdoc}
     */
    public function dump(\Closure $closure)
    {
        $parser = ClosureParser::fromClosure($closure);

        if ($parser->getUsedVariables()) {
            throw new DumpingClosureException($closure, 'Closure must not depend on context (use statement)');
        }

        try {
            // Remove trailing ";"
            return substr($parser->getCode(), 0, -1);
        } catch (\Exception $e) {
            throw new DumpingClosureException($closure, $e->getMessage(), 0, $e);
        }
    }
}
