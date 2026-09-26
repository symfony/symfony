KeyManagement bridges
=====================

This directory hosts bridges that connect the KeyManagement component to
specific backends. Each bridge is published as its own Composer package with a
name identifying the backend or protocol it targets, such as
`symfony/aws-key-management`, `symfony/hashicorp-vault-key-management`,
`symfony/azure-keyvault-key-management` or `symfony/kmip-key-management`.
These packages provide `symfony-key-management-bridge`.

A bridge typically contains:

 * A KMS class such as `KmipKms` implementing `Symfony\Component\KeyManagement\EncrypterInterface`,
   and optionally `Symfony\Component\KeyManagement\DataKeyGeneratorInterface` if the
   backend can produce envelope-encryption data keys.
 * A README describing the supported authentication and configuration.
 * Tests using mock HTTP clients, in-memory equivalents, or a local protocol server.

Bridges are encouraged to expose their constructor as the primary wiring
point. Lazy instantiation, when needed, is delegated to the host framework
(the DI component supports lazy services natively) or to standalone callers
using `ReflectionClass::newLazyProxy()`.
