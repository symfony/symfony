# Threat model

This file steers the [OSS Scanner](https://github.com/anthropics/oss-scanner). Vulnerabilities found by other means are reported as the [security policy](https://symfony.com/security) describes, to security@symfony.com.

## What this project does

`symfony/symfony` is the monorepo of the Symfony framework: the components (`src/Symfony/Component`), the bridges to Doctrine, Monolog, Twig, PSR-7 and PHPUnit (`src/Symfony/Bridge`), the bundles (`src/Symfony/Bundle`) and the contracts (`src/Symfony/Contracts`). Each package is split to a read-only repository (`splitsh.json`) and installed on its own. The default configuration lives in `FrameworkBundle`, `SecurityBundle` and the bundles that the components ship since 8.2 (`HttpClientBundle`, `MessengerBundle`, `SerializerBundle`, `WebhookBundle`, ...).

The application is the code built on Symfony: web applications and APIs behind a web server or a reverse proxy, or run as long-lived HTTP workers under the CLI SAPI, the workers that consume their messages, their console commands. Its configuration decides which packages, firewalls, authenticators and features are enabled.

## Where untrusted input enters

The attacker is anyone who can send an HTTP request to the application: an anonymous client, an authenticated user reaching for what they may not, a client that forges forwarded headers, a third party that posts to a webhook endpoint. When a Messenger transport signs its messages, whoever can write to the broker is an attacker too. Untrusted:

- Every part of the HTTP request as `Request` reads it: method, request URI, path info, query, headers (`Host`, `Forwarded`, `X-Forwarded-*`, `Cache-Control`, `Content-Type`, `Accept`), cookies, body and uploaded files (name, type, size, content). It reaches the router, the firewall and `access_control`, the argument resolvers of controllers (`#[MapRequestPayload]`, `#[MapQueryString]`, `#[MapQueryParameter]`, `#[MapUploadedFile]`, and the resolvers of enums, dates, UIDs and Doctrine entities), the Serializer and the Validator behind them, the Form component, `HttpCache` and `FragmentListener`.
- What clients send back as proof: session and remember-me cookies, CSRF tokens, login links, signed URIs (`UriSigner`, `#[IsSignatureValid]`, the `_fragment` URIs), bearer and DPoP-bound access tokens, the authorization responses and the ID and logout tokens of `oidc_login`, CAS tickets, the distinguished names of client certificates (`x509`).
- The webhook requests that `WebhookController` hands to the request parsers (`src/Symfony/Component/Webhook/Client`, `src/Symfony/Component/*/Bridge/*/Webhook`).
- What an application hands to a component built for untrusted data: HTML given to `HtmlSanitizer`; addresses, names and header values given to `Mime` and `Mailer`; URLs received from users and requested through `NoPrivateNetworkHttpClient`, with the responses, redirects and DNS answers that come back; YAML parsed with the default flags; expressions checked by `#[Assert\ExpressionSyntax]`; JSON decoded by `JsonStreamer` or queried by `JsonPath`; XML and CSV decoded by the Serializer; values checked by the Validator constraints; paths built with `Filesystem\Path`.
- The messages a worker reads from a transport with the `sign` option (`SigningSerializer`): the signature is what makes them trusted.
- The envelopes and stored data keys that `KeyManagement` reads back from a database or a file: encryption at rest exists because that storage can leak or be altered.

What is trusted:

- The configuration and the code of the application: `config/`, the environment variables, `APP_SECRET` (`kernel.secret`), the routes and their requirements, the service definitions, templates, expressions, validation and serialization metadata, translation files.
- The proxies listed in `trusted_proxies`, for the headers listed in `trusted_headers` only, and the hosts that `trusted_hosts` matches.
- The storage the application owns, apart from what `KeyManagement` encrypts: database, cache pools, session storage, lock stores, and the transports that do not sign their messages. Reading back what is stored there is trusted, unless an attacker can choose the bytes that Symfony writes.
- The servers the application configures: identity providers with their discovery documents and key sets, CAS servers, mail and SMS providers, translation providers, key management services.
- The operator: console commands, their arguments and their environment.

## Security properties that must hold

- The router, the firewall map and `access_control` agree on the path and the method of a request. No encoding, dot segment, trailing slash, script name, host placeholder or `HEAD` request reaches a route that a rule denies, and a client never chooses the target of a sub-request, which the firewall does not check.
- An authenticator authenticates a user only on valid credentials for its own firewall, runs the user checker and migrates the session. A session, a remember-me cookie or a login link stops authenticating once what it is bound to changes (`signature_properties`, `EquatableInterface`, `user_checker_on_refresh`).
- Secrets and the values derived from them are compared in constant time, and a signed value is accepted only for its purpose and until it expires: signed and fragment URIs, login links, remember-me cookies, CSRF tokens, webhook and Messenger signatures.
- OIDC and OAuth2: a token is accepted only when the configured provider issued it, signed with an allowed algorithm when it is a JWT, for the expected `iss` and `aud`, and before `exp`; `oidc_login` checks `state`, `nonce` and PKCE; a DPoP proof is bound to the method, the URL and the access token of its request, and is used once.
- A webhook request parser rejects a request whose signature is missing or wrong once a secret is configured, and never reads the HMAC algorithm from the request. A `SigningSerializer` refuses a message without a valid signature before decoding it.
- Absolute URLs and redirects take the host, scheme and port from forwarded headers only when a trusted proxy sent them, and from `Host` only when it matches `trusted_hosts`, when the application sets it. A redirect target read from the request (`_target_path`, `_failure_path`, the referer) stays on the host of the request or the domain of the session cookie (`HttpUtils`).
- `NoPrivateNetworkHttpClient` never connects to an address of `IpUtils::PRIVATE_SUBNETS`, whatever the URL form, the redirects and the DNS answers, and connects to the address it checked. `HttpClient` sends the credentials of a request or of a scope to that origin only.
- The output of `HtmlSanitizer` holds no script, event handler or `javascript:` URL with any configuration built from `allowSafeElements()` or `allowStaticElements()`, and its links and media point only to the allowed schemes and hosts.
- No CR, LF or other control character reaches an email header, a header parameter or an SMTP command from an address, a name or a header value, and a recipient never becomes an option of `sendmail`.
- The Serializer instantiates only the target type or a class of its discriminator map, never builds a file object that points at a server file from request data, and never writes an attribute that groups or `#[Ignore]` exclude. `unserialize()` of data that may come from outside passes `allowed_classes`.
- The parsers of untrusted input load no external entity and no local file, and instantiate nothing: YAML with the default flags builds no object and reads no constant, XML is parsed without a document type. Their cost stays close to linear in the size of the input, with bounded nesting.
- A path built from untrusted segments stays under its base directory (`Filesystem\Path`, and the stores that build file names from keys or identifiers).
- With `kernel.debug` off, error pages and error responses expose no exception message, trace, file path or secret, beyond the messages that an exception marks as meant for users.
- `KeyManagement` rejects any envelope or stored data key that was altered: ciphertext, AAD, key identifier, wrapped key or format.

## Components that matter most

- `HttpFoundation`: `Request` (host, scheme, port, client IP, base URL and path info, trusted proxies and hosts), `HeaderUtils`, `IpUtils`, `UriSigner`, the request matchers (`ChainRequestMatcher`, `RequestMatcher\*`), `BinaryFileResponse` (`X-Sendfile`, `X-Accel-Mapping`), sessions, cookies and `UploadedFile`.
- `Routing`: the matchers (`UrlMatcher`, `CompiledUrlMatcher` and its dumper, `RedirectableUrlMatcher`) and `UrlGenerator` (encoding of parameters, dot segments, requirements, host and scheme).
- `Security/Http`: the firewall listeners, `AccessListener` and `AccessMap`, the authenticators with their success and failure handlers, `HttpUtils`, `SwitchUserListener`, logout, remember-me, login links, the access token handlers (OIDC, OAuth2 introspection, CAS), DPoP, `OidcLoginAuthenticator` and back-channel logout, `X509Authenticator`, `IsGrantedAttributeListener`.
- `Security/Core` (voters, `AccessDecisionManager`, role hierarchy, `SignatureHasher`), `Security/Csrf` (`CsrfTokenManager`, `SameOriginCsrfTokenManager`), `SecurityBundle` (the defaults and the factories of the firewall configuration), `PasswordHasher`, and the LDAP authenticators and user provider (`src/Symfony/Component/Ldap/Security`).
- `HttpKernel`: `HttpCache` with `Esi`, `Ssi` and `Store`, `FragmentListener` and the fragment renderers, the argument value resolvers, the controller attribute listeners, the error listener.
- `Webhook`, and every request parser of the Mailer and Notifier bridges.
- `Serializer` (`ObjectNormalizer`, `AbstractObjectNormalizer`, `DataUriNormalizer`, discriminator maps, `XmlEncoder`, `CsvEncoder`), the readers of `JsonStreamer`, and the Validator constraints that applications rely on to filter input: `Email`, `Url`, `Ip`, `Cidr`, `Hostname`, `File`, `Image`, `NoSuspiciousCharacters`, `ExpressionSyntax`, `Yaml`, `Json`.
- `HtmlSanitizer`.
- `HttpClient`: `NoPrivateNetworkHttpClient`, `ScopingHttpClient`, redirects and credentials in `CurlHttpClient`, `NativeHttpClient` and `AmpHttpClient`, `CachingHttpClient`.
- `Mime` (headers, `Address`, parameters) and `Mailer` (the SMTP and sendmail transports, the headers that the API transports build).
- `Messenger`: `SigningSerializer`, `InteropSerializer` and `ClaimCheckSerializer`.
- The parsers: `Yaml`, the lexer and the parser of `ExpressionLanguage`, `JsonPath`, `Config\Util\XmlUtils`.
- `KeyManagement`, experimental: `EnvelopeEncrypter`, `StoredEnvelopeEncrypter`, the local services (`SodiumKms`, `OpenSslKms`, `SealedBoxKms`), the blind indexes and the bridges.
- The Twig bridge (form themes, the `is_safe` declarations of the extensions, `HtmlSanitizerExtension`) and the Doctrine bridge (`EntityValueResolver`, `UniqueEntityValidator`, `DoctrineTokenProvider`, `EntityUserProvider`).

Less important but in scope: `Cache` (key validation, the SQL of `PdoAdapter` and `DoctrineDbalAdapter`, `PhpFilesAdapter` with values that come from requests), `Lock`, `RateLimiter`, `Translation`, `String`, `Uid`, `Intl`, `AssetMapper` in production, `Workflow`, `Runtime` (an environment read from the request), `Process`, `Filesystem`, the PSR-7 and Monolog bridges, `DomCrawler`, `CssSelector`. Deprecated code paths are in scope as long as they ship.

## How to exercise it

The image has PHP 8.4 with the extensions and ini values of the unit-tests workflow, the dependencies of the root `composer.json` in `vendor/`, and PHPUnit in `.phpunit/`. Run the tests with the `phpunit` script at the root of the repository, which runs the PHPUnit version the CI runs. Give it one package at a time, since it starts the suites of all the packages under a directory at once:

    ./phpunit --exclude-group integration --exclude-group network --exclude-group transient --exclude-group tty --exclude-group benchmark --exclude-group intl-data src/Symfony/Component/HttpFoundation
    ./phpunit src/Symfony/Component/Security/Http --filter OidcLoginAuthenticatorTest

- Each package has its tests in its `Tests` directory. A reproducer for a class goes in the test case of that class.
- `src/Symfony/Bundle/SecurityBundle/Tests/Functional` and `src/Symfony/Bundle/FrameworkBundle/Tests/Functional` boot kernels configured in their `app` directories and send requests through the test client. `FormLoginTest.php`, `AccessTokenTest.php` and `OidcBackChannelLogoutTest.php` show it end to end. A reproducer that needs the router, the firewall and the kernel together goes there.
- A webhook request parser is tested by a test case that extends `Symfony\Component\Webhook\Test\AbstractRequestParserTestCase`, with the request bodies in `Tests/Webhook/Fixtures`. `php .github/sa-tools/check-hardening-tests.php` fails when a parser has no test rejecting a forged request, or when an `__unserialize()` has no test against a `__toString()` gadget.
- The `integration` group needs servers (Redis, Memcached, MongoDB, RabbitMQ, LDAP, databases) and the `network` group needs the Internet: none of them is available in the scan. The `transient` group holds timing-sensitive tests that the CI skips too. Each group takes its own `--exclude-group`: PHPUnit 13 does not split a comma-separated list.
- The scan runs as root, so the tests that expect a permission to be denied fail, in `Cache`, `Config`, `Console`, `DependencyInjection`, `Filesystem`, `AssetMapper` and `FrameworkBundle`. So do a few tests outside the `network` group that resolve a host or wait for a connection to time out (`testMaxConnectDuration` and `testRetryWithDnsIssue` in `HttpClient`, `SocketStreamTest` in `Mailer`). These failures are expected.

## How we rate severity

Severity follows the CVSS score computed when the CVE is requested. The vulnerability classes already met, with the shapes ruled out, are in `.agents/skills/symfony-security-review/hardening-families.md`.

- Critical: code execution, or authentication as any user, for an unauthenticated client in a default or documented configuration.
- High: getting the response of a route that `access_control` or a firewall denies; accepting a forged or incomplete token; reading files of the server; sending credentials to an origin they are not meant for; injecting options into a process; a single small request or document that holds a process for seconds or crashes it.
- Medium: running a denied handler without getting its response (`HEAD`), XSS through a sanitizer or an escaping bypass, header or SMTP command injection, a forged webhook event, an SSRF filter bypass through some address forms, URLs or redirects toward a host chosen by the client, a bypass that needs a non-default but documented configuration.
- Low: a check skipped in a narrow configuration, defense in depth, disclosure of non-sensitive internals.

For calibration, the published advisories were rated as follows:

- GHSA-6h46-9jf5-q59x, `failure_forward` forwarded a failed login to a path read from the request, outside the firewall: high.
- GHSA-3rg7-wf37-54rm, a `PATH_INFO` parsed into a path without a leading `/` bypassed `access_control` rules: high.
- GHSA-29fc-p6c4-24cg, `OidcTokenHandler` accepted tokens without `aud`, `iss` or `exp`: high.
- GHSA-h38r-xrmh-cwhr, the Serializer built `UploadedFile` objects from request data, pointing at any file of the server: high.
- GHSA-gq53-v8w2-gjhj, `ScopingHttpClient` sent the credentials of its default scope to the host of a network-path reference: high.
- GHSA-xx3c-qf5g-hc39, a recipient starting with `-` injected an option into `sendmail`: high.
- GHSA-r925-mgvr-h9cc, catastrophic backtracking in `HeaderUtils::split()`, reached through the `Cache-Control` header of any request: high.
- GHSA-m7v2-7gxm-vc2v, `server:log` listened on every interface and unserialized what it received: high.
- GHSA-cjfx-x9pc-q4rx, in HTTP workers running under the CLI SAPI, the `Proxy` header of a request chose the proxy of outgoing requests: high.
- GHSA-jrf8-gvm4-7cgh, `HEAD` requests bypassed the `methods` option of `access_control` rules and firewalls: medium.
- GHSA-5mx8-mwrp-5693, a request URI starting with `//` put a host chosen by the client in the base URL, and so in the generated URLs: medium.
- GHSA-qpmx-3rfj-7rhv, CRLF in the quoted local part of a `Mime\Address` injected headers and SMTP commands: medium.
- GHSA-x5qj-865h-mgvm, `HtmlSanitizer` did not sanitize the URL attributes of `<object>`, `<applet>`, `<iframe>` and `<img>`: medium.
- GHSA-59f3-vp2f-mp9w, the Mailtrap webhook parser never verified the signature: medium.
- GHSA-38cx-cq6f-5755, `IpUtils::PRIVATE_SUBNETS` missed the IPv6 transition forms: medium.
- GHSA-8f57-m8wj-x65w, programmatic logins skipped `checkPostAuth()` of the user checker: low.
- GHSA-mrqx-rp3w-jpjp, `Request` parsed URLs with special characters unlike browsers, so a check based on it let redirects to another domain through: low.

## Anything to leave alone

- What the security policy excludes: debug tools that must never run in production (the web profiler, `DebugBundle`, `VarDumper`, anything enabled by `APP_DEBUG=1` or by an environment other than `prod`), test helpers (classes named `Mock*`, the `Test` namespaces, `src/Symfony/Bridge/PhpUnit`), and hardening: route enumeration, login throttling bypasses, timing attacks, a missing `#[\SensitiveParameter]`, a denial of service that needs sustained traffic. A debug tool that listens on an interface reachable from other hosts is in scope, as GHSA-m7v2-7gxm-vc2v shows.
- What the documentation leaves to the application: deserializing untrusted bytes with `unserialize()`, `PhpSerializer` or `Yaml::PARSE_OBJECT`; ESI and SSI, which trust the backend; `template_from_string()` and `|raw`; evaluating expressions written by users; regular expressions and route requirements built from input; `Process::fromShellCommandline()` with input; URLs received from users and requested without `NoPrivateNetworkHttpClient`; CSV formulas, escaped only with `csv_escape_formulas`; an empty webhook secret, which disables the verification, and webhooks authenticated by IP.
- Preconditions that are already a compromise: knowing `APP_SECRET`, writing to the cache, the session storage, the database or a transport that does not sign, controlling the configuration or the environment, local access.
- Bugs in the dependencies (Twig, Doctrine, Monolog, PHP and its extensions, `web-token/jwt-library`, `egulias/email-validator`, `masterminds/html5`, the SDKs of the providers): they are separate projects. The way Symfony uses them is in scope.
- `Tests` directories, `.github/` and `.agents/`.

## Reports

Include a reproducer written as a PHPUnit test in the `Tests` directory of the affected package, or as a functional test when the issue needs the firewall or the kernel, the configuration it needs when it is not the default one, and a patch against the scanned branch. The security team ports the fix to the maintained branches.
