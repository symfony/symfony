# Symfony OpenAPI

The OpenApi component provides a user-friendly, object-oriented API to build
OpenAPI specifications using PHP.

## Sponsor

The Symfony OpenApi component is [backed][1] by [Selency][2].

Selency is a team of 80 people, each more committed than the last to
promoting second-hand goods and doing something positive for our planet.
Open-Source is perfectly aligned with our values, leading us to contribute
back to the Symfony ecosystem through a series of components.

Help Symfony by [sponsoring][3] its development!

## Usage

Symfony OpenApi implements the OpenApi standard in its version 3.1. It allows to build
documentations using PHP objects, giving more flexibility and reusability to OpenApi
definitions. All features of OpenApi 3.1 are supported.

### Writing documentation

The OpenApi component provides object-oriented PHP tools to build the documentation
definition:

```php
use Symfony\Component\OpenApi\Documentation\AbstractDocumentation;

class Documentation extends AbstractDocumentation
{
    public function getIdentifier(): string
    {
        return 'myapi';
    }

    public function getVersion(): string
    {
        return '1.3.4';
    }

    public function configure(DocumentationConfigurator $doc): void
    {
        $doc->info($this->openApi->info()
            ->title('Monolith API')
            ->description(file_get_contents(__DIR__.'/Resources/info_description.md'))
            ->contact(name: 'API support', url: 'https://symfony.com', email: 'contact@symfony.com')
            ->specificationExtension('x-logo', [
                'url' => 'https://symfony.com/logos/symfony_black_02.png',
                'altText' => 'Symfony logo',
            ])
            ->license('MIT')
        );

        $doc->externalDocs(url: 'https://github.com/symfony/openapi', description: 'OpenApi component');

        $doc->server($this->openApi->server('https://api.symfony.local')->description('Local'))
            ->server($this->openApi->server('https://api.symfony-staging.com')->description('Staging'))
            ->server($this->openApi->server('https://api.symfony.com')->description('Prod'));

        $doc->securityRequirement(self::REF_SECURITY_USER_JWT);

        $doc->path('/health', $this->openApi->pathItem()
            ->get($this->openApi->operation()
                ->tag('Health')
                ->operationId('app.health.check')
                ->summary('Health check')
                ->description('Check the API is up and available.')
                ->securityRequirement(null)
                ->responses($this->openApi->responses()
                    ->response('200', $this->openApi->response()
                        ->description('When the API is up and available.')
                        ->content('application/json', $this->openApi->schema()
                            ->property('name', $this->openApi->schema()->type('string')->description('Name for this API')->example('Selency API'))
                            ->property('env', $this->openApi->schema()->type('string')->description('Current environment of this instance of the API')->example('prod'))
                        )
                    )
                    ->response('500', $this->openApi->response()->description('When the API is unavailable due to a backend problem.'))
                )
            )
        );

        // ...
    }
}

// Build a read-only model representing the documentation
$compiler = new DocumentationCompiler();
$openApiDefinition = $compiler->compile($doc);

// Compile it as YAML or JSON for usage in other tools
$openApiYaml = (new Dumper\YamlDumper())->dump($openApiDefinition);
$openApiJson = (new Dumper\JsonDumper())->dump($openApiDefinition);
```

### Splitting documentations in multiple files

The component provides a concept of Partial documentations to allow splitting the documentation
in multiple files for readability:

```php
// HealthDocumentation.php
use Symfony\Component\OpenApi\Documentation\PartialDocumentationInterface;

#[AutoconfigureTag('app.partial_documentation')]
class HealthDocumentation implements PartialDocumentationInterface
{
    public function __construct(private OpenApiBuilderInterface $openApi)
    {
    }

    public function configure(DocumentationConfigurator $doc): void
    {
        $doc->path('/health', $this->openApi->pathItem()
            ->get($this->openApi->operation()
                ->tag('Health')
                ->operationId('app.health.check')
                ->summary('Health check')
                ->description('Check the API is up and available. Mostly used by the infrastructure to check for readiness.')
                ->securityRequirement(null)
                ->responses($this->openApi->responses()
                    ->response('200', $this->openApi->response()
                        ->description('When the API is up and available.')
                        ->content('application/json', $this->openApi->schema()
                            ->property('name', $this->openApi->schema()->type('string')->description('Name for this API')->example('Selency API'))
                            ->property('env', $this->openApi->schema()->type('string')->description('Current environment of this instance of the API')->example('prod'))
                        )
                    )
                    ->response('500', $this->openApi->response()->description('When the API is unavailable due to a backend problem.'))
                )
            )
        );
    }
}


// Documentation.php
class Documentation extends AbstractDocumentation
{
    private iterable $partialsDocs;

    public function __construct(
        private Builder\OpenApiBuilder $openApi,
        #[TaggedIterator(tag: 'app.partial_documentation')] iterable $partialsDocs,
    ) {
        $this->partialsDocs = $partialsDocs;
    }

    public function getIdentifier(): string
    {
        return 'myapi';
    }

    public function getVersion(): string
    {
        return '1.3.4';
    }

    public function configure(DocumentationConfigurator $doc): void
    {
        $doc->info($this->openApi->info()
            ->title('Monolith API')
            ->description(file_get_contents(__DIR__.'/Resources/info_description.md'))
            ->contact(name: 'API support', url: 'https://symfony.com', email: 'contact@symfony.com')
            ->specificationExtension('x-logo', [
                'url' => 'https://symfony.com/logos/symfony_black_02.png',
                'altText' => 'Symfony logo',
            ])
            ->license('MIT')
        );

        // ...

        // Apply partial documentations
        foreach ($this->partialsDocs as $partialsDoc) {
            $partialsDoc->configure($doc);
        }
    }
}
```

