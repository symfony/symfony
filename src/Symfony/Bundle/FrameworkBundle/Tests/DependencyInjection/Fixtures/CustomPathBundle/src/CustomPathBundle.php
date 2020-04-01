<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests\DependencyInjection\Fixtures\CustomPathBundle;

use Symfony\Component\HttpKernel\Bundle\Bundle;

class CustomPathBundle extends Bundle
{
    public function getPath()
    {
        return __DIR__.'/..';
    }
}
