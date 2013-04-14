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

use Symfony\Component\Console\Input\InputDefinition;

/**
 * @author Jean-François Simon <contact@jfsimon.fr>
 */
class InputDefinitionMarkdownDescriptor extends AbstractMarkdownDescriptor
{
    /**
     * {@inheritdoc}
     */
    public function describe($object)
    {
        $blocks = array();
        $argumentDescriptor = new InputArgumentMarkdownDescriptor();
        $optionDescriptor = new InputOptionMarkdownDescriptor();

        /** @var InputDefinition $object */

        if (count($object->getArguments()) > 0) {
            $blocks[] = '### Arguments:';
            foreach ($object->getArguments() as $argument) {
                $blocks[] = $argumentDescriptor->describe($argument);
            }
        }

        if (count($object->getOptions()) > 0) {
            $blocks[] = '### Options:';
            foreach ($object->getOptions() as $option) {
                $blocks[] = $optionDescriptor->describe($option);
            }
        }

        return implode("\n\n", $blocks);
    }

    /**
     * {@inheritdoc}
     */
    public function supports($object)
    {
        return $object instanceof InputDefinition;
    }
}
