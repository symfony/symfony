<?php

namespace Symfony\Bundle\FrameworkBundle\Templating\Helper;

use Symfony\Components\Templating\Helper\Helper;
use Symfony\Components\HttpFoundation\Request;

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * RequestHelper.
 *
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class RequestHelper extends Helper
{
    protected $request;

    /**
     * Constructor.
     *
     * @param Request $request A Request instance
     */
    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    /**
     * Returns a parameter from the current request object.
     *
     * @param string $key     The name of the parameter
     * @param string $default A default value
     *
     * @see Symfony\Components\HttpFoundation\Request::get()
     */
    public function getParameter($key, $default = null)
    {
        return $this->request->get($key, $default);
    }

    /**
     * Returns the canonical name of this helper.
     *
     * @return string The canonical name
     */
    public function getName()
    {
        return 'request';
    }
}
