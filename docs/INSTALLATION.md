# Installation, migration, and removal

## Before starting

Back up the store files and database. Use a development copy to confirm appearance, customer ownership, language selection, sending, and cancellation. This package supports exactly Zen Cart 2.1.0 and 2.2.2.

The only install tree is files/zc_plugins/BackInStockNotifications/v4.0.0. Upload files/ into the store root. Do not rename admin folders inside this package; Plugin Manager resolves the active admin directory.

## Product page and account template integration

Version 4 uses a link to a dedicated subscription form. It does not use the old embedded product_info form or the legacy merge templates.

On templates with NOTIFY_PAGE_BODY_BEGIN, the product and account links display automatically. For custom or Bootstrap templates that do not display the link, follow the complete [product_info and account integration examples in the README](../README.md#product_info-integration). Those examples show the exact active-template path and copyable PHP.

Do not add the fallback if the automatic link already displays. Remove old BISN template includes during migration.

## Migrating the loose-file release

Do not uninstall the old database or delete subscriptions. Version 4 reuses the prefixed back_in_stock_notification_subscriptions table.

1. Pause old notification sending.
2. Use [legacy-files.json](legacy-files.json) as the inventory of the inherited files. ADMIN_FOLDER means your renamed admin directory.
3. Remove the plugin's old loose files from the storefront and admin, including both auto.back_in_stock_notifications observers, the old page header files, the old admin page, old extra_datafiles definitions, menu-registration function, old language definitions, CEON helper classes, and old plugin templates. Check that a CEON class is not used by another plugin before removing a shared copy.
4. Remove the BISN additions previously merged into your active tpl_product_info_display.php and tpl_account_default.php. Restore only those additions, not unrelated template changes. Remove the old plugin_bisn_product_info_subscribe_form include and legacy BISN variable references.
5. Remove the old account CSS and plugin email templates if no longer used. Keep a backup of custom translations and styling for reference.
6. Upload files/ and install/enable version 4 in Plugin Manager.
7. Confirm the menu entries, settings, and existing subscriptions.

The installer refuses to proceed when key legacy executable files remain. Its check is a safeguard, not a replacement for the full removal inventory.

Settings are preserved by exact configuration key. The obsolete BACK_IN_STOCK_NOTIFICATION_ENABLED key is migrated to BACK_IN_STOCK_NOTIFICATIONS_ENABLED. The existing configuration group and menu page keys are reused. The installed version is read only in the standard settings UI.

Subscriptions are not re-linked to customer accounts by matching arbitrary email addresses. Legacy customer_id rows remain account-owned. Guest rows remain accessible through their unsubscribe link.

## What installation changes in the database

- Creates the subscription table if absent.
- Widens subscription_code to 64 characters without replacing existing codes.
- Adds languages_id for older installations that lack it, defaulting to language ID 1.
- Adds next_attempt and attempts for failed delivery retries.
- Adds indexes for product/customer, product/email, customer management, and language/retry queue selection.
- Creates bisn_rate and bisn_state tables, both with your configured table prefix.
- Adds only missing BISN settings and registers the two admin pages.

If an old store does not use language ID 1, assign the correct languages_id to its historical subscriptions before sending. Back up and make that change deliberately; the original records may not contain enough information to infer their language.

## Upgrade

Upload the newer self-contained version directory and use Plugin Manager's upgrade action. Do not edit an already released version directory to introduce a future update.

## Disable and uninstall

Disable pauses the plugin through Plugin Manager. Configuration > Enable notifications = 0 also pauses new subscriptions and sending, while account and unsubscribe cancellations remain available when the plugin is enabled.

Uninstall removes only the listed BISN configuration keys, its two admin page registrations, and its throttle/state tables. It removes the configuration group only if that group contains no remaining settings. It retains subscription business data and its indexes. Reinstall reconnects that retained table.

When the plugin is disabled or uninstalled, its storefront page handlers are unavailable. If customers still need cancellation access, pause notifications with the configuration switch and keep the plugin enabled.

After uninstall, remove the uploaded version directory if it is no longer needed. Permanent removal of the retained subscription table is a separate, deliberate database action and is not performed by the plugin.

## Acceptance checks on the development store

- Install, enable, and confirm Catalog and Configuration entries.
- Open an active product with zero stock and follow the notification link.
- Subscribe as a guest and a signed-in customer. Refreshing the result must not add a duplicate.
- Confirm a customer's account lists only their own requests.
- Open an unsubscribe URL with GET, then confirm cancellation with POST.
- Replenish stock, preview a batch, send one test, and verify the real test email.
- Send a real batch, verify the actual email, and check that delivered requests are removed.
- Confirm a second send within five minutes is refused.
- Disable mail delivery and confirm requests remain.
- Verify your template's layout at desktop and mobile widths.
