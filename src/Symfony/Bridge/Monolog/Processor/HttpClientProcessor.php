<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Monolog\Processor;

use Symfony\Component\VarDumper\VarDumper;
use Symfony\Contracts\HttpClient\Exception\HttpExceptionInterface;

/**
 * Add debugging info (Response headers, response content, etc.) about failing HttpClient requests.
 *
 * @author Benoit Galati <benoit.galati@gmail.com>
 *
 * @final
 */
class HttpClientProcessor
{
    public function __invoke(array $record): array
    {
        $exception = $record['context']['exception'] ?? null;

        if ($exception === null) {
            return $record;
        }

        while ($exception instanceof \Throwable) {
            if ($exception instanceof HttpExceptionInterface) {
                // It needs to be the 1st statement in order to fulfil the response info
                $responseContent = $exception->getResponse()->getContent(false);

                $record['context']['http_client'][] =
                    $exception->getResponse()->getInfo()
                    + ['response_content' => mb_strimwidth($responseContent, 0, 10000)]
                ;
            }
            $exception = $exception->getPrevious();
        }

        return $record;
    }

}
