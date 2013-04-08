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

use Symfony\Component\Console\Input\InputArgument;

/**
 * @author Jean-François Simon <contact@jfsimon.fr>
 */
class InputArgumentTextDescriptor extends AbstractTextDescriptor
{
    /**
     * @var int|null
     */
    private $nameWidth;

    /**
     * @param string|null $nameWidth
     * @param bool        $raw
     */
    public function __construct($nameWidth = null, $raw = false)
    {
        $this->nameWidth = $nameWidth;
        parent::__construct($raw);
    }

    /**
     * {@inheritdoc}
     */
    public function configure(array $options)
    {
        if (isset($options['name_width'])) {
            $this->nameWidth = $options['name_width'];
        }

        return parent::configure($options);
    }

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
        /** @var InputArgument $object */
        if (null !== $object->getDefault() && (!is_array($object->getDefault()) || count($object->getDefault()))) {
            $default = sprintf('<comment> (default: %s)</comment>', $this->formatDefaultValue($object->getDefault()));
        } else {
            $default = '';
        }

        $nameWidth = $this->nameWidth ?: strlen($object->getName());
        $description = str_replace("\n", "\n".str_repeat(' ', $nameWidth + 2), $object->getDescription());

        return sprintf(" <info>%-${nameWidth}s</info> %s%s", $object->getName(), $description, $default);
    }

    /**
     * {@inheritdoc}
     */
    public function supports($object)
    {
        return $object instanceof InputArgument;
    }
}
