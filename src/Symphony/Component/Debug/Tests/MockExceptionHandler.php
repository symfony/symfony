<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\Debug\Tests;

use Symphony\Component\Debug\ExceptionHandler;

class MockExceptionHandler extends ExceptionHandler
{
    public $e;

    public function handle(\Exception $e)
    {
        $this->e = $e;
    }
}
