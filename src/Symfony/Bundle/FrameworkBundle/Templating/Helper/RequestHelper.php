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
 * RequestHelper provides access to the current request parameters.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class RequestHelper extends Helper
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
     * Returns a parameter from the current request object.
     *
     * @param string $key     The name of the parameter
     * @param string $default A default value
     *
     * @return mixed
     *
     * @see \Symfony\Component\HttpFoundation\Request::get()
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

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'request';
    }

    private function getRequest()
    {
        if ($currentRequest = $this->requestStack->getCurrentRequest()) {
            return $currentRequest;
        }

        throw new \LogicException('A Request must be available.');
    }
}
