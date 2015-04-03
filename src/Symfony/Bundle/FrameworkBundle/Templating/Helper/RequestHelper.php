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
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * RequestHelper provides access to the current request parameters.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class RequestHelper extends Helper
{
    protected $request;
    protected $requestStack;

    /**
     * Constructor.
     *
     * @param Request|RequestStack $requestStack A RequestStack instance or a Request instance
     *
     * @deprecated since version 2.5, passing a Request instance is deprecated and support for it will be removed in 3.0.
     */
    public function __construct($requestStack)
    {
        if ($requestStack instanceof Request) {
            trigger_error('Since version 2.5, passing a Request instance into the '.__METHOD__.' is deprecated and support for it will be removed in 3.0. Inject a Symfony\Component\HttpFoundation\RequestStack instance instead.', E_USER_DEPRECATED);
            $this->request = $requestStack;
        } elseif ($requestStack instanceof RequestStack) {
            $this->requestStack = $requestStack;
        } else {
            throw new \InvalidArgumentException('RequestHelper only accepts a Request or a RequestStack instance.');
        }
    }

    /**
     * Returns a parameter from the current request object.
     *
     * @param string $key     The name of the parameter
     * @param string $default A default value
     *
     * @return mixed
     *
     * @see Request::get()
     */
    public function getParameter($key, $default = null)
    {
        return $this->getRequest()->get($key, $default);
    }

    /**
     * Returns the locale.
     *
     * @return string
     */
    public function getLocale()
    {
        return $this->getRequest()->getLocale();
    }

    private function getRequest()
    {
        if ($this->requestStack) {
            if (!$this->requestStack->getCurrentRequest()) {
                throw new \LogicException('A Request must be available.');
            }

            return $this->requestStack->getCurrentRequest();
        }

        return $this->request;
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'request';
    }
}
