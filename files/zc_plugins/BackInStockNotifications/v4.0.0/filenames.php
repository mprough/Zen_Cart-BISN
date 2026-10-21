<?php
if (!defined('IS_ADMIN_FLAG')) { die('Illegal Access'); }
foreach ([
    'FILENAME_CEON_BACK_IN_STOCK_NOTIFICATIONS' => 'back_in_stock_notifications',
    'FILENAME_BACK_IN_STOCK_NOTIFICATION_SUBSCRIBE' => 'back_in_stock_notification_subscribe',
    'FILENAME_BACK_IN_STOCK_NOTIFICATION_UNSUBSCRIBE' => 'back_in_stock_notification_unsubscribe',
    'FILENAME_ACCOUNT_BACK_IN_STOCK_NOTIFICATIONS' => 'account_back_in_stock_notifications',
] as $bisnKey => $bisnValue) {
    if (!defined($bisnKey)) { define($bisnKey, $bisnValue); }
}
unset($bisnKey, $bisnValue);
