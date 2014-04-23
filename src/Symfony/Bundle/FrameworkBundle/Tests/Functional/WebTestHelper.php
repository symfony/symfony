<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\Test\WebTestHelper as BaseWebTestHelper;

class WebTestHelper extends BaseWebTestHelper
{
    public static function getKernelClass()
    {
        return WebTestCase::getKernelClass();
    }

    public static function createKernel(array $options = array())
    {
        return WebTestCase::createKernel($options);
    }
}
