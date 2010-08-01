<?php

namespace Symfony\Bundle\FrameworkBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller;

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * TemplateController.
 *
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class TemplateController extends Controller
{
    public function templateAction($template)
    {
        return $this->render($template);
    }
}
