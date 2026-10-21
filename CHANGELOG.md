# Change history

## 4.0.1

- Removes the observer that inserted product and account links.
- Uses explicit template code as the only method of displaying those links.
- Rewrites Bootstrap integration instructions with exact files and paste locations.
- Preserves settings, subscriptions, and existing version 4 template code.

## 4.0.0

- Supports Zen Cart 2.1.0 and 2.2.2; excludes 2.0.0 and 2.0.1.
- Converts the loose-file alpha release into one encapsulated Plugin Manager package.
- Preserves the existing subscription table, configuration values, and legacy unsubscribe codes.
- Replaces automatic admin-page installation with a scripted installer.
- Adds installation, upgrade, uninstall, language loading, filename bootstrap, and missing-menu repair.
- Corrects account ownership filtering. Guest requests cannot attach themselves to an existing customer account by supplying its email.
- Replaces GET subscription and admin mutations with POST and session form-token validation.
- Replaces predictable time-derived unsubscribe codes with 32 random bytes.
- Escapes output and removes SQL interpolation of unescaped subscription addresses.
- Adds persistent IP and email request limits with hashed identities.
- Adds bounded email batches, cross-admin locking, cooldowns, retry delays, status/stock checks, and actual delivery-result checks.
- Fixes the legacy false-return handling that could discard requests when Zen Cart skipped email delivery.
- Makes preview read only and limits explicit tests to one email.
- Removes unlimited execution time, unbounded list views, per-product email lookup queries, the CEON placeholder engine, old merge templates, obsolete images, and unrelated historical documentation.
- Uses ordinary Zen Cart forms and language arrays.
- Adds database integration regression coverage and PHP 8.0 through 8.5 validation jobs.
