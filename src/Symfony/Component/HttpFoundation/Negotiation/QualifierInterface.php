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

/**
 * Qualifies a content.
 *
 * @author Jean-François Simon <contact@jfsimon.fr>
 */
interface QualifierInterface
{
    /**
     * Qualifies a content.
     *
     * @param ContentInterface $content
     *
     * @return float A quality, between 0 and 1
     */
    public function qualify(ContentInterface $content);

    /**
     * Returns varying headers.
     *
     * @return array
     */
    public function getVaryingHeaders();
}
