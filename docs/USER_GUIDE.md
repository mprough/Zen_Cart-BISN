# Using back in stock notifications

## Customer requests

On templates with the standard display event, an active product with quantity zero or below displays a link above its main content. Custom templates without that event need the fallback integration shown in the README. The link opens a dedicated form. Guests enter a name, email, and matching email confirmation. Signed-in customers see their account details prefilled and still submit the form.

Requests are saved after successful validation. No signup email is sent. Email is sent only when staff explicitly processes a restocked product.

Signed-in customers manage account-owned requests from their account page. Each page shows at most 50 requests. Select requests to cancel and submit. Guest requests use the link in their restock email. A valid unsubscribe URL first displays a confirmation page; opening the link alone does not delete anything.

## Configuration

| Setting | Default | Behavior |
| --- | --- | --- |
| Enable notifications | 1 | 0 pauses signup and sending; cancellation pages still work |
| Require login | 0 | 1 requires an account before displaying the request form |
| Notification batch size | 10 | Clamped to 1 through 50 subscriptions |
| Batch interval in seconds | 300 | Clamped to 300 through 86400 seconds |
| Subscriptions per IP in five minutes | 5 | Clamped to 1 through 50 |
| Subscriptions per email in five minutes | 3 | Clamped to 1 through 20 |
| Test recipient | Store email | Explicit tests send one sample here |
| Legacy subscription copy addresses | Existing value or empty | Retained for reference; signup copy emails are not sent |

Version and internal privacy key are managed settings. Invalid numeric settings use safe defaults; values outside the supported range are clamped at runtime.

Signup limits use fixed five minute buckets and persist across browsers and sessions. Shared networks share the IP limit. The web server must provide the correct REMOTE_ADDR when behind a reverse proxy; the plugin does not trust forwarded headers.

## Admin page

Open Catalog > Back in stock notifications.

- Filter by product ID, or use 0 for all products.
- Expand Preview next batch to inspect up to the batch limit. No email is sent.
- Send one test email to inspect a sample. It does not remove the subscription.
- Send next batch to process the current admin language. Wait for the cooldown before submitting the next batch.
- Select subscriptions to delete, at most 50 per page.
- Maintenance removes up to 100 requests for deleted products per submission.

Tests and real sending each have a separate cooldown. Both share a lock, so they cannot send concurrently. A batch stops starting new email calls after 20 seconds; a single SMTP call can take longer if the transport's timeout is higher.

Each subscription produces one restock email. A person requesting several restocked products may receive more than one message over one or more batches.

## Languages

English arrays are provided under catalog/includes/languages/english/extra_definitions/ and admin/includes/languages/english/extra_definitions/. Translate the corresponding arrays in the desired language directory. Translate both storefront messages and admin mail text.

Select the matching language in the admin before sending. Requests without a matching product description are skipped until the description is available. No automatic language switching or automatic sending occurs.

## Troubleshooting

A missing menu is repaired on an admin request when the plugin's installed marker is present. Existing registrations are left intact. If access is forbidden, review the admin profile's page permissions.

A batch status of cooldown means the minimum interval has not elapsed. Busy means another batch owns the shared database lock. Mail_disabled means Zen Cart's SEND_EMAILS setting is not true. Empty means no eligible request matched the language, product filter, stock/status, and retry date.

Failed or skipped emails remain and become eligible after one hour. Review Zen Cart's email logs and transport configuration. Do not continually retry a broken mail service.

The test suite captures mail instead of delivering it. Verify a real test email on the development store before use.
