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
 * @author Jean-François Simon <contact@jfsimon.fr>
 */
class Content implements ContentInterface
{
    /**
     * @var string
     */
    private $type;

    /**
     * @var string
     */
    private $language;

    /**
     * @var string
     */
    private $charset;

    /**
     * @var float
     */
    private $quality;

    /**
     * @param string $type
     * @param string $language
     * @param string $charset
     * @param float  $quality
     */
    public function __construct($type, $language, $charset, $quality = 1)
    {
        $this->type = $type;
        $this->language = $language;
        $this->charset = $charset;
        $this->quality = $quality;
    }

    /**
     * {@inheritdoc}
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * {@inheritdoc}
     */
    public function getLanguage()
    {
        return $this->language;
    }

    /**
     * {@inheritdoc}
     */
    public function getCharset()
    {
        return $this->charset;
    }

    /**
     * {@inheritdoc}
     */
    public function setQuality($quality)
    {
        $this->quality = $quality;
    }

    /**
     * {@inheritdoc}
     */
    public function getQuality()
    {
        return $this->quality;
    }
}
