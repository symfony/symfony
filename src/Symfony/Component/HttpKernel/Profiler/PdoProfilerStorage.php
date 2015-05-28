<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Profiler;

use Symfony\Component\Profiler\Storage\AbstractPdoProfilerStorage;

/**
 * Base PDO storage for profiling information in a PDO database.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Jan Schumann <js@schumann-it.com>
 * @deprecated since x.x, to be removed in x.x. Use Symfony\Component\Profiler\Storage\PdoProfilerStorage instead.
 */
abstract class PdoProfilerStorage extends AbstractPdoProfilerStorage
{

}
