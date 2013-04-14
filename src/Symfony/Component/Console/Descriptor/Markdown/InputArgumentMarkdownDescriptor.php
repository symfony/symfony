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

use Symfony\Component\Console\Input\InputArgument;

/**
 * @author Jean-François Simon <contact@jfsimon.fr>
 */
class InputArgumentMarkdownDescriptor extends AbstractMarkdownDescriptor
{
    /**
     * {@inheritdoc}
     */
    public function describe($object)
    {
        /** @var InputArgument $object */
        return '**'.$object->getName().':**'."\n\n"
            .'* Name: '.($object->getName() ?: '<none>')."\n"
            .'* Is required: '.($object->isRequired() ? 'yes' : 'no')."\n"
            .'* Is array: '.($object->isArray() ? 'yes' : 'no')."\n"
            .'* Description: '.($object->getDescription() ?: '<none>')."\n"
            .'* Default: `'.str_replace("\n", '', var_export($object->getDefault(), true)).'`';
    }

    /**
     * {@inheritdoc}
     */
    public function supports($object)
    {
        return $object instanceof InputArgument;
    }
}
