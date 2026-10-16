# Troubleshooting

**Emails não saem.** Verifique SMTP no WordPress. O OtterMail só chama `wp_mail()`.

**Fila parada.** Action Scheduler precisa de WP-Cron ou cron real do servidor. Em WooCommerce → Status → Agendado, grupo `ottermail`.

**Estimativa zerada.** Confirme pedidos em status `completed` (HPOS). Tabelas `wc_order_stats` / `wc_customer_lookup` aceleram a consulta.

**Checkout sem captura.** Ative a captura nas configurações. O script só carrega em `is_checkout()`. Digite o email e aguarde o debounce (não a cada tecla).

**Restore inválido.** Link expirado (TTL) ou carrinho já convertido. O token precisa ser o HMAC completo.

**WooCommerce inativo.** O plugin mostra aviso e não carrega módulos de domínio — sem fatal error.
