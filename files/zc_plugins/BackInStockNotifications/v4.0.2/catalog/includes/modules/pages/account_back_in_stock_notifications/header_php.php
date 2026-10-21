<?php
if (!defined('IS_ADMIN_FLAG')) { die('Illegal Access'); }
require_once dirname(__DIR__, 5) . '/shared/bootstrap.php';
if (empty($_SESSION['customer_id'])) {
    $_SESSION['navigation']->set_snapshot();
    zen_redirect(zen_href_link(FILENAME_LOGIN, '', 'SSL'));
}
require DIR_WS_MODULES . 'require_languages.php';
$breadcrumb->add(BISN_TITLE);
$bisnOwner = ProWebsBisn::ownership((int)$_SESSION['customer_id']);
$bisnMessage = '';
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!ProWebsBisn::validPost()) { $bisnMessage = BISN_INVALID_REQUEST; }
    else {
        $bisnIds = isset($_POST['cancel']) && is_array($_POST['cancel']) ? array_slice($_POST['cancel'], 0, 50) : [];
        $bisnIds = array_values(array_filter(array_map(static function ($id) { return is_scalar($id) ? (int)$id : 0; }, $bisnIds)));
        if ($bisnIds) {
            $db->Execute("DELETE FROM " . TABLE_BACK_IN_STOCK_NOTIFICATION_SUBSCRIPTIONS . " WHERE $bisnOwner AND id IN (" . implode(',', $bisnIds) . ")");
            $bisnMessage = BISN_CANCELLED;
        }
    }
}
$bisnPage = max(1, min(1000000, (int)ProWebsBisn::scalar($_GET, 'page')));
$bisnOffset = ($bisnPage - 1) * 50;
$bisnRows = $db->Execute("SELECT s.id,s.product_id,s.date_subscribed,pd.products_name FROM " .
    TABLE_BACK_IN_STOCK_NOTIFICATION_SUBSCRIPTIONS . " s LEFT JOIN " . TABLE_PRODUCTS_DESCRIPTION . " pd
    ON pd.products_id=s.product_id AND pd.language_id=" . (int)$_SESSION['languages_id'] . "
    WHERE s.$bisnOwner ORDER BY s.id DESC LIMIT 51 OFFSET $bisnOffset");
