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
class Negotiator implements NegotiatorInterface
{
    /**
     * @var QualifierInterface[]
     */
    private $qualifiers = array();

    /**
     * @var ContentInterface[]
     */
    private $contents = array();

    /**
     * @var bool
     */
    private $frozen = false;

    /**
     * @var bool
     */
    private $sorted = true;

    /**
     * {@inheritdoc}
     */
    public function addQualifier(QualifierInterface $qualifier)
    {
        if ($this->frozen) {
            throw new \LogicException('Negotiation is frozen because it started to qualify document.');
        }

        $this->qualifiers[] = $qualifier;
    }

    /**
     * {@inheritdoc}
     */
    public function addContent(ContentInterface $content)
    {
        $this->frozen = true;
        $this->sorted = false;

        $quality = $content->getQuality();
        foreach ($this->qualifiers as $qualifier) {
            $quality *= $qualifier->qualify($content);
        }

        $content->setQuality($quality);
        $this->contents[] = $content;
    }

    /**
     * {@inheritdoc}
     */
    public function getBestContent()
    {
        $this->sort();

        return current($this->contents);
    }

    /**
     * {@inheritdoc}
     */
    public function getVaryingHeaders()
    {
        $headers = array();
        foreach ($this->qualifiers as $qualifier) {
            $headers = array_merge($headers, $qualifier->getVaryingHeaders());
        }

        return array_unique($headers);
    }

    /**
     * Sorts document by descending quality;
     */
    private function sort()
    {
        if ($this->sorted) {
            return;
        }

        usort($this->contents, function (ContentInterface $a, ContentInterface $b) {
            return $b->getQuality() - $a->getQuality();
        });

        $this->sorted = true;
    }
}
