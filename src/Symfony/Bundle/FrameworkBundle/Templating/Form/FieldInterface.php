<?php

namespace Symfony\Bundle\FrameworkBundle\Templating\Form;

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * 
 *
 * @author Fabien Potencier <fabien.potencier@symfony-project.com>
 */
interface FieldInterface extends \IteratorAggregate, \ArrayAccess
{
    function render($template = null);
}
