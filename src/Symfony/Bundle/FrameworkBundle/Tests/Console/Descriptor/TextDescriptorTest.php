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

use Symfony\Bundle\FrameworkBundle\Console\Descriptor\TextDescriptor;

class TextDescriptorTest extends AbstractDescriptorTest
{
    protected function setUp()
    {
        putenv('COLUMNS=121');
    }

    protected function tearDown()
    {
        putenv('COLUMNS');
    }

    protected function getDescriptor()
    {
        return new TextDescriptor();
    }

    protected function getFormat()
    {
        return 'txt';
    }
}
