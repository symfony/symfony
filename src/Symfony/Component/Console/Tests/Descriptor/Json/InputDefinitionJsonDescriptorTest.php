<?php

namespace Symfony\Component\Console\Tests\Descriptor\Json;

use Symfony\Component\Console\Descriptor\Json\InputDefinitionJsonDescriptor;
use Symfony\Component\Console\Tests\Descriptor\AbstractDescriptorTest;
use Symfony\Component\Console\Tests\Descriptor\ObjectsProvider;

class InputDefinitionJsonDescriptorTest extends AbstractDescriptorTest
{
    protected function getDescriptor()
    {
        return new InputDefinitionJsonDescriptor();
    }

    protected function getObjects()
    {
        return ObjectsProvider::getInputDefinitions();
    }
}
