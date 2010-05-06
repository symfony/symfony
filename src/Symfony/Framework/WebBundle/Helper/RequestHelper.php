<?php

namespace Symfony\Framework\WebBundle\Helper;

use Symfony\Components\Templating\Helper\Helper;
use Symfony\Components\HttpKernel\Request;

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
 * @package    Symfony
 * @subpackage Framework_WebBundle
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

    public function getParameter($key, $default = null)
    {
        return $this->request->getParameter($key, $default);
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
