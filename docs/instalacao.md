# Instalação

1. WooCommerce ativo.
2. Ativar OtterMail.
3. **OtterMail → Configurações**: remetente, lote (default 50), captura de carrinho, minutos para abandono.
4. Instalar um plugin SMTP. Ver [smtp.md](smtp.md).
5. Criar um público (ex.: status completed).
6. Enviar um email de teste a partir de um template.

## HPOS

O plugin declara compatibilidade com Custom Order Tables na inicialização (`before_woocommerce_init`). Pedidos são lidos via APIs WooCommerce / tabelas de lookup, não via `wp_posts` de `shop_order`.
