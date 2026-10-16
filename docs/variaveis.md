# Variáveis de template

Registradas em `OtterMail\Templates\Variables`. Filtro: `ottermail_template_variables`.

| Token | Uso |
|-------|-----|
| `{{customer.first_name}}` | Primeiro nome |
| `{{customer.last_name}}` | Sobrenome |
| `{{customer.email}}` | Email |
| `{{store.name}}` | Nome da loja |
| `{{store.url}}` | URL da loja |
| `{{cart.total}}` | Total do carrinho |
| `{{cart.items}}` | Tabela HTML dos itens |
| `{{cart.restore_url}}` | Link assinado de restauração |
| `{{coupon.code}}` | Cupom WooCommerce existente |
| `{{unsubscribe.url}}` | Descadastro (HMAC) |

Preview, teste e envio usam o mesmo `TemplateRenderer`.
