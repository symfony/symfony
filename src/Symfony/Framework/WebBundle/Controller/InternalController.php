<?php

namespace Symfony\Framework\WebBundle\Controller;

use Symfony\Framework\WebBundle\Controller;

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * InternalController.
 *
 * @package    Symfony
 * @subpackage Framework_WebBundle
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class InternalController extends Controller
{
    public function indexAction()
    {
        $request = $this->getRequest();

        if ('none' !== $request->path->get('path'))
        {
            parse_str($request->path->get('path'), $tmp);
            $request->path->add($tmp);
        }

        return $this->forward($request->path->get('controller'), $request->path->all(), $request->query->all());
    }
}
