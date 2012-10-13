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
 * Responsible of the content negotiation.
 *
 * @author Jean-François Simon <contact@jfsimon.fr>
 */
interface NegotiatorInterface
{
    /**
     * Adds a qualifier for the negotiation.
     *
     * @param QualifierInterface $qualifier
     */
    function addQualifier(QualifierInterface $qualifier);

    /**
     * Adds a document to qualify.
     *
     * @param ContentInterface $variant
     */
    function addContent(ContentInterface $content);

    /**
     * Returns best document.
     *
     * @return ContentInterface
     */
    function getBestContent();

    /**
     * Returns varying headers.
     *
     * @return array
     */
    function getVaryingHeaders();
}
