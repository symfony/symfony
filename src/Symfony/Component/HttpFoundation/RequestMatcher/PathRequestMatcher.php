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
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Roland Franssen <franssen.roland@gmail.com>
 */
class PathRequestMatcher implements RequestMatcherInterface
{
    private $path;
    private $isRegex;

    /**
     * @param string $path
     * @param bool   $isRegex
     */
    public function __construct($path, $isRegex = false)
    {
        $this->path = $path;
        $this->isRegex = $isRegex;
    }

    /**
     * {@inheritdoc}
     */
    public function matches(Request $request)
    {
        return $this->isRegex
            ? (bool) preg_match('{'.$this->path.'}', rawurldecode($request->getPathInfo()))
            : $this->path === rawurldecode($request->getPathInfo());
    }
}
