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

use Symfony\Component\DependencyInjection\Exception\DumpingClosureException;

/**
 * @author Nikita Konstantinov <unk91nd@gmail.com>
 *
 * @api
 */
interface ClosureDumperInterface
{
    /**
     * @param \Closure $closure
     * @return string
     *
     * @throws DumpingClosureException If closure couldn't be dumped
     */
    public function dump(\Closure $closure);
}
