<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Console\Descriptor\Markdown;

use Symfony\Component\Console\Descriptor\AbstractDescriptor;

/**
 * @author Jean-François Simon <contact@jfsimon.fr>
 */
abstract class AbstractMarkdownDescriptor extends AbstractDescriptor
{
    /**
     * {@inheritdoc}
     */
    public function configure(array $options)
    {
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getFormat()
    {
        return 'md';
    }

    /**
     * {@inheritdoc}
     */
    public function useFormatting()
    {
        return false;
    }
}
