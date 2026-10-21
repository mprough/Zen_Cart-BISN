<?php if (!defined('IS_ADMIN_FLAG')) { die('Illegal Access'); } ?>
<div class="centerColumn"><h1><?= ProWebsBisn::h(BISN_TITLE) ?></h1>
<?php if ($bisnMessage !== '') { ?><p role="status"><?= ProWebsBisn::h($bisnMessage) ?></p><?php } ?>
<?php if ($bisnSubscription) {
echo '<p>' . ProWebsBisn::h(sprintf(BISN_CONFIRM_CANCEL, $bisnSubscription['products_name'] ?? ('#' . $bisnId))) . '</p>';
echo zen_draw_form('bisnUnsubscribe', zen_href_link(FILENAME_BACK_IN_STOCK_NOTIFICATION_UNSUBSCRIBE, '', 'SSL'), 'post');
echo ProWebsBisn::csrf();
?>
<input type="hidden" name="id" value="<?= $bisnId ?>">
<input type="hidden" name="code" value="<?= ProWebsBisn::h($bisnCode) ?>">
<button class="btn btn-primary" type="submit"><?= ProWebsBisn::h(BISN_CANCEL_ONE) ?></button></form>
<?php } ?></div>
