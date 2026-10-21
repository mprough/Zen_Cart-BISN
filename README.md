# Back in stock notifications for Zen Cart

Version 4.0.0, maintained by Melanie Prough, PRO-Webs, Inc., [PRO-Webs.net](https://pro-webs.net/).

Customers can request an email when an unavailable product returns to stock. Store staff preview requests and send small batches from **Catalog > Back in stock notifications**.

## Compatibility

| Zen Cart | PHP |
| --- | --- |
| 2.1.0 | 8.0 through 8.3 |
| 2.2.2 | 8.2 through 8.5 |

Zen Cart 2.0.0 and 2.0.1 are not supported. The installer enforces the two supported Zen Cart releases. Use an actively maintained PHP release within your Zen Cart version's supported range.

## What version 4 changes

- Installs through Plugin Manager with one encapsulated version directory.
- Adds product and account links through an existing Zen Cart template event. No core or template overwrites or template merges.
- Replaces the CEON placeholder engine with ordinary PHP forms and Zen Cart mail.
- Requires POST and a session form token for all subscription changes and admin actions.
- Fixes the legacy account query that could expose or remove another customer's requests.
- Uses random unsubscribe tokens and preserves existing unsubscribe links.
- Adds persistent signup throttles, indexed queries, paged admin/account lists, bounded batches, a minimum five minute send interval, and a shared send lock.
- Retains failed or skipped email requests for retry. A preview never sends mail.
- Preserves legacy subscriptions and settings during migration and subscription records during uninstall.

See [installation and migration](docs/INSTALLATION.md), [usage and configuration](docs/USER_GUIDE.md), [technical notes](docs/TECHNICAL_NOTES.md), [audit findings](docs/AUDIT.md), and [change history](CHANGELOG.md).

## Installation

Back up your files and database and test on a development copy first.

1. For an existing BISN installation, follow the legacy removal instructions before uploading.
2. Upload the contents of **files/** to the store root.
3. Open **Admin > Modules > Plugin Manager**, install and enable **Back in stock notifications 4.0.0**.
4. Review **Configuration > Back in stock notifications**.
5. Check the product page. If your custom template does not show the link, follow Product_info integration below.
6. Test a product request, an account cancellation, a guest unsubscribe link, and a test email before sending a real batch.

## Product_info integration

### Standard automatic display

When your active template fires NOTIFY_PAGE_BODY_BEGIN, version 4 automatically displays a notification link above the product content for active products whose parent quantity is zero or below. No product_info edit is needed in that case.

Clicking the link opens the dedicated subscription page. Customers must submit that form, including when they are logged in. The old embedded form, legacy BISN variables, and plugin_bisn_product_info_subscribe_form.php include are no longer used.

### Custom or Bootstrap template without the automatic link

If the notification link is missing, add the following to your active template's product display file:

**includes/templates/YOUR_TEMPLATE/templates/tpl_product_info_display.php**

Replace YOUR_TEMPLATE with the active template folder, for example bootstrap. Edit the active override, not the plugin's page template. If your template has no override, copy its current inherited product display file into the active template's templates directory before editing.

Place this block after the product description or beside the stock information, outside the add-to-cart form. It includes its own PHP opening and closing tags, so paste it in the HTML portion of the template.

```php
<?php
// BISN 4: use only when this template does not show the automatic BISN link.
if (class_exists('ProWebsBisn', false) && ProWebsBisn::enabled()) {
    $bisnProductId = (int)ProWebsBisn::scalar($_GET, 'products_id');
    $bisnProduct = ProWebsBisn::product(
        $bisnProductId,
        (int)($_SESSION['languages_id'] ?? 1)
    );
    if ($bisnProduct && (float)$bisnProduct['products_quantity'] <= 0) {
        $bisnUrl = zen_href_link(
            FILENAME_BACK_IN_STOCK_NOTIFICATION_SUBSCRIBE,
            'products_id=' . $bisnProductId,
            'SSL'
        );
        ?>
        <div class="bisn-notice">
            <p>
                <a class="btn btn-info" href="<?= ProWebsBisn::h($bisnUrl) ?>">
                    <?= ProWebsBisn::h(BISN_REQUEST) ?>
                </a>
            </p>
        </div>
        <?php
    }
    unset($bisnProductId, $bisnProduct, $bisnUrl);
}
?>
```

The guard allows this template code to remain when BISN is disabled or uninstalled. The product lookup verifies active status and stock before showing the link.

**Use either the automatic link or this fallback.** Adding the fallback to a template that already displays the automatic link will display two links. This snippet is a missing-event fallback, not a switch for moving an existing automatic link.

For the account-management link, the same standard event displays it on the account page. A custom account template without that event can use:

```php
<?php
if (class_exists('ProWebsBisn', false)
    && ProWebsBisn::enabled()
    && !empty($_SESSION['customer_id'])) {
    ?>
    <p>
        <a href="<?= ProWebsBisn::h(zen_href_link(
            FILENAME_ACCOUNT_BACK_IN_STOCK_NOTIFICATIONS,
            '',
            'SSL'
        )) ?>">
            <?= ProWebsBisn::h(BISN_ACCOUNT) ?>
        </a>
    </p>
    <?php
}
?>
```

Do not restore the old CEON template includes or copy the obsolete BISN TO MERGE files into a version 4 installation.

## Scope and limits

Notification sending is manual. No cron job is required or included, and browsing the storefront never starts an email batch. Send the next batch from the admin page after the cooldown.

Availability is based on the parent product quantity and active status. Attribute inventory systems such as SBA and POSM are not supported. A notification does not reserve inventory.

Requests retain the language used when subscribing. Select that language in the admin to preview and send those requests. English is included; additional languages require translations of the supplied language arrays.

Version 4 uses a separate subscription form page instead of embedding a second form inside a product template. It does not send signup acknowledgments or admin copy emails. The old copy-address setting is retained for migration reference. Test delivery is an explicit admin action.

Templates must fire the standard **NOTIFY_PAGE_BODY_BEGIN** event. The event exists in the supported Zen Cart responsive_classic templates. Verify custom and Bootstrap templates on your development store.

## License, credits, and support

The repository retains its GNU GPL version 3 license. See [LICENSE](LICENSE). Credits: original CEON Back In Stock Notifications by Conor Kerr, subsequent contributions by Tony Niemann and RubikIntegration, and extensive maintenance by torvista. The inherited source contains historical GPL version 2 notices; these credits and the upstream history remain available in git.

This free distribution is provided without warranty. Installation, configuration, customization, and third party template work are not included.

Report bugs and security concerns privately through the [PRO-Webs helpdesk](https://prowebsinc.zohodesk.com/portal/en/newticket). Include Zen Cart/PHP versions and a redacted error log. Do not send customer addresses, live unsubscribe links, credentials, or database dumps in public issues.

[Source repository](https://github.com/mprough/Zen_Cart-BISN). No Zen Cart plugin directory ID has been assigned to this maintained package; the manifest uses 0.
