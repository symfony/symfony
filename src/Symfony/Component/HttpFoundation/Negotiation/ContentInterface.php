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
 * Represents a negotiable content.
 *
 * @author Jean-François Simon <contact@jfsimon.fr>
 */
interface ContentInterface
{
    /**
     * Returns content type.
     *
     * @return string
     */
    public function getType();

    /**
     * Returns content language code.
     *
     * @return string
     */
    public function getLanguage();

    /**
     * Returns content charset.
     *
     * @return string
     */
    public function getCharset();

    /**
     * Set the content quality.
     *
     * @param $quality
     *
     * @return float
     */
    public function setQuality($quality);

    /**
     * Get the content quality.
     *
     * @return mixed
     */
    public function getQuality();
}
