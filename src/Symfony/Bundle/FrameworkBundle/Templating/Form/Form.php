<?php

namespace Symfony\Bundle\FrameworkBundle\Templating\Form;

use Symfony\Component\Templating\Helper\Helper;
use Symfony\Component\Templating\Engine;
use Symfony\Component\Form\FieldGroupInterface;
use Symfony\Bundle\FrameworkBundle\Templating\HtmlGeneratorInterface;

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * Form is a factory that wraps Form instances.
 *
 * @author Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class Form
{
    public $generator;

    protected $engine;
    protected $theme;
    protected $doctype;

    public function __construct(Engine $engine, HtmlGeneratorInterface $generator, $theme = 'table', $doctype = 'xhtml')
    {
        $this->engine = $engine;
        $this->generator = $generator;
        $this->theme = $theme;
        $this->doctype = $doctype;
    }

    public function get(FieldGroupInterface $group, $theme = null, $doctype = null)
    {
        return new FieldGroup($group, $this->engine, $this->generator, null === $theme ? $this->theme : $theme, null === $doctype ? $this->doctype : $doctype);
    }
}
