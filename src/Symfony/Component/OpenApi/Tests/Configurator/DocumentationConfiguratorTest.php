<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\OpenApi\Tests\Configurator;

use Symfony\Component\OpenApi\Builder\OpenApiBuilder;
use Symfony\Component\OpenApi\Configurator\DocumentationConfigurator;
use Symfony\Component\OpenApi\Model\ParameterIn;
use Symfony\Component\OpenApi\Tests\Loader\MockSelfDescribingSchema;

class DocumentationConfiguratorTest extends AbstractConfiguratorTestCase
{
    public function testConfigureEmpty(): void
    {
        $configurator = new DocumentationConfigurator();

        $doc = $configurator->build();
        $this->assertSame('3.1.0', $doc->getVersion());
        $this->assertNull($doc->getJsonSchemaDialect());
        $this->assertSame([], $doc->getSpecificationExtensions());

        $info = $doc->getInfo();
        $this->assertNotNull($info);
        $this->assertSame('', $info->getTitle());
        $this->assertSame('', $info->getVersion());
        $this->assertNull($info->getSummary());
        $this->assertNull($info->getDescription());
        $this->assertNull($info->getTermsOfService());
        $this->assertNull($info->getContact());
        $this->assertNull($info->getLicense());
        $this->assertSame([], $info->getSpecificationExtensions());

        $this->assertNull($doc->getServers());
        $this->assertNull($doc->getPaths());
        $this->assertNull($doc->getWebhooks());
        $this->assertNull($doc->getComponents());
        $this->assertSame([], $doc->getSecurity());
        $this->assertNull($doc->getTags());
        $this->assertNull($doc->getExternalDocs());
    }

