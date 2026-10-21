# Audit and modernization findings

Baseline: a0fef05, inherited 3.2.3alpha loose-file release.

| Finding | Version 4 treatment |
| --- | --- |
| Account query compared each subscription's email with its own customer's email, without constraining that branch to the signed-in customer | Query and every account deletion use the authenticated customer_id only |
| Guest email could resolve an existing customer account without authentication | Guest requests remain guest-owned |
| Login-required mode synthesized a submission on GET | Form display and login return do not mutate data; POST required |
| Admin options sent mail or removed orphan requests on GET | Explicit POST actions with session token |
| Unescaped email addresses interpolated into subscription and sender queries | Structured inserts, escaped query values, integer IDs |
| Unsubscribe code derived from time and MD5 | random_bytes(32); existing codes retained |
| CAPTCHA message code used assignment instead of comparison | Old handler removed; observer error flag blocks submission |
| Missing form fields and array inputs could reach string operations | Scalar extraction and validation before processing |
| SMTP false return could be interpreted as success | Exact empty-string success check; failed/skipped requests retained |
| Unlimited execution time and unrestricted recipient scan | Bounded query, count, cooldown, time budget, and database lock |
| Sender queried product type repeatedly inside recipient/product loops | Product type joined into the bounded queue query |
| Inactive products could be mailed | Both active status and positive quantity required |
| Admin and account lists could load all subscriptions | 50 records per page, no Show all option |
| Installation was triggered by opening the admin utility | Scripted Plugin Manager lifecycle |
| Missing subscription lookup indexes | Four explicit lookup/queue indexes |
| Installer could add a duplicate enabled-setting row | Exact-key existence checks and preservation |
| Template merges and CEON placeholder substitution complicated maintenance | Standard forms, language arrays, and a template event |
| Embedded docs contained unrelated payment-plugin assets and outdated instructions | Replaced with focused documentation; old README archived |

The maintained package intentionally uses manual sending and parent-product availability. It does not add attribute stock support or a storefront-triggered background worker.

See TECHNICAL_NOTES.md for delivery races and the limits of automated validation.
