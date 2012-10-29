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
class Negotiator implements NegotiatorInterface
{
    /**
     * @var array
     */
    private $negotiableValues = array();

    /**
     * @var array
     */
    private $negotiatedParameters = array();

    /**
     * {@inheritdoc}
     */
    public function setValues($parameter, array $values)
    {
        $this->negotiableValues[$parameter] = $values;
    }

    /**
     * {@inheritdoc}
     */
    public function negotiate(Route $route)
    {
        if (!is_array($route->getOption('negotiate'))) {
            return;
        }

        $failures = array();
        foreach ($route->getOption('negotiate') as $parameter) {
            $values = $this->findValues($parameter, $route->getRequirement($parameter));

            if (count($values) > 0) {
                $route->setDefault($parameter, $values[0]);
                $this->negotiatedParameters[] = $parameter;
            } else {
                $failures[] = $parameter;
            }
        }

        if (count($failures) > 0) {
            throw new NegotiationFailureException($route, $failures);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getNegotiatedParameters()
    {
        return $this->negotiatedParameters;
    }

    /**
     * Finds values for given parameter according to requirement (if any).
     *
     * @param string      $parameter
     * @param string|null $requirement
     *
     * @return array
     */
    private function findValues($parameter, $requirement = null)
    {
        if (!isset($this->negotiableValues[$parameter])) {
            return array();
        }

        if (null === $requirement) {
            return $this->negotiableValues[$parameter];
        }

        return array_filter($this->negotiableValues[$parameter], function ($value) use ($requirement) {
            return preg_match(sprintf('~%s~i', $requirement), $value);
        });
    }
}
