<?php

namespace Symfony\Component\Console\Tests\Descriptor\Json;

use Symfony\Component\Console\Descriptor\Json\CommandJsonDescriptor;
use Symfony\Component\Console\Tests\Descriptor\AbstractDescriptorTest;
use Symfony\Component\Console\Tests\Descriptor\ObjectsProvider;

class CommandJsonDescriptorTest extends AbstractDescriptorTest
{
    protected function getDescriptor()
    {
        return new CommandJsonDescriptor();
    }

    protected function getObjects()
    {
        return ObjectsProvider::getCommands();
    }
}
