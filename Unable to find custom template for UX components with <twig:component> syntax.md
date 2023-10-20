Configure a new folder for Twig


twig:
    paths:
        - '%kernel.project_dir%/templates'
    resources:
        - '%kernel.project_dir%/components'

Autoloading Configuration:

"autoload": {
    "psr-4": {
        "App\\": "src/",
        "Component\\": "components/"
    }
}

Check Template File Names:
    components/button.twig

Clear Symfony Cache
php bin/console cache:clear

Test:
<twig:button></twig:button>