### Store documentation close to your application code

The OpenApi component provides two interfaces to help maintaining documentation by storing it close
to your code:

* `SelfDescribingSchemaInterface` can be implemented by classes describing a schema (request, response, payloads, ...) ;
* `SelfDescribingQueryParametersInterface` can be implemented by classes describing a list of query parameters ;

These interfaces are especially useful when using objects to handle inputs and outputs of your API:

```php
class AuthRegisterPayload implements SelfDescribingSchemaInterface
{
    #[Assert\Email(mode: Email::VALIDATION_MODE_STRICT)]
    #[Assert\NotBlank]
    public $email;

    #[Assert\Type(type: 'string')]
    #[Assert\NotBlank]
    public $firstName;

    #[Assert\Type(type: 'string')]
    #[Assert\NotBlank]
    public $lastName;

    #[Assert\Type(type: 'string')]
    #[Assert\NotBlank]
    public $password;

    public static function describeSchema(SchemaConfigurator $schema, OpenApiBuilderInterface $openApi): void
    {
        $schema
            ->title('AuthRegister')
            ->required(['email', 'firstName', 'lastName', 'password'])
            ->property('email', $openApi->schema()
                ->type('string')
                ->description('User\'s email')
                ->example('john.doe@domain.com')
            )
            ->property('firstName', $openApi->schema()
                ->type('string')
                ->description('User\'s first name')
                ->example('John')
            )
            ->property('lastName', $openApi->schema()
                ->type('string')
                ->description('User\'s last name')
                ->example('Doe')
            )
            ->property('password', $openApi->schema()
                ->type('string')
                ->description('User\'s plaintext password')
            )
        ;
    }
}
```

You can then load these self-describing schemas/query params classes by providing the
dedicated loader during compilation:

```php
// Build a read-only model representing the documentation
$compiler = new DocumentationCompiler([
    new \Symfony\Component\OpenApi\Loader\SelfDescribingSchemaLoader([
        AuthRegisterPayload::class,
    ])
]);

$openApiDefinition = $compiler->compile($doc);
```

> **Note**: in a Symfony application, SelfDescribingSchemaInterface and
> SelfDescribingQueryParametersInterface class are automatically added to the compiler,
> you don't need to do anything.

And use them in your definitions:

```php
class Documentation extends AbstractDocumentation
{
    // ...

    public function configure(DocumentationConfigurator $doc): void
    {
        // ...

        $doc->path('/auth/register', $this->openApi->pathItem()
            ->post($this->openApi->operation()
                ->tag('Auth')
                ->operationId('app.auth.register')
                ->summary('Auth registration')
                ->description('Register as a user.')
                ->securityRequirement(null)
                ->requestBody($this->openApi->requestBody()
                    ->content('application/json', AuthRegisterPayload::class)
                )
                ->responses($this->openApi->responses()
                    ->response('200', $this->openApi->response()
                        ->content('application/json', AuthRegisterOutput::class)
                    )
                )
            )
        );

        // ...
    }
}
```

## Resources

* [Contributing](https://symfony.com/doc/current/contributing/index.html)
* [Report issues](https://github.com/symfony/symfony/issues) and
  [send Pull Requests](https://github.com/symfony/symfony/pulls)
  in the [main Symfony repository](https://github.com/symfony/symfony)

[1]: https://symfony.com/backers
[2]: https://www.selency.fr
[3]: https://symfony.com/sponsor
