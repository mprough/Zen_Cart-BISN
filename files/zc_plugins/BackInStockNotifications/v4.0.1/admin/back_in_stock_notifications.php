<?php
/** BISN 4.0.1 admin. GPL-3.0-or-later. */
require 'includes/application_top.php';
require_once dirname(__DIR__) . '/shared/bootstrap.php';
$bisnProductId = max(0, (int)ProWebsBisn::scalar($_GET, 'product_id'));
$bisnLanguage = (int)($_SESSION['languages_id'] ?? 1);
$bisnMessage = '';
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!ProWebsBisn::validPost()) { $bisnMessage = BISN_INVALID_REQUEST; }
    else {
        $action = ProWebsBisn::scalar($_POST, 'bisn_action');
        if ($action === 'send' || $action === 'test') {
            $report = ProWebsBisn::sendBatch($bisnLanguage, $action === 'test', $bisnProductId);
            $bisnMessage = sprintf('Batch status: %s. Sent: %d. Failed or skipped: %d.', $report['status'], $report['sent'], $report['failed']);
        } elseif ($action === 'delete') {
            $ids = isset($_POST['ids']) && is_array($_POST['ids']) ? array_slice($_POST['ids'], 0, 50) : [];
            $ids = array_values(array_filter(array_map(static function ($id) { return is_scalar($id) ? max(0, (int)$id) : 0; }, $ids)));
            if ($ids) {
                $db->Execute("DELETE FROM " . TABLE_BACK_IN_STOCK_NOTIFICATION_SUBSCRIPTIONS . " WHERE id IN (" . implode(',', $ids) . ")");
                $bisnMessage = 'Selected subscriptions deleted.';
            }
        } elseif ($action === 'cleanup') {
            // Bounded selection and deletion; repeat as needed for a large legacy backlog.
            $orphans = $db->Execute("SELECT s.id FROM " . TABLE_BACK_IN_STOCK_NOTIFICATION_SUBSCRIPTIONS . " s LEFT JOIN " .
                TABLE_PRODUCTS . " p ON p.products_id=s.product_id WHERE p.products_id IS NULL ORDER BY s.id LIMIT 100");
            $ids = [];
            foreach ($orphans as $orphan) { $ids[] = (int)$orphan['id']; }
            if ($ids) { $db->Execute("DELETE FROM " . TABLE_BACK_IN_STOCK_NOTIFICATION_SUBSCRIPTIONS . " WHERE id IN (" . implode(',', $ids) . ")"); }
            $bisnMessage = count($ids) . ' subscriptions for deleted products removed.';
        }
    }
}
$bisnPage = max(1, min(1000000, (int)ProWebsBisn::scalar($_GET, 'page')));
$bisnOffset = ($bisnPage - 1) * 50;
$bisnFilter = $bisnProductId > 0 ? " WHERE s.product_id=$bisnProductId" : '';
$bisnRows = $db->Execute("SELECT s.*,pd.products_name,p.products_quantity,p.products_status,
    c.customers_email_address FROM " . TABLE_BACK_IN_STOCK_NOTIFICATION_SUBSCRIPTIONS . " s
    LEFT JOIN " . TABLE_PRODUCTS . " p ON p.products_id=s.product_id
    LEFT JOIN " . TABLE_PRODUCTS_DESCRIPTION . " pd ON pd.products_id=s.product_id AND pd.language_id=$bisnLanguage
    LEFT JOIN " . TABLE_CUSTOMERS . " c ON c.customers_id=s.customer_id
    $bisnFilter ORDER BY s.id DESC LIMIT 51 OFFSET $bisnOffset");
$bisnPreview = ProWebsBisn::ready($bisnLanguage, ProWebsBisn::setting('BISN_BATCH_SIZE', 10, 1, 50), $bisnProductId);
$bisnParams = 'product_id=' . $bisnProductId . '&page=' . $bisnPage;
?>
<!doctype html>
<html <?= HTML_PARAMS ?>>
<head><?php require DIR_WS_INCLUDES . 'admin_html_head.php'; ?></head>
<body>
<?php require DIR_WS_INCLUDES . 'header.php'; ?>
<main class="container-fluid">
<h1><?= ProWebsBisn::h(BISN_TITLE) ?></h1>
<?php if ($bisnMessage !== '') { ?><div class="alert alert-info" role="status"><?= ProWebsBisn::h($bisnMessage) ?></div><?php } ?>
<p>Sending is manual. Each batch processes at most <?= ProWebsBisn::setting('BISN_BATCH_SIZE', 10, 1, 50) ?> subscriptions.
Wait at least <?= ProWebsBisn::setting('BISN_INTERVAL', 300, 300, 86400) ?> seconds between batches.
Change the admin language to send subscriptions in that language. Disabled and out of stock products are excluded.</p>
<p>Current admin language ID: <?= $bisnLanguage ?>. Ready in the next batch: <?= count($bisnPreview) ?>.</p>
<?= zen_draw_form('bisnFilter', FILENAME_CEON_BACK_IN_STOCK_NOTIFICATIONS, '', 'get') ?>
<label for="bisnProduct">Product ID (0 for all)</label>
<input id="bisnProduct" type="number" name="product_id" min="0" value="<?= $bisnProductId ?>">
<button class="btn btn-default" type="submit">Filter</button></form>
<details><summary>Preview next batch</summary>
<p>This preview sends no email. A test sends one sample to the configured test recipient and retains the subscription.</p>
<ul><?php foreach ($bisnPreview as $row) {
    echo '<li>#' . (int)$row['id'] . ' ' . ProWebsBisn::h($row['products_name']) . ' (' . ProWebsBisn::h($row['email_address'] ?: ($row['customers_email_address'] ?? '')) . ')</li>';
} ?></ul></details>
<?= zen_draw_form('bisnSend', FILENAME_CEON_BACK_IN_STOCK_NOTIFICATIONS, $bisnParams) ?>
<?= ProWebsBisn::csrf() ?>
<p><button class="btn btn-primary" name="bisn_action" value="send" type="submit">Send next batch</button>
<button class="btn btn-default" name="bisn_action" value="test" type="submit">Send one test email</button></p>
</form>
<?= zen_draw_form('bisnList', FILENAME_CEON_BACK_IN_STOCK_NOTIFICATIONS, $bisnParams) ?>
<?= ProWebsBisn::csrf() ?>
<div class="table-responsive"><table class="table table-striped">
<thead><tr><th>Select</th><th>ID</th><th>Product</th><th>Stock</th><th>Name</th><th>Email</th><th>Language</th><th>Subscribed</th><th>Attempts</th><th>Retry after</th></tr></thead>
<tbody>
<?php $bisnCount = 0; foreach ($bisnRows as $row) { if (++$bisnCount > 50) { break; } ?>
<tr><td><input type="checkbox" name="ids[]" value="<?= (int)$row['id'] ?>" aria-label="Select subscription <?= (int)$row['id'] ?>"></td>
<td><?= (int)$row['id'] ?></td>
<td>#<?= (int)$row['product_id'] ?> <?= ProWebsBisn::h($row['products_name'] ?? '(deleted or untranslated product)') ?></td>
<td><?= ProWebsBisn::h($row['products_quantity'] ?? '') ?></td>
<td><?= ProWebsBisn::h($row['name']) ?></td>
<td><?= ProWebsBisn::h($row['email_address'] ?: ($row['customers_email_address'] ?? '')) ?></td>
<td><?= (int)$row['languages_id'] ?></td>
<td><?= ProWebsBisn::h($row['date_subscribed']) ?></td>
<td><?= (int)$row['attempts'] ?></td><td><?= ProWebsBisn::h($row['next_attempt'] ?? '') ?></td></tr>
<?php } ?>
</tbody></table></div>
<p><button class="btn btn-danger" type="submit" name="bisn_action" value="delete">Delete selected subscriptions</button></p></form>
<nav aria-label="Subscription pages">
<?php if ($bisnPage > 1) { echo '<a class="btn btn-default" href="' . ProWebsBisn::h(zen_href_link(FILENAME_CEON_BACK_IN_STOCK_NOTIFICATIONS, 'product_id=' . $bisnProductId . '&page=' . ($bisnPage - 1))) . '">Previous</a> '; } ?>
Page <?= $bisnPage ?>
<?php if ($bisnRows->RecordCount() > 50) { echo ' <a class="btn btn-default" href="' . ProWebsBisn::h(zen_href_link(FILENAME_CEON_BACK_IN_STOCK_NOTIFICATIONS, 'product_id=' . $bisnProductId . '&page=' . ($bisnPage + 1))) . '">Next</a>'; } ?>
</nav>
<details><summary>Maintenance</summary>
<?= zen_draw_form('bisnCleanup', FILENAME_CEON_BACK_IN_STOCK_NOTIFICATIONS, $bisnParams) ?>
<?= ProWebsBisn::csrf() ?>
<p><button class="btn btn-warning" type="submit" name="bisn_action" value="cleanup">Remove up to 100 requests for deleted products</button></p>
</form></details>
</main>
<?php require DIR_WS_INCLUDES . 'footer.php'; ?>
</body></html>
<?php require DIR_WS_INCLUDES . 'application_bottom.php'; ?>