    public function testConfigureFull(): void
    {
        $builder = new OpenApiBuilder();

        /*
         * Build the documentation using the configurator
         */
        $doc = (new DocumentationConfigurator())
            ->version('3.1.0')
            ->jsonSchemaDialect('https://selency.fr')
            ->externalDocs('https://selency.fr', 'Description example', ['x-key' => 'value'])
            ->securityRequirement('product_auth', ['product:read', 'product:write'])
            ->specificationExtension('x-key', 'value')

            ->info($builder->info()
                ->title('Symfony OpenApi')
                ->version('1.2.3')
                ->summary('Summary example')
                ->description('Description example')
                ->termsOfService('https://symfony.com/tos')
                ->contact(
                    name: 'Titouan Galopin',
                    url: 'https://selency.fr',
                    email: 'contact@selency.fr',
                    specificationExtensions: ['x-key' => 'value'],
                )
                ->license(
                    name: 'MIT License',
                    identifier: 'MIT',
                    url: 'https://opensource.org/licenses/MIT',
                    specificationExtensions: ['x-key' => 'value']
                )
                ->specificationExtension('x-key', 'value')
            )

            ->server('http://localhost')
            ->server($builder->server('https://staging.selency.fr')
                ->description('Staging')
                ->variable('token', 'token-staging', specificationExtensions: ['x-key' => 'value'])
                ->variable('version', 'v2', 'Version to use', ['v1', 'v2'], ['x-key' => 'value'])
                ->specificationExtension('x-key', 'value')
            )
            ->server($builder->server('https://selency.fr')
                ->description('Prod')
                ->variable('token', 'token-prod', specificationExtensions: ['x-key' => 'value'])
                ->variable('version', 'v1', 'Version to use', ['v1', 'v2'], ['x-key' => 'value'])
                ->specificationExtension('x-key', 'value')
            )

            ->components($builder->components()
                ->schema('mock_schema', MockSelfDescribingSchema::class)
                ->schema('empty_schema', $builder->schema())
                ->response('created', $builder->response()->description('When the user was successfully created'))
                ->pathItem('health', $builder->pathItem()->description('Health check'))
                ->link('link', $builder->link()->server('http://localhost'))
                ->parameter('embeds', $builder->parameter('embeds')
                    ->required(false)
                    ->in(ParameterIn::QUERY)
                    ->description('When providing a list of embeds, the API will fetch the values of the links in the payload to provide them in addition to the main payload.')
                )
                ->callback('callback', $builder->callbackRequest()
                    ->expression('{$request.query.queryUrl}')
                    ->definition($builder->reference('health'))
                )
                ->securityScheme('JWT', $builder->securityScheme()->type('JWT')->scheme('http')->name('JWT'))
            )

            ->tag($builder->tag()
                ->name('User')
                ->description('User related endpoints')
                ->externalDocs('https://selency.fr', 'Description example', ['x-key' => 'value'])
                ->specificationExtension('x-key', 'value')
            )
            ->tag('Product')

            ->path('/users', $builder->pathItem()
                ->get($builder->operation()
                    ->description('Get a list of users')
                    ->parameter($builder->parameter('username')
                        ->in(ParameterIn::QUERY)
                        ->required(true)
                        ->description('When provided, the list will be filtered by username')
                    )
                    ->parameter($builder->parameter('Accept')
                        ->in(ParameterIn::HEADER)
                        ->required(true)
                        ->description('When provided, the list will be filtered by username')
                        ->schema($builder->schema()->enum([
                            'application/vnd.selency.v1+json',
                            'application/vnd.selency.v2+json',
                        ]))
                        ->example('foo', $builder->example()->value('{"foo": "bar"}'))
                    )
                    ->responses($builder->responses()
                        ->response('200', $builder->response()
                            ->description('The list of users retrieved')
                            ->content('application/json', $builder->schema()
                                ->property('id', 'int')
                                ->property('username', 'string')
                                ->property('email', 'string')
                                ->required(['id', 'username', 'email'])
                            )
                        )
                    )
                )
                ->post($builder->operation()
                    ->description('Create a new user')
                    ->parameter($builder->reference('api_version_header'))
                    ->responses($builder->responses()->response('204', $builder->reference('created')))
                )
                ->put($builder->operation()
                    ->description('Create a new user')
                    ->parameter($builder->reference('api_version_header'))
                    ->responses($builder->responses()->response('204', $builder->reference('created')))
                )
            )
        ;

        /*
         * Check the built documentation
         */
        $built = $doc->build();
        $this->assertSame('3.1.0', $built->getVersion());
        $this->assertSame('https://selency.fr', $built->getJsonSchemaDialect());
        $this->assertSame(['x-key' => 'value'], $built->getSpecificationExtensions());

        $info = $built->getInfo();
        $this->assertNotNull($info);
        $this->assertSame('Symfony OpenApi', $info->getTitle());
        $this->assertSame('1.2.3', $info->getVersion());
        $this->assertSame('Summary example', $info->getSummary());
        $this->assertSame('Description example', $info->getDescription());
        $this->assertSame('https://symfony.com/tos', $info->getTermsOfService());
        $this->assertNotNull($info->getContact());
        $this->assertSame('Titouan Galopin', $info->getContact()->getName());
        $this->assertSame('https://selency.fr', $info->getContact()->getUrl());
        $this->assertSame('contact@selency.fr', $info->getContact()->getEmail());
        $this->assertSame(['x-key' => 'value'], $info->getContact()->getSpecificationExtensions());
        $this->assertNotNull($info->getLicense());
        $this->assertSame('MIT License', $info->getLicense()->getName());
        $this->assertSame('MIT', $info->getLicense()->getIdentifier());
        $this->assertSame('https://opensource.org/licenses/MIT', $info->getLicense()->getUrl());
        $this->assertSame(['x-key' => 'value'], $info->getLicense()->getSpecificationExtensions());
        $this->assertSame(['x-key' => 'value'], $info->getSpecificationExtensions());

        $externalDoc = $built->getExternalDocs();
        $this->assertNotNull($externalDoc);
        $this->assertSame('https://selency.fr', $externalDoc->getUrl());
        $this->assertSame('Description example', $externalDoc->getDescription());
        $this->assertSame(['x-key' => 'value'], $externalDoc->getSpecificationExtensions());

        $security = $built->getSecurity();
        $this->assertNotNull($security);
        $this->assertCount(1, $security);
        $this->assertSame('product_auth', $security[0]->getName());
        $this->assertSame(['product:read', 'product:write'], $security[0]->getConfig());

        $servers = $built->getServers();
        $this->assertNotNull($servers);
        $this->assertCount(3, $servers);

        $this->assertSame('http://localhost', $servers[0]->getUrl());
        $this->assertNull($servers[0]->getDescription());
        $this->assertNull($servers[0]->getVariables());
        $this->assertSame([], $servers[0]->getSpecificationExtensions());

        $this->assertSame('https://staging.selency.fr', $servers[1]->getUrl());
        $this->assertSame('Staging', $servers[1]->getDescription());
        $this->assertCount(2, $servers[1]->getVariables());
        $this->assertSame(['x-key' => 'value'], $servers[1]->getSpecificationExtensions());
        $this->assertSame('token-staging', $servers[1]->getVariables()['token']->getDefault());
        $this->assertNull($servers[1]->getVariables()['token']->getDescription());
        $this->assertNull($servers[1]->getVariables()['token']->getEnum());
        $this->assertSame(['x-key' => 'value'], $servers[1]->getVariables()['token']->getSpecificationExtensions());
        $this->assertSame('v2', $servers[1]->getVariables()['version']->getDefault());
        $this->assertSame('Version to use', $servers[1]->getVariables()['version']->getDescription());
        $this->assertSame(['v1', 'v2'], $servers[1]->getVariables()['version']->getEnum());
        $this->assertSame(['x-key' => 'value'], $servers[1]->getVariables()['version']->getSpecificationExtensions());

        $this->assertSame('https://selency.fr', $servers[2]->getUrl());
        $this->assertSame('Prod', $servers[2]->getDescription());
        $this->assertCount(2, $servers[2]->getVariables());
        $this->assertSame(['x-key' => 'value'], $servers[2]->getSpecificationExtensions());
        $this->assertSame('token-prod', $servers[2]->getVariables()['token']->getDefault());
        $this->assertNull($servers[2]->getVariables()['token']->getDescription());
        $this->assertNull($servers[2]->getVariables()['token']->getEnum());
        $this->assertSame(['x-key' => 'value'], $servers[2]->getVariables()['token']->getSpecificationExtensions());
        $this->assertSame('v1', $servers[2]->getVariables()['version']->getDefault());
        $this->assertSame('Version to use', $servers[2]->getVariables()['version']->getDescription());
        $this->assertSame(['v1', 'v2'], $servers[2]->getVariables()['version']->getEnum());
        $this->assertSame(['x-key' => 'value'], $servers[2]->getVariables()['version']->getSpecificationExtensions());

        $tags = $built->getTags();
        $this->assertNotNull($tags);
        $this->assertCount(2, $tags);

        $this->assertSame('User', $tags[0]->getName());
        $this->assertSame('User related endpoints', $tags[0]->getDescription());
        $this->assertSame(['x-key' => 'value'], $tags[0]->getSpecificationExtensions());
        $this->assertSame('https://selency.fr', $tags[0]->getExternalDocs()->getUrl());
        $this->assertSame('Description example', $tags[0]->getExternalDocs()->getDescription());
        $this->assertSame(['x-key' => 'value'], $tags[0]->getExternalDocs()->getSpecificationExtensions());

        $this->assertSame('Product', $tags[1]->getName());
        $this->assertNull($tags[1]->getDescription());
        $this->assertSame([], $tags[1]->getSpecificationExtensions());
    }
}
