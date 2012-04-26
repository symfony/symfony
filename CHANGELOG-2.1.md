CHANGELOG for 2.1.x
===================

This changelog references the relevant changes (bug and security fixes) made
in 2.1 minor versions.

To get the diff for a specific change, go to https://github.com/symfony/symfony/commit/XXX where XXX is the change hash
To get the diff between two versions, go to https://github.com/symfony/symfony/compare/v2.1.0...v2.1.1

2.1.0
-----

### AbstractDoctrineBundle

 * This bundle has been removed and the relevant code has been moved to the Doctrine bridge

### DoctrineBundle

 * This bundle has been moved to the Doctrine organization
 * added optional `group_by` property to `EntityType` that supports either a
   `PropertyPath` or a `\Closure` that is evaluated on the entity choices
 * The `em` option for the `UniqueEntity` constraint is now optional (and should
   probably not be used anymore).

### MonologBundle

 * This bundle has been moved to its own repository (https://github.com/symfony/MonologBundle)

### SwiftmailerBundle

 * This bundle has been moved to its own repository (https://github.com/symfony/SwiftmailerBundle)
 * moved the data collector to the bridge
 * replaced MessageLogger class with the one from Swiftmailer 4.1.3
