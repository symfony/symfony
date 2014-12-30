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
 * SessionHelper provides read-only access to the session attributes.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class SessionHelper extends Helper
{
    protected $session;
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
            $this->session = $requestStack->getSession();
        } elseif ($requestStack instanceof RequestStack) {
            $this->requestStack = $requestStack;
        } else {
            throw new \InvalidArgumentException('RequestHelper only accepts a Request or a RequestStack instance.');
        }
    }

    /**
     * Returns an attribute.
     *
     * @param string $name    The attribute name
     * @param mixed  $default The default value
     *
     * @return mixed
     */
    public function get($name, $default = null)
    {
        return $this->getSession()->get($name, $default);
    }

    public function getFlash($name, array $default = array())
    {
        return $this->getSession()->getFlashBag()->get($name, $default);
    }

    public function getFlashes()
    {
        return $this->getSession()->getFlashBag()->all();
    }

    public function hasFlash($name)
    {
        return $this->getSession()->getFlashBag()->has($name);
    }

    private function getSession()
    {
        if (null === $this->session) {
            if (!$this->requestStack->getMasterRequest()) {
                throw new \LogicException('A Request must be available.');
            }

            $this->session = $this->requestStack->getMasterRequest()->getSession();
        }

        return $this->session;
    }

    /**
     * Returns the canonical name of this helper.
     *
     * @return string The canonical name
     */
    public function getName()
    {
        return 'session';
    }
}
