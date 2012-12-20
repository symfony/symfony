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
use Symfony\Bundle\FrameworkBundle\HttpKernel;

/**
 * ActionsHelper manages action inclusions.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class ActionsHelper extends Helper
{
    protected $kernel;

    /**
     * Constructor.
     *
     * @param HttpKernel $kernel A HttpKernel instance
     */
    public function __construct(HttpKernel $kernel)
    {
        $this->kernel = $kernel;
    }

    /**
     * Returns the Response content for a given URI.
     *
     * @param string $uri     A URI
     * @param array  $options An array of options
     *
     * @return string
     *
     * @see Symfony\Bundle\FrameworkBundle\HttpKernel::render()
     */
    public function render($uri, array $options = array())
    {
        return $this->kernel->render($uri, $options);
    }

    /**
     * Returns the canonical name of this helper.
     *
     * @return string The canonical name
     */
    public function getName()
    {
        return 'actions';
    }
}
