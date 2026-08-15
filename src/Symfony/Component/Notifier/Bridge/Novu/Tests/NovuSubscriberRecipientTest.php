<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Novu\Tests;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\PhpUnit\ExpectUserDeprecationMessageTrait;
use Symfony\Component\Notifier\Bridge\Novu\NovuSubscriberRecipient;

class NovuSubscriberRecipientTest extends TestCase
{
    use ExpectUserDeprecationMessageTrait;

    #[Group('legacy')]
    #[IgnoreDeprecations]
    public function testPassingOverridesIsDeprecated()
    {
        $this->expectUserDeprecationMessage('Since symfony/novu-notifier 8.2: Passing "$overrides" to "Symfony\Component\Notifier\Bridge\Novu\NovuSubscriberRecipient::__construct()" is deprecated, pass them to "Symfony\Component\Notifier\Bridge\Novu\NovuOptions" instead.');

        new NovuSubscriberRecipient('123', overrides: ['email' => ['from' => 'no-reply@example.com']]);
    }

    #[Group('legacy')]
    #[IgnoreDeprecations]
    public function testGetOverridesIsDeprecated()
    {
        $this->expectUserDeprecationMessage('Since symfony/novu-notifier 8.2: The "Symfony\Component\Notifier\Bridge\Novu\NovuSubscriberRecipient::getOverrides()" method is deprecated, pass overrides to "Symfony\Component\Notifier\Bridge\Novu\NovuOptions" instead.');

        $this->assertSame([], (new NovuSubscriberRecipient('123'))->getOverrides());
    }
}
