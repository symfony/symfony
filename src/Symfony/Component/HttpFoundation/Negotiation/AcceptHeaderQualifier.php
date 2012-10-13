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
     * @param string $header
     */
    public function __construct($header)
    {
        $this->qualities = array();
        foreach (AcceptHeader::split($header) as $value => $quality) {
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
     * @param string $value
     *
     * @return int
     */
    protected function findQuality($value)
    {
        return isset($this->qualities[$value]) ? $this->qualities[$value] : 0;
    }
}
