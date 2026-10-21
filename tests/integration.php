<?php
/** Run only against a disposable bisn_test database and a clean Zen Cart source checkout. */
error_reporting(E_ALL);
set_error_handler(static function ($severity, $message, $file, $line) {
    if (error_reporting() & $severity) { throw new ErrorException($message, 0, $severity, $file, $line); }
    return false;
});
$core = rtrim(getenv('ZEN_CART_ROOT'), '/');
if (!is_file($core . '/includes/version.php')) { throw new RuntimeException('Set ZEN_CART_ROOT to a Zen Cart source checkout.'); }
define('IS_ADMIN_FLAG', true);
define('DB_PREFIX', 'bisntest_');
define('DB_DATABASE', 'bisn_test');
define('DB_PORT', (int)(getenv('BISN_DB_PORT') ?: 3306));
define('DB_CHARSET', 'utf8mb4');
define('CHARSET', 'UTF-8');
define('DIR_FS_CATALOG', $core . '/');
define('DIR_FS_ADMIN', $core . '/admin/');
define('DIR_WS_MODULES', 'includes/modules/');
define('DIR_WS_CLASSES', 'includes/classes/');
define('DIR_WS_TEMPLATES', 'includes/templates/');
define('DIR_WS_TEMPLATE', 'includes/templates/responsive_classic/');
define('PLUGIN_INSTALL_SQL_FAILURE', 'Plugin SQL failure');
define('EMAIL_FROM', 'store@example.com');
define('SEND_EMAILS', 'true');
define('STORE_NAME', 'BISN test store');
require $core . '/includes/version.php';
require $core . '/includes/database_tables.php';
require $core . '/includes/classes/traits/Singleton.php';
require $core . '/includes/classes/traits/NotifierManager.php';
require $core . '/includes/classes/traits/ObserverManager.php';
require $core . '/includes/classes/class.base.php';
require $core . '/includes/classes/EventDto.php';
require $core . '/includes/classes/db/mysql/query_factory.php';
require $core . '/includes/functions/database.php';
require $core . '/includes/functions/zen_define_default.php';
require $core . '/admin/includes/functions/admin_access.php';
require $core . '/includes/classes/PluginSupport/ScriptedInstallHelpers.php';
require $core . '/includes/classes/PluginSupport/PluginErrorContainer.php';
require $core . '/includes/classes/PluginSupport/ScriptedInstaller.php';
if (is_file($core . '/includes/classes/DbRepositories/LayoutBoxRepository.php')) { require $core . '/includes/classes/DbRepositories/LayoutBoxRepository.php'; }
$host = getenv('BISN_DB_HOST') ?: '127.0.0.1';
$user = getenv('BISN_DB_USER') ?: 'root';
$password = getenv('BISN_DB_PASSWORD') ?: '';
$connection = new mysqli($host, $user, $password, '', DB_PORT);
$connection->query('DROP DATABASE IF EXISTS bisn_test');
$connection->query('CREATE DATABASE bisn_test CHARACTER SET utf8mb4');
$db = new queryFactory();
$db->connect($host, $user, $password, DB_DATABASE, false, true);
$sql = file_get_contents($core . '/zc_install/sql/install/mysql_zencart.sql');
foreach (['configuration', 'configuration_group', 'admin_pages', 'products', 'products_description', 'customers', 'product_types', 'layout_boxes'] as $table) {
    if (!preg_match('/CREATE TABLE ' . $table . ' \(.*?;/s', $sql, $match)) { throw new RuntimeException('Missing core schema ' . $table); }
    $db->Execute(str_replace('CREATE TABLE ' . $table . ' ', 'CREATE TABLE ' . DB_PREFIX . $table . ' ', $match[0]));
}
$plugin = dirname(__DIR__) . '/files/zc_plugins/BackInStockNotifications/v4.0.0';
require $plugin . '/filenames.php';
require $plugin . '/Installer/ScriptedInstaller.php';
require $plugin . '/shared/bootstrap.php';
foreach (require $plugin . '/admin/includes/languages/english/extra_definitions/lang.bisn.php' as $key => $value) { define($key, $value); }

