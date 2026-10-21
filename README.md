# Back in stock notifications for Zen Cart

Version 4.0.2, maintained by Melanie Prough, PRO-Webs, Inc., [PRO-Webs.net](https://pro-webs.net/).

Customers can request an email when an unavailable product returns to stock. Store staff preview requests and send small batches from **Catalog > Back in stock notifications**.

## Compatibility

| Zen Cart | PHP |
| --- | --- |
| 2.1.0 | 8.0 through 8.3 |
| 2.2.2 | 8.2 through 8.5 |

Zen Cart 2.0.0 and 2.0.1 are not supported. The installer enforces the two supported Zen Cart releases. Use an actively maintained PHP release within your Zen Cart version's supported range.

## What version 4 changes

- Installs through Plugin Manager with one encapsulated version directory.
- Provides product and account link code for your template. The plugin does not insert these links itself.
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
3. Open **Admin > Modules > Plugin Manager**, install and enable **Back in stock notifications 4.0.2**.
4. Review **Configuration > Back in stock notifications**.
5. Add the product and account links using the Bootstrap instructions below. If you already added the version 4 link code, keep it.
6. Test a product request, an account cancellation, a guest unsubscribe link, and a test email before sending a real batch.

## Product_info integration for Bootstrap

This step adds a **Notify me when this product is back in stock** link below the product description. Clicking it opens the signup form. The link is shown only for active products with quantity zero or below.

1. Open **includes/templates/bootstrap/templates/tpl_product_info_display.php**.
2. Find this line:

```html
<!--eof Product description -->
```

3. Paste the complete block below immediately after that line.
4. Save and upload the edited file.
5. Open an active product with quantity zero. The notification link should appear below its description. Click it and submit a request.

```php
<?php
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

If your Bootstrap template uses a different folder name, use that folder instead of bootstrap. Other templates can use this same block in their own tpl_product_info_display.php, after the product description.

## Account page link for Bootstrap

This step lets signed-in customers open their list of notification requests.

1. Open **includes/templates/bootstrap/templates/tpl_account_default.php**.
2. Find the page heading and its closing **</h1>** tag.
3. Paste the complete block below immediately after that closing tag.
4. Save and upload the edited file.
5. Sign in and open My Account. Click **Manage back in stock notifications**.

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

The plugin does not insert product or account links itself. These two template blocks determine where the links appear. Existing version 4 template blocks can remain in place when upgrading to 4.0.2; do not paste a second copy.

## Scope and limits

Notification sending is manual. No cron job is required or included, and browsing the storefront never starts an email batch. Send the next batch from the admin page after the cooldown.

Availability is based on the parent product quantity and active status. Attribute inventory systems such as SBA and POSM are not supported. A notification does not reserve inventory.

Requests retain the language used when subscribing. Select that language in the admin to preview and send those requests. English is included; additional languages require translations of the supplied language arrays.

Version 4 uses a separate subscription form page instead of embedding a second form inside a product template. It does not send signup acknowledgments or admin copy emails. The old copy-address setting is retained for migration reference. Test delivery is an explicit admin action.

Product and account links are placed by the template code shown above. No template event is required.

## License, credits, and support

The repository retains its GNU GPL version 3 license. See [LICENSE](LICENSE). Credits: original CEON Back In Stock Notifications by Conor Kerr, subsequent contributions by Tony Niemann and RubikIntegration, and extensive maintenance by torvista. The inherited source contains historical GPL version 2 notices; these credits and the upstream history remain available in git.

This free distribution is provided without warranty. Installation, configuration, customization, and third party template work are not included.

Report bugs and security concerns privately through the [PRO-Webs helpdesk](https://prowebsinc.zohodesk.com/portal/en/newticket). Include Zen Cart/PHP versions and a redacted error log. Do not send customer addresses, live unsubscribe links, credentials, or database dumps in public issues.

[Source repository](https://github.com/mprough/Zen_Cart-BISN). No Zen Cart plugin directory ID has been assigned to this maintained package; the manifest uses 0.
