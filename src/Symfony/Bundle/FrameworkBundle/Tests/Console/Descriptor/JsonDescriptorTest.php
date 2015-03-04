<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests\Console\Descriptor;

use Symfony\Bundle\FrameworkBundle\Console\Descriptor\JsonDescriptor;

class JsonDescriptorTest extends AbstractDescriptorTest
{
    protected function setUp()
    {
        if (PHP_VERSION_ID < 50400) {
            $this->markTestSkipped('Test skipped on PHP 5.3 as JSON_PRETTY_PRINT does not exist.');
        }
    }

    protected function getDescriptor()
    {
        return new JsonDescriptor();
    }

    protected function getFormat()
    {
        return 'json';
    }
}
