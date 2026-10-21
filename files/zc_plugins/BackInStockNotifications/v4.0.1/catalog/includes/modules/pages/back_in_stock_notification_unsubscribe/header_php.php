<?php
if (!defined('IS_ADMIN_FLAG')) { die('Illegal Access'); }
require_once dirname(__DIR__, 5) . '/shared/bootstrap.php';
require DIR_WS_MODULES . 'require_languages.php';
$breadcrumb->add(BISN_TITLE);
$bisnInput = ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' ? $_POST : $_GET;
$bisnId = (int)ProWebsBisn::scalar($bisnInput, 'id');
$bisnCode = ProWebsBisn::scalar($bisnInput, 'code');
$bisnSubscription = [];
$bisnMessage = BISN_UNKNOWN;
// Retain compatibility with old ten-character unsubscribe links, but reject blank codes.
if ($bisnId > 0 && preg_match('/\A[a-f0-9]{10,64}\z/i', $bisnCode)) {
    $bisnEscapedCode = zen_db_input($bisnCode);
    $bisnFound = $db->Execute("SELECT s.id,pd.products_name FROM " . TABLE_BACK_IN_STOCK_NOTIFICATION_SUBSCRIPTIONS . " s
        LEFT JOIN " . TABLE_PRODUCTS_DESCRIPTION . " pd ON pd.products_id=s.product_id AND pd.language_id=" . (int)$_SESSION['languages_id'] . "
        WHERE s.id=$bisnId AND s.subscription_code='$bisnEscapedCode' LIMIT 1");
    if (!$bisnFound->EOF) {
        $bisnSubscription = $bisnFound->fields;
        $bisnMessage = '';
        if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
            if (!ProWebsBisn::validPost()) { $bisnMessage = BISN_INVALID_REQUEST; }
            else {
                $db->Execute("DELETE FROM " . TABLE_BACK_IN_STOCK_NOTIFICATION_SUBSCRIPTIONS . " WHERE id=$bisnId AND subscription_code='$bisnEscapedCode'");
                $bisnMessage = BISN_CANCELLED;
                $bisnSubscription = [];
            }
        }
    }
}
