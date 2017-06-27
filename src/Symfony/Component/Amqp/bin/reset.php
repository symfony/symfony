#!/usr/bin/env php
<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

if (file_exists($autoload = __DIR__.'/../vendor/autoload.php')) {
    require $autoload;
} elseif (file_exists($autoload = __DIR__.'/../../../../../vendor/autoload.php')) {
    require $autoload;
} else {
    throw new \Exception('Impossible to find the autoloader.');
}

$dsn = getenv('AMQP_DSN');

if (!$dsn) {
    $xml = new DOMDocument();
    $xml->load(__DIR__.'/../phpunit.xml.dist');
    $dsn = (new DOMXPath($xml))->query('//php/env[@name="AMQP_DSN"]')[0]->getAttribute('value');
}

if (!isset($argv[1]) || 'force' !== $argv[1]) {
    echo "You are going to use $dsn\n";
    echo 'Do you confirm? [Y/n]';
    $confirmation = strtolower(trim(fgets(STDIN))) ?: 'y';
    if (0 === strpos($confirmation, 'n')) {
        echo "Aborted !\n";
        exit(1);
    }
}

extract(Symfony\Component\Amqp\DsnParser::parseDsn($dsn));

function call_api(string $method, string $url, string $content = null)
{
    global $host, $login, $password;

    $contextOptions = array(
        'http' => array(
            'header' => 'Authorization: Basic '.base64_encode("$login:$password")."\r\nContent-Type: application/json",
            'method' => $method,
            'ignore_errors' => true,
        ),
    );

    if ($content) {
        $contextOptions['http']['content'] = $content;
    }

    file_get_contents("http://$host:15672/api$url", false, stream_context_create($contextOptions));
}

call_api('DELETE', "/vhosts/$vhost");
call_api('PUT', "/vhosts/$vhost");
call_api('PUT', "/permissions/$vhost/$login", '{"configure":".*","write":".*","read":".*"}');
