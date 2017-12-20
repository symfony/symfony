<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Fragment;

@trigger_error('The '.__NAMESPACE__.'\ContainerAwareHIncludeFragmentRenderer class is deprecated since Symfony 2.7 and will be removed in 3.0. Use Symfony\Bundle\FrameworkBundle\Fragment\HIncludeFragmentRenderer instead.', E_USER_DEPRECATED);

use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\UriSigner;
use Symfony\Component\HttpKernel\Fragment\HIncludeFragmentRenderer;

/**
 * Implements the Hinclude rendering strategy.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @deprecated since version 2.7, to be removed in 3.0. Use Symfony\Bundle\FrameworkBundle\Fragment\HIncludeFragmentRenderer instead.
 */
class ContainerAwareHIncludeFragmentRenderer extends HIncludeFragmentRenderer
{
    private $container;

    /**
     * {@inheritdoc}
     */
    public function __construct(ContainerInterface $container, UriSigner $signer = null, $globalDefaultTemplate = null)
    {
        $this->container = $container;

        parent::__construct(null, $signer, $globalDefaultTemplate, $container->getParameter('kernel.charset'));
    }

    /**
     * {@inheritdoc}
     */
    public function render($uri, Request $request, array $options = array())
    {
        // setting the templating cannot be done in the constructor
        // as it would lead to an infinite recursion in the service container
        if (!$this->hasTemplating()) {
            $this->setTemplating($this->container->get('templating'));
        }

        return parent::render($uri, $request, $options);
    }
}
