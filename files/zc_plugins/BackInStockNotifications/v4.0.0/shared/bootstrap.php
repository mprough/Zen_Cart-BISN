<?php
/** BISN 4.0.0, PRO-Webs, Inc. GPL-3.0-or-later. */
if (!defined('IS_ADMIN_FLAG')) { die('Illegal Access'); }
if (!defined('TABLE_BACK_IN_STOCK_NOTIFICATION_SUBSCRIPTIONS')) {
    define('TABLE_BACK_IN_STOCK_NOTIFICATION_SUBSCRIPTIONS', DB_PREFIX . 'back_in_stock_notification_subscriptions');
}
require_once __DIR__ . '/Bisn.php';
