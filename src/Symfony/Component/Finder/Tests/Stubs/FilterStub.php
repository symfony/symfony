<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Finder\Tests\Stubs;

class FilterStub
{
    public function filter(\SplFileInfo $file)
    {
        return preg_match('/test/', $file) > 0;
    }
}
