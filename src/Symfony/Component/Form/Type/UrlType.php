<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Type;

use Symfony\Component\Form\FormBuilder;
use Symfony\Component\Form\EventListener\FixUrlProtocolListener;

class UrlType extends AbstractType
{
    public function configure(FormBuilder $builder, array $options)
    {
        $builder->addEventSubscriber(new FixUrlProtocolListener($options['default_protocol']));
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

    public function getName()
    {
        return 'url';
    }
}