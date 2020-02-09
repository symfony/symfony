<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Routing\Loader;

use Symfony\Bundle\FrameworkBundle\Controller\RedirectController;
use Symfony\Bundle\FrameworkBundle\Controller\TemplateController;
use Symfony\Component\Config\Util\Exception\InvalidXmlException;
use Symfony\Component\Config\Util\Exception\XmlParsingException;
use Symfony\Component\Config\Util\XmlUtils;
use Symfony\Component\Routing\Loader\XmlFileLoader as BaseXmlFileLoader;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * @author Jules Pietri <jules@heahprod.com>
 */
class XmlFileLoader extends BaseXmlFileLoader
{
    public const SCHEME_PATH = __DIR__.'/../../Resources/config/schema/framework-routing-1.0.xsd';

    private const REDEFINED_SCHEME_URI = 'https://symfony.com/schema/routing/routing-1.0.xsd';
    private const SCHEME_URI = 'https://symfony.com/schema/routing/framework-routing-1.0.xsd';
    private const SCHEMA_LOCATIONS = [
        self::REDEFINED_SCHEME_URI => parent::SCHEME_PATH,
        self::SCHEME_URI => self::SCHEME_PATH,
    ];

    /** @var \DOMDocument */
    private $document;

    /**
     * {@inheritdoc}
     */
    protected function loadFile(string $file)
    {
        if ('' === trim($content = @file_get_contents($file))) {
            throw new \InvalidArgumentException(sprintf('File "%s" does not contain valid XML, it is empty.', $file));
        }

        foreach (self::SCHEMA_LOCATIONS as $uri => $path) {
            if (false !== strpos($content, $uri)) {
                $content = str_replace($uri, self::getRealSchemePath($path), $content);
            }
        }

        try {
            return $this->document = XmlUtils::parse($content, function (\DOMDocument $document) {
                return @$document->schemaValidateSource(str_replace(
                    self::REDEFINED_SCHEME_URI,
                    self::getRealSchemePath(parent::SCHEME_PATH),
                    file_get_contents(self::SCHEME_PATH)
                ));
            });
        } catch (InvalidXmlException $e) {
            throw new XmlParsingException(sprintf('The XML file "%s" is not valid.', $file), 0, $e->getPrevious());
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function parseNode(RouteCollection $collection, \DOMElement $node, $path, $file)
    {
        switch ($node->localName) {
            case 'template-route':
            case 'redirect-route':
            case 'url-redirect-route':
            case 'gone-route':
                if (self::NAMESPACE_URI !== $node->namespaceURI) {
                    return;
                }

                $this->parseRoute($collection, $node, $path);

                return;
        }

        parent::parseNode($collection, $node, $path, $file);
    }

    /**
     * {@inheritdoc}
     */
    protected function parseRoute(RouteCollection $collection, \DOMElement $node, $path)
    {
        $templateContext = [];

        if ('template-route' === $node->localName) {
            /** @var \DOMElement $context */
            foreach ($node->getElementsByTagNameNS(self::NAMESPACE_URI, 'context') as $context) {
                $node->removeChild($context);
                $map = $this->document->createElementNS(self::NAMESPACE_URI, 'map');

                // extract context vars into a map
                foreach ($context->childNodes as $n) {
                    if (!$n instanceof \DOMElement) {
                        continue;
                    }

                    $map->appendChild($n);
                }

                $default = $this->document->createElementNS(self::NAMESPACE_URI, 'default');
                $default->setAttribute('key', 'context');
                $default->appendChild($map);

                $templateContext = $this->parseDefaultsConfig($default, $path);
            }
        }

        parent::parseRoute($collection, $node, $path);

        if ($route = $collection->get($id = $node->getAttribute(('id')))) {
            $this->parseConfig($node, $route, $templateContext);

            return;
        }

        foreach ($node->getElementsByTagNameNS(self::NAMESPACE_URI, 'path') as $n) {
            $route = $collection->get($id.'.'.$n->getAttribute('locale'));

            $this->parseConfig($node, $route, $templateContext);
        }
    }

    private function parseConfig(\DOMElement $node, Route $route, array $templateContext): void
    {
        switch ($node->localName) {
            case 'template-route':
                $route
                    ->setDefault('_controller', TemplateController::class)
                    ->setDefault('template', $node->getAttribute('template'))
                    ->setDefault('context', $templateContext)
                    ->setDefault('maxAge', (int) $node->getAttribute('max-age') ?: null)
                    ->setDefault('sharedAge', (int) $node->getAttribute('shared-max-age') ?: null)
                    ->setDefault('private', $node->hasAttribute('private') ? XmlUtils::phpize($node->getAttribute('private')) : null)
                ;
                break;
            case 'redirect-route':
                $route
                    ->setDefault('_controller', RedirectController::class.'::redirectAction')
                    ->setDefault('route', $node->getAttribute('redirect-to-route'))
                    ->setDefault('permanent', self::getBooleanAttribute($node, 'permanent'))
                    ->setDefault('keepRequestMethod', self::getBooleanAttribute($node, 'keep-request-method'))
                    ->setDefault('keepQueryParams', self::getBooleanAttribute($node, 'keep-query-params'))
                ;

                if (\is_string($ignoreAttributes = XmlUtils::phpize($node->getAttribute('ignore-attributes')))) {
                    $ignoreAttributes = array_map('trim', explode(',', $ignoreAttributes));
                }

                $route->setDefault('ignoreAttributes', $ignoreAttributes);
                break;
            case 'url-redirect-route':
                $route
                    ->setDefault('_controller', RedirectController::class.'::urlRedirectAction')
                    ->setDefault('path', $node->getAttribute('redirect-to-url'))
                    ->setDefault('permanent', self::getBooleanAttribute($node, 'permanent'))
                    ->setDefault('scheme', $node->getAttribute('scheme'))
                    ->setDefault('keepRequestMethod', self::getBooleanAttribute($node, 'keep-request-method'))
                ;
                if ($node->hasAttribute('http-port')) {
                    $route->setDefault('httpPort', (int) $node->getAttribute('http-port') ?: null);
                } elseif ($node->hasAttribute('https-port')) {
                    $route->setDefault('httpsPort', (int) $node->getAttribute('https-port') ?: null);
                }
                break;
            case 'gone-route':
                $route
                    ->setDefault('_controller', RedirectController::class.'::redirectAction')
                    ->setDefault('route', '')
                ;
                if ($node->hasAttribute('permanent')) {
                    $route->setDefault('permanent', self::getBooleanAttribute($node, 'permanent'));
                }
                break;
        }
    }

    private static function getRealSchemePath(string $schemePath): string
    {
        return 'file:///'.str_replace('\\', '/', realpath($schemePath));
    }

    private static function getBooleanAttribute(\DOMElement $node, string $attribute): bool
    {
        return $node->hasAttribute($attribute) ? XmlUtils::phpize($node->getAttribute($attribute)) : false;
    }
}
