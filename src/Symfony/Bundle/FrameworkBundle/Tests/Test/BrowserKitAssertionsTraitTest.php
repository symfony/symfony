<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests\Test;

use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\ExpectationFailedException;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\Test\BrowserKitAssertionsTrait;
use Symfony\Component\BrowserKit\AbstractBrowser;
use Symfony\Component\BrowserKit\Response as BrowserKitResponse;

class BrowserKitAssertionsTraitTest extends TestCase
{
    use BrowserKitAssertionsTrait;

    private const RESPONSE_BODY_MARKER = 'verbose-body-marker';

    #[RunInSeparateProcess]
    public function testFailureMessageIsNotVerboseByDefault()
    {
        self::getClient($this->createDummyBrowser());

        $message = $this->captureFailureMessage();

        $this->assertStringNotContainsString(self::RESPONSE_BODY_MARKER, $message);
    }

    #[RunInSeparateProcess]
    public function testPerAssertionVerboseArgumentOptsIntoVerboseFailureMessage()
    {
        self::getClient($this->createDummyBrowser());

        $message = $this->captureFailureMessage(true);

        $this->assertStringContainsString(self::RESPONSE_BODY_MARKER, $message);
    }

    #[RunInSeparateProcess]
    public function testExplicitSetterOptsIntoVerboseFailureMessage()
    {
        self::setBrowserKitAssertionsAsVerbose(true);
        self::getClient($this->createDummyBrowser());

        $message = $this->captureFailureMessage();

        $this->assertStringContainsString(self::RESPONSE_BODY_MARKER, $message);
    }

    private function captureFailureMessage(?bool $verbose = null): string
    {
        try {
            self::assertResponseStatusCodeSame(500, verbose: $verbose);
        } catch (ExpectationFailedException $e) {
            return $e->getMessage();
        }

        $this->fail('Expected assertion to fail (response status is 200, asserted 500).');
    }

    private function createDummyBrowser(): AbstractBrowser
    {
        $browser = new class('<html><body>'.self::RESPONSE_BODY_MARKER.'</body></html>') extends AbstractBrowser {
            public function __construct(private string $body)
            {
                parent::__construct();
            }

            protected function doRequest(object $request): BrowserKitResponse
            {
                return new BrowserKitResponse($this->body, 200, ['Content-Type' => 'text/html']);
            }
        };

        $browser->request('GET', '/');

        return $browser;
    }
}
