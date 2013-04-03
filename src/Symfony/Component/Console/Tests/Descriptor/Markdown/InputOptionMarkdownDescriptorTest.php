<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Console\Tests\Descriptor\Json;

use Symfony\Component\Console\Descriptor\Markdown\InputOptionMarkdownDescriptor;
use Symfony\Component\Console\Tests\Descriptor\AbstractDescriptorTest;
use Symfony\Component\Console\Tests\Descriptor\ObjectsProvider;

class InputOptionMarkdownDescriptorTest extends AbstractDescriptorTest
{
    protected function getDescriptor()
    {
        return new InputOptionMarkdownDescriptor();
    }

    protected function getObjects()
    {
        return ObjectsProvider::getInputOptions();
    }
}
