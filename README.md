# OtterMail

Email marketing inteligente para WooCommerce. Campanhas segmentadas e recuperação de carrinhos abandonados, no próprio WordPress.

## Requisitos

- WordPress 6.0+
- PHP 7.4+
- WooCommerce 8.0+ (obrigatório, com HPOS declarado)
- Um plugin SMTP (WP Mail SMTP, FluentSMTP, etc.) — o OtterMail usa `wp_mail()` e **não** envia SMTP por conta própria

## Instalação

1. Ative o WooCommerce.
2. Ative o OtterMail em Plugins.
3. Configure remetente e captura de carrinho em **OtterMail → Configurações**.
4. Configure a entrega de email no WordPress (plugin SMTP).

## Fluxos

**Campanha:** Público → Template → Campanha → snapshot de destinatários → Action Scheduler → `wp_mail()`.

**Carrinho:** Captura → abandono → automação → email → restaurar carrinho → pedido → receita recuperada.

## Documentação

- [docs/instalacao.md](docs/instalacao.md)
- [docs/hooks.md](docs/hooks.md)
- [docs/variaveis.md](docs/variaveis.md)
- [docs/smtp.md](docs/smtp.md)
- [docs/carrinho.md](docs/carrinho.md)
- [docs/troubleshooting.md](docs/troubleshooting.md)