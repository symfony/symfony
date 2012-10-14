<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Routing\Matcher;

use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\Exception\NegotiationFailureException;

/**
 * Negotiation.
 *
 * @author Jean-François Simon <contact@jfsimon.fr>
 */
interface NegotiatorInterface
{
    /**
     * Set values for negotiable parameter.
     *
     * @param string $parameter
     * @param array  $values
     */
    public function setValues($parameter, array $values);

    /**
     * @param Route $route
     *
     * @throws NegotiationFailureException
     */
    public function negotiate(Route $route);

    /**
     * @return array
     */
    public function getNegotiatedParameters();
}
