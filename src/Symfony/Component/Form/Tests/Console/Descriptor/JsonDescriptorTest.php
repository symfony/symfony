<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Tests\Console\Descriptor;

use Symfony\Component\Form\Console\Descriptor\JsonDescriptor;

class JsonDescriptorTest extends AbstractDescriptorTestCase
{
    protected function getDescriptor()
    {
        return new JsonDescriptor();
    }

    protected function getFormat()
    {
        return 'json';
    }
}
