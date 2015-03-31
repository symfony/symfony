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
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * SessionHelper provides read-only access to the session attributes.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class SessionHelper extends Helper
{
    protected $requestStack;

    /**
     * Constructor.
     *
     * @param RequestStack $requestStack A RequestStack instance
     */
    public function __construct(RequestStack $requestStack)
    {
        $this->requestStack = $requestStack;
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

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'session';
    }

    private function getSession()
    {
        if ($masterRequest = $this->requestStack->getMasterRequest()) {
            return $masterRequest->getSession();
        }

        throw new \LogicException('A Request must be available.');
    }
}
