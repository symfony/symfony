<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Bridge\MicrosoftGraph\Tests;

use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\Mailer\Bridge\MicrosoftGraph\TokenManager;

class TokenManagerMock extends TokenManager
{
    public function __construct()
    {
        parent::__construct('', '', '', '', '', new MockHttpClient());
    }

    public function getToken(): string
    {
        return 'ACCESSTOKEN';
    }
}
