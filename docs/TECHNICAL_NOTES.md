# Technical notes

## Runtime boundaries

The package uses an encapsulated observer and page handlers supported by both targeted cores. The observer uses NOTIFY_PAGE_BODY_BEGIN and queries one product only on supported product information pages. It does no catalog-wide scan and sends no email. The account link requires no subscription count query.

The admin page lists 50 records plus one sentinel for pagination. Preview selects at most 50 joined eligible subscription rows. Sending uses the same bounded query, with product type and customer details included, avoiding the inherited per-product query loop. Account management reads 50 records plus one sentinel and every deletion includes the current customer ID.

Legacy subscriptions with no stored email use the linked customer's current email address. New requests store the submitted email and only attach customer_id when it matches the authenticated customer's email. Neither a guest email nor another logged-in customer's arbitrary input establishes account ownership.

## Throttling and locks

bisn_rate uses a primary key of HMAC scope hash and five minute time bucket. An atomic insert/update counts signup attempts that passed basic form and product validation. IP and email limits both apply. At most 100 expired bucket records are pruned on an accepted signup attempt; raw IP and email identities are not stored in this table.

Duplicate subscription requests are serialized with a MySQL/MariaDB named lock scoped to store, product, and normalized address. Existing requests do not consume additional signup quota.

The sender uses a separate database named lock scoped to the store. Locks are held by the active connection and released in finally blocks. MySQL/MariaDB also releases them when the connection closes. The persisted batch timestamp is written before the first email call, so a failed or interrupted request still consumes its cooldown. Both supported database families must permit GET_LOCK and RELEASE_LOCK.

Failed emails are delayed by an hour so that a failing oldest record does not block newer ready requests. A batch processes no more than the configured count and stops starting new mail calls after 20 seconds. The mail transport itself must have an appropriate timeout.

## Delivery semantics

Zen Cart returns an empty string for successful mail submission, false when sending is skipped, and an error string on transport failure. Only the empty string permits deletion.

Successful submission to the transport does not guarantee delivery to the mailbox. No email system can make SMTP delivery and a database mutation one atomic operation. A crash after transport acceptance but before deletion can produce a duplicate on retry. Cancellation after a batch has already loaded its rows can race with in-flight delivery. This plugin makes no exactly-once or delivery-guarantee claim.

The sender honors Zen Cart's SEND_EMAILS setting. Developer email overrides and module skips are still controlled by core Zen Cart. Tests capture the mail function to exercise each result branch.

## Security and data retention

All mutations require POST and a random session form token. Account deletions also constrain customer_id. Guest cancellation requires the subscription ID plus its token, then an explicit confirmation. Stored output is escaped at rendering. Mail addresses are validated and names reject CR, LF, and NUL.

New unsubscribe tokens contain 32 random bytes encoded as hexadecimal. Old hexadecimal tokens remain valid. Existing subscription codes are not regenerated. Product names come from the database, not submitted product-name fields.

The inherited table can contain personal information. Set a retention policy appropriate for the store. The plugin removes delivered requests and explicitly cancelled/deleted requests, but does not silently delete old pending subscriptions. Uninstall preserves them.

## Reproducible tests

Run tests/integration.php against a disposable database named bisn_test and a clean checkout of the supported Zen Cart tag. It drops and recreates only that database. Do not point it at a production server.

Environment:
- ZEN_CART_ROOT: absolute path to the Zen Cart source checkout.
- BISN_DB_HOST: default 127.0.0.1.
- BISN_DB_PORT: default 3306.
- BISN_DB_USER: default root.
- BISN_DB_PASSWORD: default empty.

The suite uses the actual core database schema, queryFactory, scripted installer base class, menu registration functions, observer/event base, and actual account/unsubscribe handlers. Mail delivery, admin activity logging, and the module-cache refresh are isolated test boundaries. GitHub Actions covers PHP 8.0 through 8.5 and valid Zen Cart/PHP pairs. These automated checks do not replace a live template and mail acceptance test.
