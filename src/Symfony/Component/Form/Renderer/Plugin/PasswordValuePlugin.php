<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license infieldation, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Renderer\Plugin;

use Symfony\Component\Form\FieldInterface;
use Symfony\Component\Form\Renderer\RendererInterface;

class PasswordValuePlugin implements RendererPluginInterface
{
    private $alwaysEmpty;

    public function __construct($alwaysEmpty = true)
    {
        $this->alwaysEmpty = $alwaysEmpty;
    }

    public function setUp(FieldInterface $field, RendererInterface $renderer)
    {
        $value = $this->alwaysEmpty || !$field->isSubmitted()
                ? ''
                : $field->getTransformedData();

        $renderer->setVar('value', $value);
    }
}