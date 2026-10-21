<?php
if (!defined('IS_ADMIN_FLAG') || IS_ADMIN_FLAG !== true) { return; }
require_once dirname(__DIR__, 4) . '/filenames.php';
foreach (['BOX_CEON_BACK_IN_STOCK_NOTIFICATIONS', 'BOX_CEON_BACK_IN_STOCK_NOTIFICATIONS_CONFIG_GROUP'] as $bisnLabel) {
    if (!defined($bisnLabel)) { define($bisnLabel, 'Back in stock notifications'); }
}
if (isset($db) && function_exists('zen_page_key_exists') && function_exists('zen_register_admin_page')) {
    $bisnInstalled = $db->Execute("SELECT configuration_group_id FROM " . TABLE_CONFIGURATION . " WHERE configuration_key='BISN_VERSION' LIMIT 1");
    if (!$bisnInstalled->EOF) {
        if (!zen_page_key_exists('ceon_bisn')) {
            zen_register_admin_page('ceon_bisn', 'BOX_CEON_BACK_IN_STOCK_NOTIFICATIONS', 'FILENAME_CEON_BACK_IN_STOCK_NOTIFICATIONS', '', 'catalog', 'Y');
        }
        $bisnGroup = (int)$bisnInstalled->fields['configuration_group_id'];
        if ($bisnGroup > 0 && !zen_page_key_exists('ceon_bisn_cg')) {
            zen_register_admin_page('ceon_bisn_cg', 'BOX_CEON_BACK_IN_STOCK_NOTIFICATIONS_CONFIG_GROUP', 'FILENAME_CONFIGURATION', 'gID=' . $bisnGroup, 'configuration', 'Y');
        }
    }
}
unset($bisnLabel, $bisnInstalled, $bisnGroup);
