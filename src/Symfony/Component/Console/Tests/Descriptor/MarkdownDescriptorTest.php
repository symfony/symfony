<?php

namespace Symfony\Component\Console\Tests\Descriptor;

use Symfony\Component\Console\Descriptor\MarkdownDescriptor;

class MarkdownDescriptorTest extends AbstractDescriptorTest
{
    protected function getDescriptor()
    {
        return new MarkdownDescriptor();
    }

    protected function getFormat()
    {
        return 'md';
    }
}
