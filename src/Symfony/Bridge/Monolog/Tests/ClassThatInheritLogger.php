<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Monolog\Tests;

use Symfony\Bridge\Monolog\Logger;
use Symfony\Component\HttpFoundation\Request;

class ClassThatInheritLogger extends Logger
{
    public function getLogs(Request $request = null): array
    {
        return parent::getLogs($request);
    }

    public function countErrors(Request $request = null): int
    {
        return parent::countErrors($request);
    }
}
