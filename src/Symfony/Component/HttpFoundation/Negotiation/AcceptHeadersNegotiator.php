<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpFoundation\Negotiation;

use Symfony\Component\HttpFoundation\HeaderBag;

/**
 * Responsible of the content negotiation.
 *
 * @author Jean-François Simon <contact@jfsimon.fr>
 */
class AcceptHeadersNegotiator extends Negotiator
{
    public function setHeaders(HeaderBag $headers)
    {
        if ($headers->has('Accept')) {
            $this->addQualifier(new AcceptTypeQualifier('Accept', $headers->get('Accept')));
        }

        if ($headers->has('Accept-Language')) {
            $this->addQualifier(new AcceptTypeQualifier('Accept-Language', $headers->get('Accept-Language')));
        }

        if ($headers->has('Accept-Charset')) {
            $this->addQualifier(new AcceptTypeQualifier('Accept-Charset', $headers->get('Accept-Charset')));
        }
    }
}
