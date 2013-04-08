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

use Symfony\Component\Console\Input\InputOption;

/**
 * @author Jean-François Simon <contact@jfsimon.fr>
 */
class InputOptionTextDescriptor extends AbstractTextDescriptor
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
        /** @var InputOption $object */
        if ($object->acceptValue() && null !== $object->getDefault() && (!is_array($object->getDefault()) || count($object->getDefault()))) {
            $default = sprintf('<comment> (default: %s)</comment>', $this->formatDefaultValue($object->getDefault()));
        } else {
            $default = '';
        }

        $nameWidth = $this->nameWidth ?: strlen($object->getName());
        $nameWithShortcutWidth = $nameWidth - strlen($object->getName()) - 2;

        return sprintf(" <info>%s</info> %-${nameWithShortcutWidth}s%s%s%s",
            '--'.$object->getName(),
            $object->getShortcut() ? sprintf('(-%s) ', $object->getShortcut()) : '',
            str_replace("\n", "\n".str_repeat(' ', $nameWidth + 2), $object->getDescription()),
            $default,
            $object->isArray() ? '<comment> (multiple values allowed)</comment>' : ''
        );
    }

    /**
     * {@inheritdoc}
     */
    public function supports($object)
    {
        return $object instanceof InputOption;
    }
}
