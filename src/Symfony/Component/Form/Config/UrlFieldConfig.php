<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Config;

use Symfony\Component\Form\FieldInterface;
use Symfony\Component\Form\EventListener\FixUrlProtocolListener;

class UrlFieldConfig extends AbstractFieldConfig
{
    public function configure(FieldInterface $field, array $options)
    {
        $field->addEventSubscriber(new FixUrlProtocolListener($options['default_protocol']));
    }

    public function getDefaultOptions(array $options)
    {
        return array(
            'default_protocol' => 'http',
        );
    }

    public function getParent(array $options)
    {
        return 'text';
    }

    public function getIdentifier()
    {
        return 'url';
    }
}