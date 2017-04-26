<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Test;

use Symfony\Component\BrowserKit\Client;

/**
 * AssertWebTestCase add custom assertion to ease functional testing
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
abstract class AssertWebTestCase extends WebTestCase
{
    /**
     * Asserts that the response is redirect
     *
     * You can optionnaly specify location the uri should match
     *
     * @param  Client  $client
     * @param  string  $location
     */
    public static function assertIsRedirect(Client $client, $location = null)
    {
        if ($location == null) {
            $location = $client->getResponse()->headers->get('Location');
        }

        $uri = $client->getRequest()->getUri();
        $status = $client->getResponse()->getStatusCode();

        self::assertTrue(
            $client->getResponse()->isRedirected($location),
            sprintf('failed asserting that response is redirected, uri : "%s", status code : "%s"', $uri, $status)
        );
    }

    /**
     * Asserts that the response is successful
     *
     * @param  Client  $client
     */
    public static function assertIsSuccessful(Client $client)
    {
        $status = $client->getResponse()->getStatusCode();

        self::assertTrue(
            $client->getResponse()->isSuccessful(),
            sprintf('failed asserting that response is successful, status code : "%s"', $status)
        );
    }

    /**
     * Asserts that the response is not found
     *
     * @param  Client  $client
     */
    public static function assertIsNotFound(Client $client)
    {
        $status = $client->getResponse()->getStatusCode();

        self::assertTrue(
            $client->getResponse()->isNotFound(),
            sprintf('failed asserting that response is not found, status code : "%s"', $status)
        );
    }

    /**
     * Asserts that the response has a given status code
     *
     * @param  Client  $client
     * @param  integer $statusCode
     */
    public static function assertStatusCode(Client $client, $statusCode)
    {
        $actualStatus = $client->getResponse()->getStatusCode();

        self::assertEquals(
            $statusCode, $actualStatus,
            sprintf('failed asserting that response status code is "%s", actual status code is "%s"', $statusCode, $actualStatus)
        );
    }

    /**
     * Asserts that the response contains a given text
     *
     * You can optionnaly filter on a specific html tag
     *
     * @param  Client  $client
     * @param  string  $text
     * @param  string  $tag
     */
    public static function assertResponseContains(Client $client, $text, $tag = 'html')
    {
        self::assertGreaterThan(
            0, $client->getCrawler()->filter(sprintf('%s:contains("%s")', $tag, $text)->count()),
            sprintf('failed asserting that response contains : "%s", inside a tag : "%s"', $text, $tag)
        );
    }
}
