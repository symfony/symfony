<?php

namespace Symfony\Framework\FoundationBundle\Controller;

use Symfony\Framework\FoundationBundle\Controller;

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
 * @package    Symfony
 * @subpackage Framework_FoundationBundle
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class TemplateController extends Controller
{
    public function templateAction($template)
    {
        return $this->render($template);
    }
}
