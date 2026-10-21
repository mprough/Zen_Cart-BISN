<?php
if (!defined('IS_ADMIN_FLAG')) { die('Illegal Access'); }
require_once dirname(__DIR__, 4) . '/shared/bootstrap.php';

class zcObserverBisn extends base
{
    public function __construct()
    {
        $this->attach($this, ['NOTIFY_PAGE_BODY_BEGIN']);
    }

    protected function update(&$callingClass, $eventID, $paramsArray = null): void
    {
        global $current_page_base;
        if (!ProWebsBisn::enabled()) { return; }
        $url = '';
        $label = '';
        if ($current_page_base === 'account' && !empty($_SESSION['customer_id'])) {
            $url = zen_href_link(FILENAME_ACCOUNT_BACK_IN_STOCK_NOTIFICATIONS, '', 'SSL');
            $label = BISN_ACCOUNT;
        } elseif (in_array($current_page_base, [
            'product_info', 'document_product_info', 'document_general_info', 'product_music_info',
            'product_free_shipping_info', 'product_book_info',
        ], true)) {
            $id = (int)ProWebsBisn::scalar($_GET, 'products_id');
            if ($id < 1) { return; }
            $product = ProWebsBisn::product($id, (int)($_SESSION['languages_id'] ?? 1));
            if (!$product || (float)$product['products_quantity'] > 0) { return; }
            $url = zen_href_link(FILENAME_BACK_IN_STOCK_NOTIFICATION_SUBSCRIBE, 'products_id=' . $id, 'SSL');
            $label = BISN_REQUEST;
        }
        if ($url !== '') {
            echo '<div class="bisn-notice centerColumn" role="region" aria-label="' . ProWebsBisn::h(BISN_TITLE) . '"><p><a class="btn btn-info" href="' . ProWebsBisn::h($url) . '">' . ProWebsBisn::h($label) . '</a></p></div>';
        }
    }
}
