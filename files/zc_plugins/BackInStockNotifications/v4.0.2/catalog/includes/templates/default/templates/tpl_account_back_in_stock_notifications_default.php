<?php if (!defined('IS_ADMIN_FLAG')) { die('Illegal Access'); } ?>
<div class="centerColumn">
<h1><?= ProWebsBisn::h(BISN_TITLE) ?></h1>
<?php if ($bisnMessage !== '') { ?><p role="status"><?= ProWebsBisn::h($bisnMessage) ?></p><?php } ?>
<?php if ($bisnRows->EOF) { ?><p><?= ProWebsBisn::h(BISN_EMPTY) ?></p><?php } else {
echo zen_draw_form('bisnAccount', zen_href_link(FILENAME_ACCOUNT_BACK_IN_STOCK_NOTIFICATIONS, 'page=' . $bisnPage, 'SSL'), 'post');
echo ProWebsBisn::csrf();
$bisnCount = 0;
foreach ($bisnRows as $bisnRow) {
    if (++$bisnCount > 50) { break; }
?>
<p><label><input type="checkbox" name="cancel[]" value="<?= (int)$bisnRow['id'] ?>">
<?= ProWebsBisn::h($bisnRow['products_name'] ?? ('#' . $bisnRow['product_id'])) ?>
(<?= ProWebsBisn::h($bisnRow['date_subscribed']) ?>)</label></p>
<?php } ?>
<button type="submit" class="btn btn-primary"><?= ProWebsBisn::h(BISN_CANCEL) ?></button></form>
<?php }
if ($bisnPage > 1) { echo '<p><a href="' . ProWebsBisn::h(zen_href_link(FILENAME_ACCOUNT_BACK_IN_STOCK_NOTIFICATIONS, 'page=' . ($bisnPage - 1), 'SSL')) . '">&lt; ' . (int)($bisnPage - 1) . '</a></p>'; }
if ($bisnRows->RecordCount() > 50) { echo '<p><a href="' . ProWebsBisn::h(zen_href_link(FILENAME_ACCOUNT_BACK_IN_STOCK_NOTIFICATIONS, 'page=' . ($bisnPage + 1), 'SSL')) . '">' . (int)($bisnPage + 1) . ' &gt;</a></p>'; }
?>
</div>
