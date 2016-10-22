<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpFoundation;

/**
 * @author Roland Franssen <franssen.roland@gmail.com>
 */
class ChainRequestMatcher implements RequestMatcherInterface
{
    private $matchers;

    /**
     * @param RequestMatcherInterface[] $matchers
     */
    public function __construct(array $matchers)
    {
        $this->matchers = $matchers;
    }

    /**
     * {@inheritdoc}
     */
    public function matches(Request $request)
    {
        foreach ($this->matchers as $matcher) {
            if (!$matcher->matches($request)) {
                return false;
            }
        }

        return true;
    }
}
