UPGRADE FROM 2.0 to 2.1
=======================

* assets_base_urls and base_urls merging strategy has changed

    Unlike most configuration blocks, successive values for
    ``assets_base_urls`` will overwrite each other instead of being merged.
    This behavior was chosen because developers will typically define base
    URL's for each environment. Given that most projects tend to inherit
    configurations (e.g. ``config_test.yml`` imports ``config_dev.yml``)
    and/or share a common base configuration (i.e. ``config.yml``), merging
    could yield a set of base URL's for multiple environments.

