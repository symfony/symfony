<?php

namespace Symfony\Component\Console\Tests\Descriptor;

use Symfony\Component\Console\Descriptor\XmlDescriptor;

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
