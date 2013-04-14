<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Console\Descriptor\Text;

use Symfony\Component\Console\Input\InputDefinition;

/**
 * @author Jean-François Simon <contact@jfsimon.fr>
 */
class InputDefinitionTextDescriptor extends AbstractTextDescriptor
{
    /**
     * {@inheritdoc}
     */
    public function getRawText($object)
    {
        return strip_tags($this->getFormattedText($object));
    }

    /**
     * {@inheritdoc}
     */
    public function getFormattedText($object)
    {
        // find the largest option or argument name
        $nameWidth = 0;

        /** @var InputDefinition $object */
        foreach ($object->getOptions() as $option) {
            $nameLength = strlen($option->getName()) + 2;
            if ($option->getShortcut()) {
                $nameLength += strlen($option->getShortcut()) + 3;
            }

            $nameWidth = max($nameWidth, $nameLength);
        }

        foreach ($object->getArguments() as $argument) {
            $nameWidth = max($nameWidth, strlen($argument->getName()));
        }

        ++$nameWidth;

        $text = array();

        if ($object->getArguments()) {
            $text[] = '<comment>Arguments:</comment>';
            foreach ($object->getArguments() as $argument) {
                $text[] = $this
                    ->getDescriptor($argument)
                    ->configure(array('name_width' => $nameWidth))
                    ->getFormattedText($argument);
            }
            $text[] = '';
        }

        if ($object->getOptions()) {
            $text[] = '<comment>Options:</comment>';
            foreach ($object->getOptions() as $option) {
                $text[] = $this
                    ->getDescriptor($option)
                    ->configure(array('name_width' => $nameWidth))
                    ->getFormattedText($option);
            }
            $text[] = '';
        }

        return implode("\n", $text);
    }

    /**
     * {@inheritdoc}
     */
    public function supports($object)
    {
        return $object instanceof InputDefinition;
    }
}
