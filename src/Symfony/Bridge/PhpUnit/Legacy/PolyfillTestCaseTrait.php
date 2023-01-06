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

use PHPUnit\Framework\Error\Error;
use PHPUnit\Framework\Error\Notice;
use PHPUnit\Framework\Error\Warning;

/**
 * This trait is @internal.
 */
trait PolyfillTestCaseTrait
{
    /**
     * @param string $messageRegExp
     *
     * @return void
     */
    public function expectExceptionMessageMatches($messageRegExp)
    {
        $this->expectExceptionMessageRegExp($messageRegExp);
    }

    /**
     * @return void
     */
    public function expectNotice()
    {
        $this->expectException(Notice::class);
    }

    /**
     * @param string $message
     *
     * @return void
     */
    public function expectNoticeMessage($message)
    {
        $this->expectExceptionMessage($message);
    }

    /**
     * @param string $regularExpression
     *
     * @return void
     */
    public function expectNoticeMessageMatches($regularExpression)
    {
        $this->expectExceptionMessageMatches($regularExpression);
    }

    /**
     * @return void
     */
    public function expectWarning()
    {
        $this->expectException(Warning::class);
    }

    /**
     * @param string $message
     *
     * @return void
     */
    public function expectWarningMessage($message)
    {
        $this->expectExceptionMessage($message);
    }

    /**
     * @param string $regularExpression
     *
     * @return void
     */
    public function expectWarningMessageMatches($regularExpression)
    {
        $this->expectExceptionMessageMatches($regularExpression);
    }

    /**
     * @return void
     */
    public function expectError()
    {
        $this->expectException(Error::class);
    }

    /**
     * @param string $message
     *
     * @return void
     */
    public function expectErrorMessage($message)
    {
        $this->expectExceptionMessage($message);
    }

    /**
     * @param string $regularExpression
     *
     * @return void
     */
    public function expectErrorMessageMatches($regularExpression)
    {
        $this->expectExceptionMessageMatches($regularExpression);
    }
}
