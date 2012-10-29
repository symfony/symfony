<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Negotiation;

use Symfony\Component\Routing\Matcher\NegotiatorInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * MatcherNegotiatorBuilderInterface.
 *
 * @author Jean-François Simon <contact@jfsimon.fr>
 */
interface MatcherNegotiatorBuilderInterface
{
    /**
     * Builds the negotiator.
     *
     * @param NegotiatorInterface $negotiator
     * @param Request             $request
     */
    public function buildNegotiator(NegotiatorInterface $negotiator, Request $request);

    /**
     * Returns varying headers according to the negotiator.
     *
     * @param NegotiatorInterface $negotiator
     * @param Request             $request
     *
     * @return array
     */
    public function getVaryingHeaders(NegotiatorInterface $negotiator, Request $request);
}
