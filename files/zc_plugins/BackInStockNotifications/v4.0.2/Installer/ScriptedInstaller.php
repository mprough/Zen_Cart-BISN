<?php
if (!defined('IS_ADMIN_FLAG')) { die('Illegal Access'); }
use Zencart\PluginSupport\ScriptedInstaller as ScriptedInstallBase;

class ScriptedInstaller extends ScriptedInstallBase
{
    private const KEYS = [
        'BISN_VERSION', 'BACK_IN_STOCK_NOTIFICATIONS_ENABLED', 'BACK_IN_STOCK_REQUIRES_LOGIN',
        'SEND_EXTRA_BACK_IN_STOCK_NOTIFICATION_SUBSCRIPTION_EMAILS_TO', 'BISN_TEST_EMAIL_TO',
        'BISN_BATCH_SIZE', 'BISN_INTERVAL', 'BISN_IP_LIMIT', 'BISN_EMAIL_LIMIT', 'BISN_SECRET',
        'BACK_IN_STOCK_NOTIFICATION_ENABLED',
    ];

    protected function executeInstall(): bool
    {
        try { return $this->installOrUpgrade(); }
        catch (Throwable $e) {
            $this->errorContainer->addError(0, $e->getMessage(), true);
            return false;
        }
    }

    private function runSql(string $sql): void
    {
        if (!parent::executeInstallerSql($sql)) {
            throw new RuntimeException('BISN database change failed. Review the Plugin Manager error and retry after correcting it.');
        }
    }

