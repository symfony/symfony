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
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * RouterHelper manages links between pages in a template context.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class RouterHelper extends Helper
{
    protected $generator;

    public function __construct(UrlGeneratorInterface $router)
    {
        $this->generator = $router;
    }

    /**
     * Generates a URL from the given parameters.
     *
     * @param string $name          The name of the route
     * @param mixed  $parameters    An array of parameters
     * @param int    $referenceType The type of reference (one of the constants in UrlGeneratorInterface)
     *
     * @return string The generated URL
     *
     * @see UrlGeneratorInterface
     */
    public function generate($name, $parameters = array(), $referenceType = UrlGeneratorInterface::ABSOLUTE_PATH)
    {
        @trigger_error('The '.__METHOD__.' method is deprecated since Symfony 2.8 and will be removed in 3.0. Use the "path" or "url" method instead.', E_USER_DEPRECATED);

        return $this->generator->generate($name, $parameters, $referenceType);
    }

    /**
     * Generates a URL reference (as an absolute or relative path) to the route with the given parameters.
     *
     * @param string $name       The name of the route
     * @param mixed  $parameters An array of parameters
     * @param bool   $relative   Whether to generate a relative or absolute path
     *
     * @return string The generated URL reference
     *
     * @see UrlGeneratorInterface
     */
    public function path($name, $parameters = array(), $relative = false)
    {
        return $this->generator->generate($name, $parameters, $relative ? UrlGeneratorInterface::RELATIVE_PATH : UrlGeneratorInterface::ABSOLUTE_PATH);
    }

    /**
     * Generates a URL reference (as an absolute URL or network path) to the route with the given parameters.
     *
     * @param string $name           The name of the route
     * @param mixed  $parameters     An array of parameters
     * @param bool   $schemeRelative Whether to omit the scheme in the generated URL reference
     *
     * @return string The generated URL reference
     *
     * @see UrlGeneratorInterface
     */
    public function url($name, $parameters = array(), $schemeRelative = false)
    {
        return $this->generator->generate($name, $parameters, $schemeRelative ? UrlGeneratorInterface::NETWORK_PATH : UrlGeneratorInterface::ABSOLUTE_URL);
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'router';
    }
}
