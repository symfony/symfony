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

use Symfony\Component\Profiler\Storage\MysqlProfilerStorage as BaseMysqlProfilerStorage;

/**
 * A ProfilerStorage for Mysql.
 *
 * @author Jan Schumann <js@schumann-it.com>
 *
 * @deprecated since 2.8, to be removed in 3.0. Use Symfony\Component\Profiler\Storage\MysqlProfilerStorage instead.
 */
class MysqlProfilerStorage extends BaseMysqlProfilerStorage
{
}
