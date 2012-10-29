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
use Symfony\Component\HttpFoundation\AcceptHeader;

/**
 * AcceptHeaderQualifier.
 *
 * @author Jean-François Simon <contact@jfsimon.fr>
 */
abstract class AcceptHeaderQualifier implements QualifierInterface
{
    /**
     * @var array
     */
    private $qualities;

    /**
     * @var string
     */
    private $headerName;

    /**
     * @param string $headerName
     * @param string $headerValue
     */
    public function __construct($headerName, $headerValue)
    {
        $this->headerName = $headerName;
        $this->qualities = array();
        foreach (AcceptHeader::split($headerValue) as $value => $quality) {
            $this->add($value, $quality);
        }
    }

    /**
     * @param string $value
     * @param float  $quality
     */
    public function add($value, $quality = 1)
    {
        $this->qualities[$value] = $quality;
    }

    /**
     * {@inheritdoc}
     */
    public function getVaryingHeaders()
    {
        return array($this->headerName);
    }

    /**
     * @param string $value
     *
     * @return int
     */
    protected function findQuality($value)
    {
        return isset($this->qualities[$value]) ? $this->qualities[$value] : 0;
    }
}
