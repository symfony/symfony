<?php

declare(strict_types=1);

namespace Symfony\Component\Mime\Part;

use LogicException;
use Symfony\Component\Mime\Header\Headers;
use Symfony\Component\Mime\Header\UnstructuredHeader;
use Symfony\Component\Mime\Part\Multipart\MixedPart;

class IcsPart extends DataPart
{
    private const array RFC5546_COMPONENT_LIST = [
        'VEVENT', 'VTODO', 'VJOURNAL', 'VFREEBUSY',
    ];
    private const array RFC5546_METHOD_LIST = [
        'PUBLISH', 'REQUEST', 'REPLY', 'ADD', 'CANCEL', 'REFRESH', 'COUNTER', 'DECLINECOUNTER'
    ];

    private const MEDIA_TYPE = 'text';
    private const MEDIA_SUBTYPE = 'calendar';

    public function __construct($body, string $filename = 'invite.ics', ?string $encoding = null)
    {
        parent::__construct($body, $filename, sprintf('%s/%s', self::MEDIA_TYPE, self::MEDIA_SUBTYPE), $encoding);
        $this->setDisposition('inline');
    }

    public function getMediaType(): string
    {
        return self::MEDIA_TYPE;
    }

    public function getMediaSubtype(): string
    {
        return self::MEDIA_SUBTYPE;
    }

    public function getPreparedHeaders(): Headers
    {
        $headers = parent::getPreparedHeaders();
        $unfolded = preg_replace("/\r\n[ \t]/", '', $this->getBody());

        $contentType = $headers->get('content-type')->getBodyAsString();

        if (($method = $this->findMethod($unfolded)) !== null) {
            $contentType .= '; method='.$method;
        }

        if (($components = $this->findComponents($unfolded)) !== []) {
            foreach ($components as $component) {
                $contentType .= '; component='.$component;
            }
        }

        $headers->remove('content-type');
        $headers->add(new UnstructuredHeader('Content-Type', strtolower($contentType)));

        return $headers;
    }

    private function findMethod(string $unfoldedBody): ?string
    {
        $methodsRegex = implode('|', self::RFC5546_METHOD_LIST);
        if (1 !== preg_match("/^METHOD:(?<method>$methodsRegex)\s*$/mi", $unfoldedBody, $matches)) {
            throw new LogicException('Propriété METHOD absente ou invalide dans le contenu ICS.');
        }

        if (!isset($matches['method']) || [] === $matches['method']) {
            return null;
        }

        return strtoupper($matches['method']);
    }

    private function findComponents(string $unfoldedBody): array
    {
        $componentsRegex = implode('|', self::RFC5546_COMPONENT_LIST);
        preg_match_all("/^BEGIN:($componentsRegex)\s*$/mi", $unfoldedBody, $matches);

        $components = array_unique(array_map('strtolower', $matches[1]));

        if (\count(array_diff($components, ['vtimezone'])) > 1) {
            throw new LogicException(sprintf(
                'Plusieurs composants iTIP principaux dans le même fichier ics. Utilisez plusieurs ics dans un %s.',
                MixedPart::class
            ));
        }

        return $components;
    }
}
