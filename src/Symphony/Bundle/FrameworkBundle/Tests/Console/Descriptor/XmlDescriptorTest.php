<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Bundle\FrameworkBundle\Tests\Console\Descriptor;

use Symphony\Bundle\FrameworkBundle\Console\Descriptor\XmlDescriptor;

class XmlDescriptorTest extends AbstractDescriptorTest
{
    protected function getDescriptor()
    {
        return new XmlDescriptor();
    }

    protected function getFormat()
    {
        return 'xml';
    }
}
