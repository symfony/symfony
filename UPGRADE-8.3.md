UPGRADE FROM 8.2 to 8.3
=======================

Symfony 8.3 is a minor release. According to the Symfony release process, there should be no significant
backward compatibility breaks. Minor backward compatibility breaks are prefixed in this document with
`[BC BREAK]`, make sure your code is compatible with these entries before upgrading.
Read more about this in the [Symfony documentation](https://symfony.com/doc/8.3/setup/upgrade_minor.html).

If you're upgrading from a version below 8.2, follow the [8.2 upgrade guide](UPGRADE-8.2.md) first.

SecurityBundle
--------------

 * Deprecate the `roles` option of access control rules:
   - a rule with a single role or attribute uses `is_granted` instead
   - a rule with many roles, in a list or comma-separated, uses `allow_if` or role hierarchy instead
   - a rule with both `roles` and `allow_if` checks its roles in the `allow_if` expression instead

   ```yaml
   # config/packages/security.yaml

   # before
   security:
       access_control:
           - { path: ^/admin, roles: ROLE_ADMIN }
           - { path: ^/reports, roles: [ROLE_ACCOUNTANT, ROLE_AUDITOR] }

   # after
   security:
       access_control:
           - { path: ^/admin, is_granted: ROLE_ADMIN }
           - { path: ^/reports, allow_if: "is_granted('ROLE_ACCOUNTANT') or is_granted('ROLE_AUDITOR')" }
   ```

 * Deprecate the `attributes` option of access control rules, use `request_attributes` instead

   ```yaml
   # config/packages/security.yaml

   # before
   security:
       access_control:
           - { route: export, attributes: { _format: csv }, is_granted: ROLE_EXPORT }

   # after
   security:
       access_control:
           - { route: export, request_attributes: { _format: csv }, is_granted: ROLE_EXPORT }
   ```
