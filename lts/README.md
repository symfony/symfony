Symfony LTS - Enforcing Long Term Supported versions of components
==================================================================

When using the `symfony/symfony` Composer meta-package, you get all Symfony
Components at once, all in the same version. But when using standalone Composer
packages, you might get their dependencies in a different major version (e.g.
`symfony/http-kernel` v2.8 is compatible with `symfony/event-dispatcher` v3.0.)

This is fine when you don't use these transitive dependencies in your own code.
But once you do, you'd better stick to the actual major version that you support.

This special Composer package allows you to enforce that any Symfony Components
you use, transitively or not, stick to *The* major version you decided to be.

Note that this is meant to be used by *root* Composer applications. Library
authors SHOULD NOT use it, except maybe in the `"require-dev"` section of their
`composer.json` files.

Usage
-----

Using the Composer command line:
```sh
composer require symfony/lts 2
```

Or patching your `composer.json` file:
```json
    "require": {
        "symfony/lts": "2"
    }
```

Versioning policy
------------------

There is only one version of this `symfony/lts` package per major Symfony version.
Each version is tagged at the same time than the *last* minor version of
each major release (e.g. `v3` when Symfony `v3.4.0` is out.)

At this same time also, the Composer `branch-alias` is increased to the next
major version number.

(As a corollary, if one wants to use the next *unreleased* major version of Symfony
right now, one should just not use this package at all.)