    private function installOrUpgrade(): bool
    {
        // The manifest is advisory; enforce the supported versions on both cores.
        $version = PROJECT_VERSION_MAJOR . '.' . PROJECT_VERSION_MINOR;
        if (!in_array($version, ['2.1.0', '2.2.2'], true)) {
            throw new RuntimeException('BISN 4.0.2 requires Zen Cart 2.1.0 or 2.2.2.');
        }
        foreach ([
            DIR_FS_CATALOG . 'includes/classes/observers/auto.back_in_stock_notificationsProductInfo.php',
            DIR_FS_CATALOG . 'includes/classes/observers/auto.back_in_stock_notificationsAccount.php',
            DIR_FS_CATALOG . 'includes/modules/pages/back_in_stock_notification_subscribe/header_php.php',
            DIR_FS_ADMIN . 'back_in_stock_notifications.php',
            DIR_FS_ADMIN . 'includes/functions/extra_functions/back_in_stock_notifications_admin_page_reg.php',
        ] as $legacyFile) {
            if (is_file($legacyFile)) {
                throw new RuntimeException('Remove the legacy BISN loose files before installing version 4. See docs/INSTALLATION.md. Your subscription table must be kept.');
            }
        }
        require_once dirname(__DIR__) . '/shared/bootstrap.php';
        $db = $this->dbConn;
        $table = TABLE_BACK_IN_STOCK_NOTIFICATION_SUBSCRIPTIONS;
        $this->runSql("CREATE TABLE IF NOT EXISTS $table (
            id int unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
            product_id int unsigned NOT NULL DEFAULT 0,
            customer_id int unsigned DEFAULT NULL,
            subscription_code varchar(64) DEFAULT NULL,
            name varchar(64) NOT NULL DEFAULT '',
            email_address varchar(96) DEFAULT NULL,
            date_subscribed datetime NOT NULL,
            languages_id int unsigned NOT NULL DEFAULT 1,
            next_attempt datetime DEFAULT NULL,
            attempts int unsigned NOT NULL DEFAULT 0
        ) ENGINE=InnoDB");
        $columns = [];
        foreach ($db->Execute("SHOW COLUMNS FROM $table") as $row) { $columns[$row['Field']] = true; }
        foreach ([
            'languages_id' => 'int unsigned NOT NULL DEFAULT 1',
            'next_attempt' => 'datetime DEFAULT NULL',
            'attempts' => 'int unsigned NOT NULL DEFAULT 0',
        ] as $column => $definition) {
            if (!isset($columns[$column])) { $this->runSql("ALTER TABLE $table ADD $column $definition"); }
        }
        $this->runSql("ALTER TABLE $table MODIFY subscription_code varchar(64) DEFAULT NULL");
        $indexes = [];
        foreach ($db->Execute("SHOW INDEX FROM $table") as $row) { $indexes[$row['Key_name']] = true; }
        foreach ([
            'bisn_product_customer' => '(product_id, customer_id)',
            'bisn_product_email' => '(product_id, email_address)',
            'bisn_customer' => '(customer_id)',
            'bisn_queue' => '(languages_id, next_attempt, id)',
        ] as $index => $columnsSql) {
            if (!isset($indexes[$index])) { $this->runSql("ALTER TABLE $table ADD INDEX $index $columnsSql"); }
        }
        $rate = DB_PREFIX . 'bisn_rate';
        $state = DB_PREFIX . 'bisn_state';
        $this->runSql("CREATE TABLE IF NOT EXISTS $rate (
            scope_hash char(64) NOT NULL, bucket bigint unsigned NOT NULL,
            hits int unsigned NOT NULL DEFAULT 1,
            PRIMARY KEY(scope_hash, bucket), INDEX bisn_bucket(bucket)
        ) ENGINE=InnoDB");
        $this->runSql("CREATE TABLE IF NOT EXISTS $state (
            state_key varchar(32) NOT NULL PRIMARY KEY,
            state_value bigint unsigned NOT NULL DEFAULT 0
        ) ENGINE=InnoDB");
        $group = $db->Execute("SELECT configuration_group_id FROM " . TABLE_CONFIGURATION_GROUP . "
            WHERE configuration_group_title = 'Back In Stock Notifications' LIMIT 1");
        if ($group->EOF) {
            $this->runSql("INSERT INTO " . TABLE_CONFIGURATION_GROUP . "
                (configuration_group_title, configuration_group_description, sort_order, visible)
                VALUES ('Back In Stock Notifications', 'Back in stock notification settings', 1, 1)");
            $groupId = (int)$db->insert_ID();
        } else { $groupId = (int)$group->fields['configuration_group_id']; }

        // Rename the historical key only when the replacement does not already exist.
        $old = $db->Execute("SELECT configuration_value FROM " . TABLE_CONFIGURATION . " WHERE configuration_key='BACK_IN_STOCK_NOTIFICATION_ENABLED' LIMIT 1");
        $defaults = [
            'BISN_VERSION' => ['Installed version', '4.0.2', 'Installed version. Managed by Plugin Manager.'],
            'BACK_IN_STOCK_NOTIFICATIONS_ENABLED' => ['Enable notifications', $old->EOF ? '1' : $old->fields['configuration_value'], '1 enables subscriptions and sending; 0 pauses both. Account cancellations remain available.'],
            'BACK_IN_STOCK_REQUIRES_LOGIN' => ['Require login', '0', '1 requires a customer account; 0 also allows guests.'],
            'SEND_EXTRA_BACK_IN_STOCK_NOTIFICATION_SUBSCRIPTION_EMAILS_TO' => ['Legacy subscription copy addresses', '', 'Preserved for migration. Version 4 does not send signup emails or admin copies.'],
            'BISN_TEST_EMAIL_TO' => ['Test recipient', defined('EMAIL_FROM') ? EMAIL_FROM : '', 'An explicit test action sends one sample here.'],
            'BISN_BATCH_SIZE' => ['Notification batch size', '10', 'Subscriptions per batch, clamped to 1 through 50.'],
            'BISN_INTERVAL' => ['Batch interval in seconds', '300', 'Minimum 300 seconds between send batches across all admins and languages.'],
            'BISN_IP_LIMIT' => ['Subscriptions per IP in five minutes', '5', 'Accepted requests per five minute bucket, clamped to 1 through 50.'],
            'BISN_EMAIL_LIMIT' => ['Subscriptions per email in five minutes', '3', 'Accepted requests per five minute bucket, clamped to 1 through 20.'],
            'BISN_SECRET' => ['Internal privacy key', bin2hex(random_bytes(32)), 'Internal key for hashing throttle identities. Do not edit.'],
        ];
        $sort = 0;
        foreach ($defaults as $key => [$title, $value, $description]) {
            $exists = $db->Execute("SELECT configuration_id FROM " . TABLE_CONFIGURATION . " WHERE configuration_key='$key' LIMIT 1");
            if ($exists->EOF) {
                $title = zen_db_input($title); $value = zen_db_input($value); $description = zen_db_input($description);
                $this->runSql("INSERT INTO " . TABLE_CONFIGURATION . "
                    (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added)
                    VALUES ('$title', '$key', '$value', '$description', $groupId, $sort, NOW())");
            } else {
                $this->runSql("UPDATE " . TABLE_CONFIGURATION . " SET configuration_group_id=$groupId WHERE configuration_key='$key'");
            }
            $sort++;
        }
        $this->runSql("DELETE FROM " . TABLE_CONFIGURATION . " WHERE configuration_key='BACK_IN_STOCK_NOTIFICATION_ENABLED'");
        $this->runSql("UPDATE " . TABLE_CONFIGURATION . " SET configuration_value='4.0.2', set_function='zen_cfg_read_only(', use_function=NULL WHERE configuration_key='BISN_VERSION'");
        $this->runSql("UPDATE " . TABLE_CONFIGURATION . " SET set_function='zen_cfg_read_only(', use_function=NULL WHERE configuration_key='BISN_SECRET'");
        foreach ([
            ['ceon_bisn', 'BOX_CEON_BACK_IN_STOCK_NOTIFICATIONS', 'FILENAME_CEON_BACK_IN_STOCK_NOTIFICATIONS', '', 'catalog'],
            ['ceon_bisn_cg', 'BOX_CEON_BACK_IN_STOCK_NOTIFICATIONS_CONFIG_GROUP', 'FILENAME_CONFIGURATION', 'gID=' . $groupId, 'configuration'],
        ] as [$key, $label, $file, $params, $menu]) {
            if (!zen_page_key_exists($key)) { zen_register_admin_page($key, $label, $file, $params, $menu, 'Y'); }
            else {
                $params = zen_db_input($params);
                $this->runSql("UPDATE " . TABLE_ADMIN_PAGES . " SET language_key='$label', main_page='$file', page_params='$params', menu_key='$menu' WHERE page_key='$key'");
            }
        }
        return true;
    }

    protected function executeUpgrade($oldVersion): bool { return $this->executeInstall(); }

    protected function executeUninstall(): bool
    {
        try { return $this->uninstallOwnedSettings(); }
        catch (Throwable $e) {
            $this->errorContainer->addError(0, $e->getMessage(), true);
            return false;
        }
    }

    private function uninstallOwnedSettings(): bool
    {
        $keys = "'" . implode("','", self::KEYS) . "'";
        $groups = $this->dbConn->Execute("SELECT DISTINCT configuration_group_id FROM " . TABLE_CONFIGURATION . " WHERE configuration_key IN ($keys)");
        $this->runSql("DELETE FROM " . TABLE_ADMIN_PAGES . " WHERE page_key IN ('ceon_bisn','ceon_bisn_cg')");
        $this->runSql("DELETE FROM " . TABLE_CONFIGURATION . " WHERE configuration_key IN ($keys)");
        foreach ($groups as $group) {
            $id = (int)$group['configuration_group_id'];
            $this->runSql("DELETE FROM " . TABLE_CONFIGURATION_GROUP . " WHERE configuration_group_id=$id AND configuration_group_title='Back In Stock Notifications' AND NOT EXISTS (SELECT 1 FROM " . TABLE_CONFIGURATION . " WHERE configuration_group_id=$id)");
        }
        // Subscription business data is deliberately retained, including unsubscribe codes.
        $this->runSql("DROP TABLE IF EXISTS " . DB_PREFIX . "bisn_rate");
        $this->runSql("DROP TABLE IF EXISTS " . DB_PREFIX . "bisn_state");
        return true;
    }
}
