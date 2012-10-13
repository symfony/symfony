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

use Symfony\Component\HttpFoundation\AcceptHeader;

/**
 * AcceptCharsetQualifier.
 *
 * @author Jean-François Simon <contact@jfsimon.fr>
 */
abstract class AcceptCharsetQualifier extends AcceptHeaderQualifier
{
    /**
     * {@inheritdoc}
     */
    public function qualify(ContentInterface $content)
    {
        return $this->findQuality($content->getCharset());
    }
}
