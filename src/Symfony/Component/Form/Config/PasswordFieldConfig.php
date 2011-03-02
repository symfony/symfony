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
use Symfony\Component\Form\Renderer\Plugin\PasswordValuePlugin;

class PasswordFieldConfig extends AbstractFieldConfig
{
    public function configure(FieldInterface $field, array $options)
    {
        $field->addRendererPlugin(new PasswordValuePlugin($field, $options['always_empty']));
    }

    public function getDefaultOptions(array $options)
    {
        return array(
            'template' => 'password',
            'always_empty' => true,
        );
    }

    public function getParent(array $options)
    {
        return 'text';
    }

    public function getIdentifier()
    {
        return 'password';
    }
}