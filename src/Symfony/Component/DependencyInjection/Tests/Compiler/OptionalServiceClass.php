<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Tests\Compiler;

use Symfony\Bug\NotExistClass;

if (!function_exists('__phpunit_run_isolated_test')) {
    class OptionalServiceClass extends NotExistClass
    {
    }
}
