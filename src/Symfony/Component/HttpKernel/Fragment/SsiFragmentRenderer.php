<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Fragment;

use Symfony\Component\HttpKernel\Controller\ControllerReference;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\UriSigner;

/**
 * Implements the ESI rendering strategy.
 *
 * @author Sebastian Krebs <krebs.seb@gmail.com>
 */
class SsiFragmentRenderer extends AbstractSurrogateFragmentRenderer
{
    /** @var UriSigner */
    private $signer;

    /**
     * Set uri signer
     *
     * @param UriSigner $signer
     */
    public function setUriSigner(UriSigner $signer)
    {
        $this->signer = $signer;
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'ssi';
    }

    /**
     * {@inheritdoc}
     */
    protected function generateFragmentUri(ControllerReference $reference, Request $request, $absolute = false, $strict = true)
    {
        $uri = parent::generateFragmentUri($reference, $request, $absolute, $strict);

        if ($this->signer) {
            $uri = $this->signer->sign($uri);
        }

        return $uri;
    }
}
