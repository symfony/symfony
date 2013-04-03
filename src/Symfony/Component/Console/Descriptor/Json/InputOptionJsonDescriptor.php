<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Console\Descriptor\Json;

use Symfony\Component\Console\Input\InputOption;

/**
 * @author Jean-François Simon <contact@jfsimon.fr>
 */
class InputOptionJsonDescriptor extends AbstractJsonDescriptor
{
    /**
     * {@inheritdoc}
     */
    public function getData($object)
    {
        /** @var InputOption $object */
        return array(
            'name'              => '--'.$object->getName(),
            'shortcut'          => $object->getShortcut() ? '-'.$object->getShortcut() : '',
            'accept_value'      => $object->acceptValue(),
            'is_value_required' => $object->isValueRequired(),
            'is_multiple'       => $object->isArray(),
            'description'       => $object->getDescription(),
            'default'           => $object->getDefault(),
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
