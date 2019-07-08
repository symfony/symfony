<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Console\Helper;

use Symfony\Component\Console\Helper\DescriptorHelper as BaseDescriptorHelper;
use Symfony\Component\Form\Console\Descriptor\JsonDescriptor;
use Symfony\Component\Form\Console\Descriptor\TextDescriptor;
use Symfony\Component\HttpKernel\Debug\FileLinkFormatter;

/**
 * @author Yonel Ceruto <yonelceruto@gmail.com>
 *
 * @internal
 */
class DescriptorHelper extends BaseDescriptorHelper
{
    public function __construct(FileLinkFormatter $fileLinkFormatter = null)
    {
        $this
            ->register('txt', new TextDescriptor($fileLinkFormatter))
            ->register('json', new JsonDescriptor())
        ;
    }
}
