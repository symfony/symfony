<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\ErrorRenderer\ErrorRenderer;

use Symfony\Component\ErrorRenderer\Exception\FlattenException;

/**
 * @author Yonel Ceruto <yonelceruto@gmail.com>
 */
class JsonErrorRenderer implements ErrorRendererInterface
{
    private $debug;

    public function __construct(bool $debug = false)
    {
        $this->debug = $debug;
    }

    /**
     * {@inheritdoc}
     */
    public static function getFormat(): string
    {
        return 'json';
    }

    /**
     * {@inheritdoc}
     */
    public function render(FlattenException $exception): string
    {
        $debug = $this->debug && ($exception->getHeaders()['X-Debug'] ?? true);

        if ($debug) {
            $message = $exception->getMessage();
        } else {
            $message = 404 === $exception->getStatusCode() ? 'Sorry, the page you are looking for could not be found.' : 'Whoops, looks like something went wrong.';
        }

        $content = [
            'title' => $exception->getTitle(),
            'status' => $exception->getStatusCode(),
            'detail' => $message,
        ];
        if ($debug) {
            $content['exceptions'] = $exception->toArray();
        }

        return (string) json_encode($content, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_LINE_TERMINATORS | JSON_PRESERVE_ZERO_FRACTION);
    }
}
