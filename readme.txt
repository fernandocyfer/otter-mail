=== OtterMail ===
Contributors: cyferweb
Tags: woocommerce, email, marketing, abandoned cart, campaigns
Requires at least: 6.0
Tested up to: 6.8
Requires PHP: 7.4
WC requires at least: 8.0
WC tested up to: 10.6
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Email marketing inteligente para WooCommerce: campanhas segmentadas e recuperação de carrinhos abandonados.

== Description ==

O OtterMail transforma dados da loja WooCommerce em públicos, campanhas, automações e emails.

* Campanhas com snapshot de destinatários
* Públicos dinâmicos (pedidos, produtos, carrinho)
* Sequência de carrinho abandonado
* Restauração segura do carrinho
* Envio via wp_mail() e Action Scheduler
* Compatível com HPOS

O plugin não configura SMTP. Use um plugin de entrega no WordPress.

== Installation ==

1. Instale e ative o WooCommerce.
2. Envie a pasta `otter-mail` para `wp-content/plugins/`.
3. Ative o OtterMail.
4. Configure SMTP no site e o remetente em OtterMail → Configurações.

== Frequently Asked Questions ==

= O plugin envia email sozinho? =

Não. Ele chama wp_mail(). A entrega depende do SMTP/transporte do WordPress.

= wp_mail() true significa que o cliente recebeu? =

Não. Significa apenas que o transporte local aceitou a mensagem. O status no OtterMail é "sent", nunca "delivered".

= Os dados são apagados ao desativar? =

Não. Só na desinstalação, se a opção "Apagar dados ao desinstalar" estiver marcada.

== Changelog ==

= 0.1.0 =
* Lançamento inicial do MVP.
