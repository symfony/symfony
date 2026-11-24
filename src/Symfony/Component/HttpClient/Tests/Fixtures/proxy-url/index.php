<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

if ('cli-server' !== \PHP_SAPI) {
    // safe guard against unwanted execution
    throw new \Exception("You cannot run this script directly, it's a fixture for HttpClientTraitTest.");
}

$parent = __DIR__;
while (!@file_exists($parent.'/vendor/autoload.php')) {
    if (!@file_exists($parent)) {
        // open_basedir restriction in effect
        break;
    }
    if ($parent === dirname($parent)) {
        echo "vendor/autoload.php not found\n";
        exit(1);
    }

    $parent = dirname($parent);
}

require $parent.'/vendor/autoload.php';

$client = new class() {
    use Symfony\Component\HttpClient\HttpClientTrait;

    public function getProxyUrlFor(string $scheme): ?string
    {
        return self::getProxyUrl(null, ['scheme' => $scheme]);
    }
};

echo json_encode(['sapi' => \PHP_SAPI, 'http' => $client->getProxyUrlFor('http:'), 'https' => $client->getProxyUrlFor('https:')]);
