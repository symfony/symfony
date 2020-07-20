<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\PhpUnit\Legacy;

use SebastianBergmann\Exporter\Exporter;

/**
 * @internal
 */
trait ConstraintTraitForV6
{
    /**
     * @return int
     */
    public function count()
    {
        return $this->doCount();
    }

    /**
     * @return string
     */
    public function toString()
    {
        return $this->doToString();
    }

    /**
     * @param mixed $other
     *
     * @return string
     */
    protected function additionalFailureDescription($other)
    {
        return $this->doAdditionalFailureDescription($other);
    }

    /**
     * @return Exporter
     */
    protected function exporter()
    {
        if (null === $this->exporter) {
            $this->exporter = new Exporter();
        }

        return $this->exporter;
    }

    /**
     * @param mixed $other
     *
     * @return string
     */
    protected function failureDescription($other)
    {
        return $this->doFailureDescription($other);
    }

    /**
     * @param mixed $other
     *
     * @return bool
     */
    protected function matches($other)
    {
        return $this->doMatches($other);
    }

    private function doAdditionalFailureDescription($other)
    {
        return '';
    }

    private function doCount()
    {
        return 1;
    }

    private function doFailureDescription($other)
    {
        return $this->exporter()->export($other).' '.$this->toString();
    }

    private function doMatches($other)
    {
        return false;
    }

    private function doToString()
    {
        return '';
    }
}
