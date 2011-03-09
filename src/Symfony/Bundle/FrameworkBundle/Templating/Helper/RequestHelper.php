<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Templating\Helper;

use Symfony\Component\Templating\Helper\Helper;
use Symfony\Component\HttpFoundation\Request;

/**
 * RequestHelper provides access to the current request parameters.
 *
 * @author Fabien Potencier <fabien@symfony.com>
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
     * @see Symfony\Component\HttpFoundation\Request::get()
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
