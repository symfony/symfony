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

use Symfony\Component\Routing\Matcher\Negotiator;

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
     * @param Negotiator $negotiator
     */
    public function buildNegotiator(Negotiator $negotiator);

    /**
     * Returns varying headers according to the negotiator.
     *
     * @param Negotiator $negotiator
     *
     * @return array
     */
    public function getVaryingHeaders(Negotiator $negotiator);
}
