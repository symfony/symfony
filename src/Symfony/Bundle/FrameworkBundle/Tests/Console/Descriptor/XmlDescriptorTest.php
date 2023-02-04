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

use Symfony\Bundle\FrameworkBundle\Console\Descriptor\XmlDescriptor;

class XmlDescriptorTest extends AbstractDescriptorTestCase
{
    protected static function getDescriptor()
    {
        return new XmlDescriptor();
    }

    protected static function getFormat()
    {
        return 'xml';
    }
}
