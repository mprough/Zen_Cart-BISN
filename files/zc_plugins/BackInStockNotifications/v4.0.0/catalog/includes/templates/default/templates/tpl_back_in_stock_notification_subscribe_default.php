<?php
if (!defined('IS_ADMIN_FLAG')) { die('Illegal Access'); }
?>
<div class="centerColumn" id="bisnSubscribe">
<h1><?= ProWebsBisn::h(BISN_TITLE) ?></h1>
<?php if ($bisnProduct) { ?><h2><?= ProWebsBisn::h($bisnProduct['products_name']) ?></h2><?php } ?>
<?php if ($bisnMessage !== '') { ?><p role="status"><?= ProWebsBisn::h($bisnMessage) ?></p><?php } ?>
<?php
if (!in_array($bisnStatus, ['subscribed', 'disabled', 'unavailable'], true)) {
    echo zen_draw_form('bisnSubscribe', zen_href_link(FILENAME_BACK_IN_STOCK_NOTIFICATION_SUBSCRIBE, 'products_id=' . $bisnProductId, 'SSL'), 'post');
    echo ProWebsBisn::csrf();
?>
<p><?= ProWebsBisn::h(BISN_NOTICE) ?></p>
<p><label for="bisnName"><?= ProWebsBisn::h(BISN_NAME) ?></label>
<input class="form-control" id="bisnName" name="name" maxlength="64" autocomplete="name" required value="<?= ProWebsBisn::h($bisnName) ?>"></p>
<p><label for="bisnEmail"><?= ProWebsBisn::h(BISN_EMAIL) ?></label>
<input class="form-control" type="email" id="bisnEmail" name="email" maxlength="96" autocomplete="email" required value="<?= ProWebsBisn::h($bisnEmail) ?>"></p>
<p><label for="bisnConfirm"><?= ProWebsBisn::h(BISN_CONFIRM_EMAIL) ?></label>
<input class="form-control" type="email" id="bisnConfirm" name="cofnospam" maxlength="96" autocomplete="email" required value="<?= ProWebsBisn::h($bisnEmail) ?>"></p>
<div style="position:absolute;left:-10000px" aria-hidden="true"><label>Website<input name="website" tabindex="-1" autocomplete="off"></label></div>
<?php
if (defined('GOOGLE_RECAPTCHA_BISN_SUBSCRIBE') && GOOGLE_RECAPTCHA_BISN_SUBSCRIBE === 'true' && function_exists('recaptcha_get_html')) {
    echo recaptcha_get_html(false, 'light', 'normal', 'margin:5px');
}
?>
<button class="btn btn-primary" name="notify_me" value="1" type="submit"><?= ProWebsBisn::h(BISN_SUBMIT) ?></button>
</form>
<?php } ?>
</div>
