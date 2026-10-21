<?php
if (!defined('IS_ADMIN_FLAG')) { die('Illegal Access'); }
require_once dirname(__DIR__, 5) . '/shared/bootstrap.php';
require DIR_WS_MODULES . 'require_languages.php';
$breadcrumb->add(BISN_TITLE);
if (defined('BACK_IN_STOCK_REQUIRES_LOGIN') && BACK_IN_STOCK_REQUIRES_LOGIN === '1' && empty($_SESSION['customer_id'])) {
    $_SESSION['navigation']->set_snapshot();
    zen_redirect(zen_href_link(FILENAME_LOGIN, '', 'SSL'));
}
$bisnProductId = (int)ProWebsBisn::scalar($_GET, 'products_id');
$bisnLanguage = (int)($_SESSION['languages_id'] ?? 1);
$bisnProduct = ProWebsBisn::product($bisnProductId, $bisnLanguage);
$bisnMessage = '';
$bisnStatus = '';
$bisnCustomer = ProWebsBisn::customer();
$bisnName = $bisnCustomer ? trim($bisnCustomer['customers_firstname'] . ' ' . $bisnCustomer['customers_lastname']) : '';
$bisnEmail = $bisnCustomer['customers_email_address'] ?? '';
if (!ProWebsBisn::enabled()) { $bisnStatus = 'disabled'; }
elseif (!$bisnProduct || (float)$bisnProduct['products_quantity'] > 0) { $bisnStatus = 'unavailable'; }
elseif (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $bisnName = ProWebsBisn::scalar($_POST, 'name');
    $bisnEmail = ProWebsBisn::scalar($_POST, 'email');
    if (!ProWebsBisn::validPost()) { $bisnMessage = BISN_INVALID_REQUEST; }
    elseif (strcasecmp($bisnEmail, ProWebsBisn::scalar($_POST, 'cofnospam')) !== 0) { $bisnStatus = 'invalid'; }
    elseif (ProWebsBisn::scalar($_POST, 'website') !== '') { $bisnStatus = 'invalid'; }
    else {
        $error = false;
        $zco_notifier->notify('NOTIFY_BISN_SUBSCRIBE_CAPTCHA_CHECK', $_POST);
        if (!empty($error)) { $bisnMessage = BISN_INVALID_REQUEST; }
        else { $bisnStatus = ProWebsBisn::subscribe($bisnProductId, $bisnLanguage, $bisnName, $bisnEmail); }
    }
}
if ($bisnStatus !== '') { $bisnMessage = constant('BISN_' . $bisnStatus); }
