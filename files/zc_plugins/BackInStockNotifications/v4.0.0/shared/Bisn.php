<?php
/** Back in stock notifications. Maintained by Melanie Prough, PRO-Webs, Inc.
 * Based on CEON and torvista's BISN. GPL-3.0-or-later; see LICENSE.
 */
if (!defined('IS_ADMIN_FLAG')) { die('Illegal Access'); }

final class ProWebsBisn
{
    public static function setting(string $key, int $default, int $min, int $max): int
    {
        return max($min, min($max, defined($key) && is_numeric(constant($key)) ? (int)constant($key) : $default));
    }

    public static function enabled(): bool
    {
        return defined('BISN_VERSION') && defined('BACK_IN_STOCK_NOTIFICATIONS_ENABLED') && BACK_IN_STOCK_NOTIFICATIONS_ENABLED === '1';
    }

    public static function h($text): string
    {
        return htmlspecialchars((string)$text, ENT_QUOTES | ENT_SUBSTITUTE, defined('CHARSET') ? CHARSET : 'UTF-8');
    }

    public static function scalar(array $input, string $key): string
    {
        return isset($input[$key]) && is_scalar($input[$key]) ? trim((string)$input[$key]) : '';
    }

    public static function csrf(): string
    {
        if (empty($_SESSION['bisn_csrf'])) { $_SESSION['bisn_csrf'] = bin2hex(random_bytes(32)); }
        return '<input type="hidden" name="bisn_csrf" value="' . self::h($_SESSION['bisn_csrf']) . '">';
    }

    public static function validPost(): bool
    {
        return ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST'
            && !empty($_SESSION['bisn_csrf'])
            && hash_equals($_SESSION['bisn_csrf'], self::scalar($_POST, 'bisn_csrf'));
    }

