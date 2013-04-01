<?php

namespace Symfony\Component\Console\Tests\Descriptor\Json;

use Symfony\Component\Console\Descriptor\Json\InputArgumentJsonDescriptor;
use Symfony\Component\Console\Tests\Descriptor\AbstractDescriptorTest;
use Symfony\Component\Console\Tests\Descriptor\ObjectsProvider;

class InputArgumentJsonDescriptorTest extends AbstractDescriptorTest
{
    protected function getDescriptor()
    {
        return new InputArgumentJsonDescriptor();
    }

    protected function getObjects()
    {
        return ObjectsProvider::getInputArguments();
    }
}
