UPGRADE FROM 7.3 to 7.4
=======================

Symfony 7.4 is a minor release. According to the Symfony release process, there should be no significant
backward compatibility breaks. Minor backward compatibility breaks are prefixed in this document with
`[BC BREAK]`, make sure your code is compatible with these entries before upgrading.
Read more about this in the [Symfony documentation](https://symfony.com/doc/7.4/setup/upgrade_minor.html).

If you're upgrading from a version below 7.3, follow the [7.3 upgrade guide](UPGRADE-7.3.md) first.
string in `UserInterface::getUserIdentifier()`

SecurityBundle
--------------

 * Deprecate XML-configured custom authenticators and providers under security namespace; they must now have their own:

   ```diff
   <srv:container xmlns="http://symfony.com/schema/dic/security"
               xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
               xmlns:srv="http://symfony.com/schema/dic/services"
   +           xmlns:custom="http://example.com/schema"
               xsi:schemaLocation="http://symfony.com/schema/dic/services
        https://symfony.com/schema/dic/services/services-1.0.xsd
        http://symfony.com/schema/dic/security
   -    https://symfony.com/schema/dic/security/security-1.0.xsd">
   +    https://symfony.com/schema/dic/security/security-1.0.xsd
   +    http://example.com/schema http://example.com/schema.xsd">
   +    <!-- the line above can be omitted if the schema does not have a definition -->

        <config>
           <provider name="custom_provider">
   -           <provider-name>
   -               <config key="value"/>
   -           </provider-name>
   +           <custom:provider-name>
   +               <custom:config key="value"/>
   +           </custom:provider-name>
           </provider>
        </config>
   </srv:container>
   ```
