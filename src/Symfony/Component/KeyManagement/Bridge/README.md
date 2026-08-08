KeyManagement bridges
=====================

This directory hosts bridges that connect the KeyManagement component to
specific backends. Each bridge is published as its own Composer package, named
after the vendor it targets followed by the component name, e.g.
`symfony/aws-key-management`, `symfony/vault-key-management`,
`symfony/azure-keyvault-key-management`, ... and typed
`symfony-key-management-bridge`.

A bridge typically contains:

 * A `<Vendor>Kms` class implementing `Symfony\Component\KeyManagement\EncrypterInterface`,
   and optionally `Symfony\Component\KeyManagement\DataKeyGeneratorInterface` if the
   backend can produce envelope-encryption data keys.
 * A README describing the supported authentication and configuration.
 * Tests using mock HTTP clients or in-memory equivalents.

Bridges are encouraged to expose their constructor as the primary wiring
point. Lazy instantiation, when needed, is delegated to the host framework
(the DI component supports lazy services natively) or to standalone callers
using `ReflectionClass::newLazyProxy()`.
