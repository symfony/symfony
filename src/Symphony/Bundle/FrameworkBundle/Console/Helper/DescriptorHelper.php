<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Bundle\FrameworkBundle\Console\Helper;

use Symphony\Bundle\FrameworkBundle\Console\Descriptor\JsonDescriptor;
use Symphony\Bundle\FrameworkBundle\Console\Descriptor\MarkdownDescriptor;
use Symphony\Bundle\FrameworkBundle\Console\Descriptor\TextDescriptor;
use Symphony\Bundle\FrameworkBundle\Console\Descriptor\XmlDescriptor;
use Symphony\Component\Console\Helper\DescriptorHelper as BaseDescriptorHelper;

/**
 * @author Jean-François Simon <jeanfrancois.simon@sensiolabs.com>
 *
 * @internal
 */
class DescriptorHelper extends BaseDescriptorHelper
{
    public function __construct()
    {
        $this
            ->register('txt', new TextDescriptor())
            ->register('xml', new XmlDescriptor())
            ->register('json', new JsonDescriptor())
            ->register('md', new MarkdownDescriptor())
        ;
    }
}
