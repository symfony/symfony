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

use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Templating\Helper\Helper;

/**
 * SessionHelper provides read-only access to the session attributes.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class SessionHelper extends Helper
{
    protected $session;
    protected $requestStack;

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

    public function getFlash($name, array $default = [])
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
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'session';
    }
}
