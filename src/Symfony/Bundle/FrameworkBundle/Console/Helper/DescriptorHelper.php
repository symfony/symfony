<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Console\Helper;

use Symfony\Bundle\FrameworkBundle\Console\Descriptor\JsonDescriptor;
use Symfony\Bundle\FrameworkBundle\Console\Descriptor\MarkdownDescriptor;
use Symfony\Bundle\FrameworkBundle\Console\Descriptor\TextDescriptor;
use Symfony\Bundle\FrameworkBundle\Console\Descriptor\XmlDescriptor;
use Symfony\Component\Console\Helper\DescriptorHelper as BaseDescriptorHelper;
use Symfony\Component\ErrorHandler\ErrorRenderer\FileLinkFormatter;

/**
 * @author Jean-François Simon <jeanfrancois.simon@sensiolabs.com>
 *
 * @internal
 */
class DescriptorHelper extends BaseDescriptorHelper
{
    public function __construct(?FileLinkFormatter $fileLinkFormatter = null)
    {
        $this
            ->register('txt', new TextDescriptor($fileLinkFormatter))
            ->register('xml', new XmlDescriptor())
            ->register('json', new JsonDescriptor())
            ->register('md', new MarkdownDescriptor())
        ;
    }
}
