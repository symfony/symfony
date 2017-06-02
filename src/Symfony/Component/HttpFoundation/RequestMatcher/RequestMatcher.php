<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpFoundation\RequestMatcher;

use Symfony\Component\HttpFoundation\Request;

/**
 * RequestMatcher compares a pre-defined set of checks against a Request instance.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Roland Franssen <franssen.roland@gmail.com>
 */
class RequestMatcher extends ChainRequestMatcher
{
    private $host;
    private $methods;
    private $ips;
    private $attributes;
    private $schemes;

    /**
     * @param string|null $path
     * @param string|null $host
     * @param string[]    $methods
     * @param string[]    $ips
     * @param array       $attributes
     * @param string[]    $schemes
     */
    public function __construct($path = null, $host = null, array $methods = array(), array $ips = array(), array $attributes = array(), array $schemes = array())
    {
        $matchers = array();
        if (null !== $path) {
            $matchers[] = new PathRequestMatcher($path, true);
        }

        parent::__construct($matchers);

        // @todo
        $this->host = $host;
        $this->methods = $methods;
        $this->ips = $ips;
        $this->attributes = $attributes;
        $this->schemes = $schemes;
    }

    /**
     * {@inheritdoc}
     */
    public function matches(Request $request)
    {
        if ($this->schemes && !in_array($request->getScheme(), $this->schemes)) {
            return false;
        }

        if ($this->methods && !in_array($request->getMethod(), $this->methods)) {
            return false;
        }

        foreach ($this->attributes as $key => $pattern) {
            if (!preg_match('{'.$pattern.'}', $request->attributes->get($key))) {
                return false;
            }
        }

        if (!parent::matches($request)) {
            return false;
        }

        if (null !== $this->host && !preg_match('{'.$this->host.'}i', $request->getHost())) {
            return false;
        }

        if (IpUtils::checkIp($request->getClientIp(), $this->ips)) {
            return true;
        }

        // Note to future implementors: add additional checks above the
        // foreach above or else your check might not be run!
        return count($this->ips) === 0;
    }
}
