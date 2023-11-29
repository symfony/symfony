<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel;

use Symfony\Component\HttpFoundation\UriSigner as HttpFoundationUriSigner;

trigger_deprecation('symfony/http-kernel', '6.4', 'The "%s" class is deprecated, use "%s" instead.', UriSigner::class, HttpFoundationUriSigner::class);

class_exists(HttpFoundationUriSigner::class);

if (false) {
    /**
     * @deprecated since Symfony 6.4, to be removed in 7.0, use {@link HttpFoundationUriSigner} instead
     */
    class UriSigner extends HttpFoundationUriSigner
    {
    }
}
