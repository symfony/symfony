<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Renderer;

use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\Renderer\Engine\EngineInterface;

class FormRenderer extends FieldRenderer
{
    public function __construct(FormInterface $form, EngineInterface $engine)
    {
        parent::__construct($form, $engine);
    }

    /**
     * Renders the HTML enctype in the form tag, if necessary
     *
     * Example usage in Twig templates:
     *
     *     <form action="..." method="post" {{ form.render.enctype }}>
     *
     * @param Form $form   The form for which to render the encoding type
     */
    public function enctype()
    {
        return $this->getField()->isMultipart() ? 'enctype="multipart/form-data"' : '';
    }
}