    /** Never trust forwarded headers; use the address supplied by the web server. */
    public static function consumeRate(string $scope, int $limit): bool
    {
        global $db;
        $table = DB_PREFIX . 'bisn_rate';
        $bucket = (int)floor(time() / 300);
        $hash = hash_hmac('sha256', $scope, defined('BISN_SECRET') ? BISN_SECRET : '');
        $db->Execute("INSERT INTO $table (scope_hash,bucket,hits) VALUES ('$hash',$bucket,1)
            ON DUPLICATE KEY UPDATE hits=hits+1");
        $row = $db->Execute("SELECT hits FROM $table WHERE scope_hash='$hash' AND bucket=$bucket");
        $db->Execute("DELETE FROM $table WHERE bucket < " . ($bucket - 2) . " LIMIT 100");
        return (int)$row->fields['hits'] <= $limit;
    }

    public static function customer(): array
    {
        global $db;
        $id = (int)($_SESSION['customer_id'] ?? 0);
        if ($id < 1) { return []; }
        $row = $db->Execute("SELECT customers_id,customers_firstname,customers_lastname,customers_email_address
            FROM " . TABLE_CUSTOMERS . " WHERE customers_id=$id LIMIT 1");
        return $row->EOF ? [] : $row->fields;
    }

    /** Only logged-in ownership. A guest email is not proof of an account's identity. */
    public static function ownership(int $customerId): string
    {
        return 'customer_id=' . max(0, $customerId);
    }

    public static function product(int $id, int $language): array
    {
        global $db;
        $row = $db->Execute("SELECT p.products_id,p.products_quantity,p.products_status,p.products_model,
            pd.products_name FROM " . TABLE_PRODUCTS . " p JOIN " . TABLE_PRODUCTS_DESCRIPTION . " pd
            ON pd.products_id=p.products_id AND pd.language_id=$language
            WHERE p.products_id=$id AND p.products_status=1 LIMIT 1");
        return $row->EOF ? [] : $row->fields;
    }

    public static function subscribe(int $productId, int $language, string $name, string $email): string
    {
        global $db;
        if (!self::enabled()) { return 'disabled'; }
        $product = self::product($productId, $language);
        if (!$product || (float)$product['products_quantity'] > 0) { return 'unavailable'; }
        if ($name === '' || strlen($name) > 64 || strlen($email) > 96
            || preg_match('/[\r\n\x00]/', $name . $email) || !zen_validate_email($email)) { return 'invalid'; }
        $customer = self::customer();
        if (defined('BACK_IN_STOCK_REQUIRES_LOGIN') && BACK_IN_STOCK_REQUIRES_LOGIN === '1' && !$customer) { return 'login'; }
        $email = strtolower($email);
        $customerId = $customer && strcasecmp($customer['customers_email_address'], $email) === 0 ? (int)$customer['customers_id'] : 0;
        $escapedEmail = zen_db_input($email);
        // Serialize duplicate detection across sessions. Never hold this lock for email delivery.
        $lock = 'bisn-sub-' . substr(hash('sha256', DB_DATABASE . DB_PREFIX . $productId . $email), 0, 48);
        $acquired = $db->Execute("SELECT GET_LOCK('$lock',0) AS acquired");
        if ((int)$acquired->fields['acquired'] !== 1) { return 'busy'; }
        try {
            $table = TABLE_BACK_IN_STOCK_NOTIFICATION_SUBSCRIPTIONS;
            $duplicate = $db->Execute("SELECT id FROM $table WHERE product_id=$productId AND
                (email_address='$escapedEmail'" . ($customerId > 0 ? " OR customer_id=$customerId" : '') . ") LIMIT 1");
            if (!$duplicate->EOF) { return 'subscribed'; }
            if (!self::consumeRate('ip:' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'), self::setting('BISN_IP_LIMIT', 5, 1, 50))
                || !self::consumeRate('email:' . $email, self::setting('BISN_EMAIL_LIMIT', 3, 1, 20))) { return 'rate'; }
            zen_db_perform($table, [
                'product_id' => $productId,
                'customer_id' => $customerId > 0 ? $customerId : 'null',
                'name' => $name,
                'email_address' => $email,
                'subscription_code' => bin2hex(random_bytes(32)),
                'languages_id' => $language,
                'date_subscribed' => date('Y-m-d H:i:s'),
            ]);
            return 'subscribed';
        } finally { $db->Execute("SELECT RELEASE_LOCK('$lock')"); }
    }

    /** A single bounded query, no per-product or per-recipient queries. */
    public static function ready(int $language, int $limit, int $productId = 0): array
    {
        global $db;
        $limit = max(1, min(50, $limit));
        $filter = $productId > 0 ? " AND s.product_id=$productId" : '';
        $result = $db->Execute("SELECT s.*,p.products_model,pd.products_name,pt.type_handler,
            c.customers_firstname,c.customers_lastname,c.customers_email_address
            FROM " . TABLE_BACK_IN_STOCK_NOTIFICATION_SUBSCRIPTIONS . " s
            JOIN " . TABLE_PRODUCTS . " p ON p.products_id=s.product_id
            JOIN " . TABLE_PRODUCTS_DESCRIPTION . " pd ON pd.products_id=p.products_id AND pd.language_id=s.languages_id
            LEFT JOIN " . TABLE_PRODUCT_TYPES . " pt ON pt.type_id=p.products_type
            LEFT JOIN " . TABLE_CUSTOMERS . " c ON c.customers_id=s.customer_id
            WHERE s.languages_id=$language AND p.products_status=1 AND p.products_quantity>0
            AND (s.next_attempt IS NULL OR s.next_attempt<=NOW()) $filter ORDER BY s.id LIMIT $limit");
        $rows = [];
        foreach ($result as $row) { $rows[] = $row; }
        return $rows;
    }

    /** zen_mail returns '' on success and false when delivery was skipped. */
    public static function delivered($result): bool { return $result === ''; }

    public static function sendBatch(int $language, bool $test = false, int $productId = 0): array
    {
        global $db;
        $report = ['sent' => 0, 'failed' => 0, 'status' => 'ready'];
        if (!self::enabled()) { $report['status'] = 'disabled'; return $report; }
        if (!defined('SEND_EMAILS') || SEND_EMAILS !== 'true') { $report['status'] = 'mail_disabled'; return $report; }
        $lock = 'bisn-send-' . substr(hash('sha256', DB_DATABASE . DB_PREFIX), 0, 48);
        $acquired = $db->Execute("SELECT GET_LOCK('$lock',0) AS acquired");
        if ((int)$acquired->fields['acquired'] !== 1) { $report['status'] = 'busy'; return $report; }
        try {
            $state = DB_PREFIX . 'bisn_state';
            $key = $test ? 'last_test' : 'last_batch';
            $last = $db->Execute("SELECT state_value FROM $state WHERE state_key='$key'");
            $interval = self::setting('BISN_INTERVAL', 300, 300, 86400);
            if (!$last->EOF && (int)$last->fields['state_value'] + $interval > time()) {
                $report['status'] = 'cooldown'; return $report;
            }
            $rows = self::ready($language, $test ? 1 : self::setting('BISN_BATCH_SIZE', 10, 1, 50), $productId);
            if (!$rows) { $report['status'] = 'empty'; return $report; }
            $now = time();
            $db->Execute("INSERT INTO $state (state_key,state_value) VALUES ('$key',$now)
                ON DUPLICATE KEY UPDATE state_value=$now");
            $started = microtime(true);
            foreach ($rows as $row) {
                // Keep work bounded between SMTP calls. Configure a transport timeout on the server.
                if (microtime(true) - $started >= 20) { break; }
                $email = !empty($row['email_address']) ? $row['email_address'] : ($row['customers_email_address'] ?? '');
                $name = $row['name'] ?: trim(($row['customers_firstname'] ?? '') . ' ' . ($row['customers_lastname'] ?? ''));
                if ($test) { $email = defined('BISN_TEST_EMAIL_TO') && BISN_TEST_EMAIL_TO !== '' ? BISN_TEST_EMAIL_TO : EMAIL_FROM; }
                $productPage = !empty($row['type_handler']) ? $row['type_handler'] . '_info' : 'product_info';
                $url = zen_catalog_href_link($productPage, 'products_id=' . (int)$row['product_id'], 'SSL');
                $cancel = !empty($row['subscription_code'])
                    ? zen_catalog_href_link(FILENAME_BACK_IN_STOCK_NOTIFICATION_UNSUBSCRIBE, 'id=' . (int)$row['id'] . '&code=' . rawurlencode($row['subscription_code']), 'SSL')
                    : zen_catalog_href_link(FILENAME_ACCOUNT_BACK_IN_STOCK_NOTIFICATIONS, '', 'SSL');
                $subject = ($test ? '[TEST] ' : '') . BISN_MAIL_SUBJECT;
                $text = sprintf(BISN_MAIL_BODY, $name, $row['products_name'], $url, $cancel, STORE_NAME);
                $html = nl2br(self::h($text));
                $result = false;
                try {
                    if (zen_validate_email($email)) {
                        $result = zen_mail($name, $email, $subject, $text, STORE_NAME, EMAIL_FROM, ['EMAIL_MESSAGE_HTML' => $html], 'default');
                    }
                } catch (Throwable $e) {
                    error_log('BISN notification transport failed for subscription ' . (int)$row['id']);
                }
                $id = (int)$row['id'];
                if (self::delivered($result)) {
                    $report['sent']++;
                    if (!$test) {
                        // Delete only this delivered subscription, never all subscriptions for an email.
                        $db->Execute("DELETE FROM " . TABLE_BACK_IN_STOCK_NOTIFICATION_SUBSCRIPTIONS . " WHERE id=$id");
                    }
                } else {
                    $report['failed']++;
                    if (!$test) {
                        // A failed oldest row must not starve newer requests in the next batch.
                        $db->Execute("UPDATE " . TABLE_BACK_IN_STOCK_NOTIFICATION_SUBSCRIPTIONS . "
                            SET attempts=attempts+1,next_attempt=DATE_ADD(NOW(),INTERVAL 1 HOUR) WHERE id=$id");
                    }
                }
            }
            return $report;
        } finally { $db->Execute("SELECT RELEASE_LOCK('$lock')"); }
    }
}
