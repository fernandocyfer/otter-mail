# Hooks

## Actions

- `ottermail_before_send_email` (`Message $message`)
- `ottermail_after_send_email` (`Message $message`, `bool $result`)
- `ottermail_cart_marked_abandoned` (`$cart`)
- `ottermail_cart_recovered` (`$cart`)
- `ottermail_cart_converted` (`$cart`, `WC_Order $order`)
- `ottermail_track` (stub V1, sem pixel)

## Filters

- `ottermail_template_variables` (`array $map`, `array $context`)
- `ottermail_campaign_recipient_query` (`array $emails`, `Audience $audience`)

## Action Scheduler (grupo `ottermail`)

- `ottermail_process_campaign`
- `ottermail_send_campaign_batch`
- `ottermail_check_abandoned_carts`
- `ottermail_process_automation_step`
