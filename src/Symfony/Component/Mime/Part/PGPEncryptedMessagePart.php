<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mime\Part;

use Symfony\Component\Mime\Helper\PGPSigningPreparer;

/*
 * @author PuLLi <the@pulli.dev>
 */
class PGPEncryptedMessagePart extends AbstractPart
{
    use PGPSigningPreparer;

    private string $body;

    public function __construct(string $body)
    {
        parent::__construct();

        $this->body = $this->normalizeLineEnding($body);
        $this->getHeaders()->addParameterizedHeader('Content-Disposition', 'inline', [
            'filename' => 'msg.asc',
        ]);
    }

    public function bodyToString(): string
    {
        return $this->body;
    }

    public function bodyToIterable(): iterable
    {
        yield $this->body;
    }

    public function getMediaType(): string
    {
        return 'application';
    }

    public function getMediaSubtype(): string
    {
        return 'octet-stream';
    }
}
