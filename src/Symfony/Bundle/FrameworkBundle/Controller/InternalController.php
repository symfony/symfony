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
 * InternalController.
 *
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class InternalController extends Controller
{
    public function indexAction()
    {
        $request = $this->getRequest();

        if ('none' !== $request->attributes->get('path'))
        {
            parse_str($request->attributes->get('path'), $tmp);
            $request->attributes->add($tmp);
        }

        return $this->forward($request->attributes->get('controller'), $request->attributes->all(), $request->query->all());
    }
}