$assertions = 0;
function check($condition, string $message): void {
    global $assertions;
    $assertions++;
    if (!$condition) { throw new RuntimeException($message); }
}
function value(string $sql) {
    global $db;
    return array_values($db->Execute($sql)->fields)[0] ?? null;
}
function reloadConfig(): void {
    global $db;
    foreach ($db->Execute('SELECT configuration_key,configuration_value FROM ' . TABLE_CONFIGURATION) as $row) {
        if (!defined($row['configuration_key'])) { define($row['configuration_key'], $row['configuration_value']); }
    }
}
// Mail capture exercises sender failure handling without delivering real email.
$mailResult = '';
$mails = [];
function zen_update_modules_cache() {}
function zen_record_admin_activity($message, $severity) {}
function zen_mail(...$args) { global $mailResult, $mails; $mails[] = $args; return $mailResult; }
function zen_validate_email($email) { return (bool)filter_var($email, FILTER_VALIDATE_EMAIL); }
function zen_catalog_href_link($page, $params = '', $connection = '') { return 'https://example.com/index.php?main_page=' . $page . ($params ? '&' . $params : ''); }
function zen_href_link($page, $params = '', $connection = '') { return zen_catalog_href_link($page, $params, $connection); }

$errors = new Zencart\PluginSupport\PluginErrorContainer();
$installer = new ScriptedInstaller($db, $errors);
$installer->setVersionDetails(['pluginKey' => 'BackInStockNotifications', 'pluginDir' => $plugin, 'version' => 'v4.0.0', 'oldVersion' => null]);
check($installer->doInstall() === true, 'Clean install: ' . implode('; ', $errors->getLogErrors()));
check(!$errors->hasErrors(), 'Installer error container is empty');
check((int)value('SELECT COUNT(*) FROM ' . TABLE_ADMIN_PAGES) === 2, 'Both menus installed');
check(value("SELECT set_function FROM " . TABLE_CONFIGURATION . " WHERE configuration_key='BISN_VERSION'") === 'zen_cfg_read_only(', 'Version cannot be edited in normal configuration UI');
$db->Execute("UPDATE " . TABLE_CONFIGURATION . " SET configuration_value='7' WHERE configuration_key='BISN_BATCH_SIZE'");
check($installer->doUpgrade('3.2.3alpha') === true, 'Upgrade is repeatable');
check(value("SELECT configuration_value FROM " . TABLE_CONFIGURATION . " WHERE configuration_key='BISN_BATCH_SIZE'") === '7', 'Upgrade preserves settings');
check((int)value('SELECT COUNT(*) FROM ' . TABLE_CONFIGURATION) === 10, 'Upgrade does not duplicate configuration');
reloadConfig();
$sub = TABLE_BACK_IN_STOCK_NOTIFICATION_SUBSCRIPTIONS;
$db->Execute("INSERT INTO " . TABLE_PRODUCT_TYPES . " (type_id,type_name,type_handler) VALUES (1,'Product','product')");
foreach ([1,2,3,4,5,6,7,8,9,10,11,12] as $id) {
    $db->Execute("INSERT INTO " . TABLE_PRODUCTS . " (products_id,products_quantity,products_status,products_type,products_model,products_date_added) VALUES ($id,0,1,1,'T$id',NOW())");
    $db->Execute("INSERT INTO " . TABLE_PRODUCTS_DESCRIPTION . " (products_id,language_id,products_name) VALUES ($id,1,'Test product $id')");
}
$db->Execute("INSERT INTO " . TABLE_CUSTOMERS . " (customers_id,customers_firstname,customers_lastname,customers_email_address) VALUES
    (1,'Alice','One','alice@example.com'),(2,'Bob','Two','bob@example.com')");
$_SESSION = ['languages_id' => 1, 'customer_id' => 1];
$_SERVER['REMOTE_ADDR'] = '192.0.2.1';
check(ProWebsBisn::subscribe(1, 1, "Alice O'One", 'alice@example.com') === 'subscribed', 'Subscribe customer');
check(ProWebsBisn::subscribe(1, 1, 'Alice', 'ALICE@example.com') === 'subscribed', 'Case insensitive duplicate');
check((int)value("SELECT COUNT(*) FROM $sub") === 1, 'One subscription per product/identity');
check(strlen(value("SELECT subscription_code FROM $sub LIMIT 1")) === 64, 'Cryptographic unsubscribe code');
check((int)value("SELECT customer_id FROM $sub LIMIT 1") === 1, 'Authenticated ownership stored');
$_SESSION['customer_id'] = 2;
$_SERVER['REMOTE_ADDR'] = '192.0.2.2';
check(ProWebsBisn::subscribe(2, 1, 'Bob', 'bob@example.com') === 'subscribed', 'Second customer subscribes');
$owner = ProWebsBisn::ownership(1);
check((int)value("SELECT COUNT(*) FROM $sub WHERE $owner") === 1, 'Alice cannot see Bob subscriptions');
$db->Execute("DELETE FROM $sub WHERE $owner AND product_id=2");
check((int)value("SELECT COUNT(*) FROM $sub WHERE customer_id=2") === 1, 'Foreign cancellation blocked by ownership');
unset($_SESSION['customer_id']);
$_SERVER['REMOTE_ADDR'] = '192.0.2.3';
check(ProWebsBisn::subscribe(3, 1, 'Guest', 'alice@example.com') === 'subscribed', 'Guest can request an address');
check(value("SELECT customer_id FROM $sub WHERE product_id=3") === null, 'Guest cannot claim a customer account');
check(ProWebsBisn::subscribe(4, 1, 'Guest', 'guest@example.com') === 'subscribed', 'Guest subscribes');
check(ProWebsBisn::subscribe(5, 1, 'Guest', 'guest@example.com') === 'subscribed', 'Guest second product');
check(ProWebsBisn::subscribe(6, 1, 'Guest', 'guest@example.com') === 'subscribed', 'Guest third product');
check(ProWebsBisn::subscribe(7, 1, 'Guest', 'guest@example.com') === 'rate', 'Persistent email throttle');
check(ProWebsBisn::subscribe(8, 1, 'Other', 'other@example.com') === 'rate', 'Persistent IP throttle');
check(ProWebsBisn::subscribe(99, 1, 'Guest', 'a@example.com') === 'unavailable', 'Missing product rejected');
check(ProWebsBisn::subscribe(8, 1, "Bad\r\nName", 'a@example.com') === 'invalid', 'Header injection rejected');
$_SERVER['REQUEST_METHOD'] = 'GET';
ProWebsBisn::csrf();
$_POST = ['bisn_csrf' => $_SESSION['bisn_csrf']];
check(!ProWebsBisn::validPost(), 'GET never authorizes mutation');
$_SERVER['REQUEST_METHOD'] = 'POST';
check(ProWebsBisn::validPost(), 'Session form token accepted');
$_POST['bisn_csrf'] = ['bad'];
check(!ProWebsBisn::validPost(), 'Array token rejected without warnings');
check(!ProWebsBisn::delivered(false) && !ProWebsBisn::delivered('error') && ProWebsBisn::delivered(''), 'Mail return values handled exactly');
$db->Execute("UPDATE " . TABLE_PRODUCTS . " SET products_quantity=10");
$db->Execute("UPDATE " . TABLE_PRODUCTS . " SET products_status=0 WHERE products_id=2");
check(count(ProWebsBisn::ready(1, 2)) === 2, 'Queue query respects hard limit');
check(count(ProWebsBisn::ready(2, 10)) === 0, 'Queue does not mix languages');
$before = (int)value("SELECT COUNT(*) FROM $sub");
check(count(ProWebsBisn::ready(1, 10)) > 0 && !$mails, 'Preview sends no mail');
$test = ProWebsBisn::sendBatch(1, true);
check($test['sent'] === 1 && count($mails) === 1, 'Test sends one sample');
check((int)value("SELECT COUNT(*) FROM $sub") === $before, 'Test never removes subscription');
check(ProWebsBisn::sendBatch(1, true)['status'] === 'cooldown', 'Test has independent cooldown');
$mails = [];
$mailResult = false;
$failed = ProWebsBisn::sendBatch(1);
check($failed['sent'] === 0 && $failed['failed'] > 0, 'Skipped mail not treated as delivered');
check((int)value("SELECT COUNT(*) FROM $sub") === $before, 'Failed delivery retains subscriptions');
check((int)value("SELECT COUNT(*) FROM $sub WHERE attempts=1 AND next_attempt>NOW()") > 0, 'Failures get retry delay');
check(ProWebsBisn::sendBatch(1)['status'] === 'cooldown', 'Refresh cannot bypass batch interval');
$db->Execute("DELETE FROM " . DB_PREFIX . "bisn_state");
$db->Execute("UPDATE $sub SET next_attempt=NULL");
$mailResult = '';
$mails = [];
$sent = ProWebsBisn::sendBatch(1);
check($sent['sent'] > 0 && $sent['failed'] === 0, 'Delivery succeeds');
check((int)value("SELECT COUNT(*) FROM $sub WHERE product_id=2") === 1, 'Disabled product excluded');
check((int)value("SELECT COUNT(*) FROM $sub") === $before - $sent['sent'], 'Only delivered rows deleted');
foreach ($mails as $mail) { check(strpos($mail[3], 'back_in_stock_notification_unsubscribe') !== false, 'Every new notification has cancellation link'); }


// A second database connection proves the advisory lock blocks a simultaneous sender.
$db->Execute("DELETE FROM " . DB_PREFIX . "bisn_state");
$other = new mysqli($host, $user, $password, DB_DATABASE, DB_PORT);
$sendLock = 'bisn-send-' . substr(hash('sha256', DB_DATABASE . DB_PREFIX), 0, 48);
$other->query("SELECT GET_LOCK('$sendLock',0)");
check(ProWebsBisn::sendBatch(1)['status'] === 'busy', 'Concurrent sender rejected');
$other->query("SELECT RELEASE_LOCK('$sendLock')");
$db->Execute('UPDATE ' . TABLE_PRODUCTS . ' SET products_quantity=0 WHERE products_id=11');
$subscriptionLock = 'bisn-sub-' . substr(hash('sha256', DB_DATABASE . DB_PREFIX . '11race@example.com'), 0, 48);
$other->query("SELECT GET_LOCK('$subscriptionLock',0)");
check(ProWebsBisn::subscribe(11, 1, 'Race', 'race@example.com') === 'busy', 'Concurrent duplicate subscription rejected');
$other->query("SELECT RELEASE_LOCK('$subscriptionLock')");
$other->close();

// Runtime menu self-repair preserves existing permissions and uses the actual gID.
$db->Execute("UPDATE " . TABLE_ADMIN_PAGES . " SET display_on_menu='N' WHERE page_key='ceon_bisn'");
$db->Execute("DELETE FROM " . TABLE_ADMIN_PAGES . " WHERE page_key='ceon_bisn_cg'");
require $plugin . '/admin/includes/functions/extra_functions/bisn_menu.php';
check(zen_page_key_exists('ceon_bisn_cg'), 'Missing configuration menu repaired');
check(value("SELECT display_on_menu FROM " . TABLE_ADMIN_PAGES . " WHERE page_key='ceon_bisn'") === 'N', 'Existing menu permissions preserved');

// Compile the observer against the real base class and exercise its actual event.
require $plugin . '/catalog/includes/classes/observers/auto.bisn.php';
$observer = new zcObserverBisn();
$current_page_base = 'product_info';
$_GET = ['products_id' => 12];
$db->Execute("UPDATE " . TABLE_PRODUCTS . " SET products_quantity=0 WHERE products_id=12");
ob_start();
$notifier = new base();
$notifier->notify('NOTIFY_PAGE_BODY_BEGIN', 'product_info');
$output = ob_get_clean();
check(strpos($output, 'back_in_stock_notification_subscribe') !== false, 'Product observer renders supported core event');

// Uninstall retains requests; reinstall restores settings and reconnects records.
$db->Execute("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_title,configuration_key,configuration_value,configuration_description,configuration_group_id,date_added) VALUES ('Unrelated','OTHER_PLUGIN','keep','Unrelated setting',1,NOW())");
$remaining = (int)value("SELECT COUNT(*) FROM $sub");
check($installer->doUninstall() === true, 'Uninstall');
check((int)value("SELECT COUNT(*) FROM $sub") === $remaining, 'Uninstall preserves business data');
check(value("SELECT configuration_value FROM " . TABLE_CONFIGURATION . " WHERE configuration_key='OTHER_PLUGIN'") === 'keep', 'Uninstall preserves unrelated settings');
check((int)value('SELECT COUNT(*) FROM ' . TABLE_ADMIN_PAGES) === 0, 'Uninstall removes only owned menus');
check($installer->doInstall() === true, 'Reinstall');
check((int)value("SELECT COUNT(*) FROM $sub") === $remaining, 'Reinstall preserves retained data');

// Legacy migration: keep business data, add missing language/retry fields and indexes.
$db->Execute("DROP TABLE $sub");
$db->Execute("CREATE TABLE $sub (
    id int unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY, product_id int unsigned NOT NULL DEFAULT 0,
    customer_id int unsigned DEFAULT NULL, subscription_code varchar(10) DEFAULT NULL,
    name varchar(64) NOT NULL DEFAULT '', email_address varchar(96) DEFAULT NULL,
    date_subscribed datetime NOT NULL
)");
$db->Execute("INSERT INTO $sub (product_id,subscription_code,name,email_address,date_subscribed)
    VALUES (12,'aabbccddee','Legacy','legacy@example.com',NOW())");
$db->Execute("UPDATE " . TABLE_CONFIGURATION . " SET configuration_value='0' WHERE configuration_key='BACK_IN_STOCK_NOTIFICATIONS_ENABLED'");
check($installer->doInstall() === true, 'Loose file schema migrates through Plugin Manager install');
check(value("SELECT subscription_code FROM $sub LIMIT 1") === 'aabbccddee', 'Legacy unsubscribe code preserved');
check((int)value("SELECT languages_id FROM $sub LIMIT 1") === 1, 'Legacy language default added');
check((int)value("SELECT attempts FROM $sub LIMIT 1") === 0, 'Legacy retries initialized');
check(value("SELECT configuration_value FROM " . TABLE_CONFIGURATION . " WHERE configuration_key='BACK_IN_STOCK_NOTIFICATIONS_ENABLED'") === '0', 'Disabled legacy setting preserved');

// Exercise the actual account and unsubscribe page handlers without network or real mail.
class BisnTestLanguageLoader {
    public function setCurrentPage($page) {}
    public function loadLanguageForView() {}
}
class BisnTestBreadcrumb { public function add(...$args) {} }
$languageLoader = new BisnTestLanguageLoader();
$breadcrumb = new BisnTestBreadcrumb();
$current_page = 'account_back_in_stock_notifications';
$zco_notifier = new base();
chdir($core);
$_SESSION['customer_id'] = 1;
$_SESSION['languages_id'] = 1;
$db->Execute("INSERT INTO $sub (product_id,customer_id,subscription_code,name,email_address,date_subscribed,languages_id)
    VALUES (1,1,'1111111111','Alice','alice@example.com',NOW(),1),
           (2,2,'2222222222','Bob','bob@example.com',NOW(),1)");
$bobId = (int)value("SELECT id FROM $sub WHERE customer_id=2 LIMIT 1");
$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST = ['bisn_csrf' => $_SESSION['bisn_csrf'], 'cancel' => [$bobId]];
$_GET = [];
require $plugin . '/catalog/includes/modules/pages/account_back_in_stock_notifications/header_php.php';
check((int)value("SELECT COUNT(*) FROM $sub WHERE id=$bobId") === 1, 'Actual account handler rejects a foreign ID');
$_POST['cancel'] = ['bad' => ['nested']];
require $plugin . '/catalog/includes/modules/pages/account_back_in_stock_notifications/header_php.php';
check((int)value("SELECT COUNT(*) FROM $sub WHERE id=$bobId") === 1, 'Malformed selection has no effect');
$_SESSION['customer_id'] = 2;
$_POST = ['bisn_csrf' => $_SESSION['bisn_csrf'], 'cancel' => [$bobId]];
require $plugin . '/catalog/includes/modules/pages/account_back_in_stock_notifications/header_php.php';
check((int)value("SELECT COUNT(*) FROM $sub WHERE id=$bobId") === 0, 'Actual account handler cancels own ID');
$legacyId = (int)value("SELECT id FROM $sub WHERE email_address='legacy@example.com'");
$_SERVER['REQUEST_METHOD'] = 'GET';
$_GET = ['id' => $legacyId, 'code' => 'aabbccddee'];
$_POST = [];
$current_page = 'back_in_stock_notification_unsubscribe';
require $plugin . '/catalog/includes/modules/pages/back_in_stock_notification_unsubscribe/header_php.php';
check(!empty($bisnSubscription), 'Actual unsubscribe page accepts legacy token');
check((int)value("SELECT COUNT(*) FROM $sub WHERE id=$legacyId") === 1, 'Unsubscribe GET is read only');
$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST = ['id' => $legacyId, 'code' => 'aabbccddee', 'bisn_csrf' => 'invalid'];
require $plugin . '/catalog/includes/modules/pages/back_in_stock_notification_unsubscribe/header_php.php';
check((int)value("SELECT COUNT(*) FROM $sub WHERE id=$legacyId") === 1, 'Unsubscribe POST requires session token');
$_POST['bisn_csrf'] = $_SESSION['bisn_csrf'];
require $plugin . '/catalog/includes/modules/pages/back_in_stock_notification_unsubscribe/header_php.php';
check((int)value("SELECT COUNT(*) FROM $sub WHERE id=$legacyId") === 0, 'Actual unsubscribe POST removes matching token');
$_POST = ['id' => 1, 'code' => ['array'], 'bisn_csrf' => $_SESSION['bisn_csrf']];
require $plugin . '/catalog/includes/modules/pages/back_in_stock_notification_unsubscribe/header_php.php';
check($bisnMessage === BISN_UNKNOWN, 'Malformed unsubscribe code rejected without warnings');
echo "Passed $assertions integration assertions on Zen Cart " . PROJECT_VERSION_MAJOR . '.' . PROJECT_VERSION_MINOR . " / PHP " . PHP_VERSION . "\n";
