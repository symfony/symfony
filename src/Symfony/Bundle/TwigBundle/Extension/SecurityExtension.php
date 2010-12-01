<?php

namespace Symfony\Bundle\TwigBundle\Extension;

use Symfony\Bundle\TwigBundle\TokenParser\IfRoleTokenParser;
use Symfony\Component\Security\SecurityContext;

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 *
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class SecurityExtension extends \Twig_Extension
{
    protected $context;

    public function __construct(SecurityContext $context = null)
    {
        $this->context = $context;
    }

    public function vote($role, $object = null)
    {
        if (null === $this->context) {
            return false;
        }

        return $this->context->vote($role, $object);
    }

    /**
     * {@inheritdoc}
     */
    public function getFilters()
    {
        return array(
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getTokenParsers()
    {
        return array(
            // {% ifrole "ROLE_ADMIN" %}something{% endifrole %}
            new IfRoleTokenParser(),
        );
    }

    /**
     * Returns the name of the extension.
     *
     * @return string The extension name
     */
    public function getName()
    {
        return 'security';
    }
}
