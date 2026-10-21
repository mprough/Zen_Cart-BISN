<?php
/** BISN 4.0.2, PRO-Webs, Inc. GPL-3.0-or-later. */
if (!defined('IS_ADMIN_FLAG')) { die('Illegal Access'); }
if (!defined('TABLE_BACK_IN_STOCK_NOTIFICATION_SUBSCRIPTIONS')) {
    define('TABLE_BACK_IN_STOCK_NOTIFICATION_SUBSCRIPTIONS', DB_PREFIX . 'back_in_stock_notification_subscriptions');
}
// The active version can already be loaded when Plugin Manager upgrades this plugin.
if (!class_exists('ProWebsBisn', false)) {
    require_once __DIR__ . '/Bisn.php';
}
