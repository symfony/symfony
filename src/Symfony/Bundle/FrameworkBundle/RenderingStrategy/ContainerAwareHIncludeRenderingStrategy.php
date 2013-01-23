<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\RenderingStrategy;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\UriSigner;
use Symfony\Component\HttpKernel\RenderingStrategy\HIncludeRenderingStrategy;

/**
 * Implements the Hinclude rendering strategy.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class ContainerAwareHIncludeRenderingStrategy extends HIncludeRenderingStrategy
{
    private $container;

    /**
     * {@inheritdoc}
     */
    public function __construct(ContainerInterface $container, UriSigner $signer = null, $globalDefaultTemplate = null)
    {
        $this->container = $container;

        parent::__construct(null, $signer, $globalDefaultTemplate);
    }

    /**
     * {@inheritdoc}
     */
    public function render($uri, Request $request, array $options = array())
    {
        if (!$this->templating) {
            $this->templating = $this->container->get('templating');
        }

        return parent::render($uri, $request, $options);
    }
}
