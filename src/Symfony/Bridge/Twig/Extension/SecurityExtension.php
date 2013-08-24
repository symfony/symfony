<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Twig\Extension;

use Symfony\Component\Security\Acl\Voter\FieldVote;
use Symfony\Component\Security\Core\SecurityContextInterface;

/**
 * SecurityExtension exposes security context features.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @since v2.0.0
 */
class SecurityExtension extends \Twig_Extension
{
    private $context;

    /**
     * @since v2.0.0
     */
    public function __construct(SecurityContextInterface $context = null)
    {
        $this->context = $context;
    }

    /**
     * @since v2.0.0
     */
    public function isGranted($role, $object = null, $field = null)
    {
        if (null === $this->context) {
            return false;
        }

        if (null !== $field) {
            $object = new FieldVote($object, $field);
        }

        return $this->context->isGranted($role, $object);
    }

    /**
     * {@inheritdoc}
     *
     * @since v2.0.0
     */
    public function getFunctions()
    {
        return array(
            new \Twig_SimpleFunction('is_granted', array($this, 'isGranted')),
        );
    }

    /**
     * Returns the name of the extension.
     *
     * @return string The extension name
     *
     * @since v2.0.0
     */
    public function getName()
    {
        return 'security';
    }
}
