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

use Symfony\Component\Form\Renderer\RendererInterface;
use Symfony\Component\Form\FieldInterface;

class SelectMultipleNamePlugin extends NamePlugin
{
    public function setUp(RendererInterface $renderer)
    {
        parent::setUp($renderer);

        // Add "[]" to the name in case a select tag with multiple options is
        // displayed. Otherwise only one of the selected options is sent in the
        // POST request.
        $renderer->setVar('name', $renderer->getVar('name').'[]');
    }
}