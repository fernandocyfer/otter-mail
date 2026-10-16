# SMTP

O OtterMail **não** envia email por SMTP próprio e **não** integra Brevo, Amazon SES, Mailgun ou Postmark na V1.

Todo disparo passa por `wp_mail()`. Qualquer plugin que intercepte essa função funciona:

- WP Mail SMTP
- FluentSMTP
- SES / Brevo / Mailgun via plugin de transporte

`MailerInterface` + `WpMailMailer` permitem um `SesMailer` futuro sem reescrever campanhas.

`wp_mail() === true` só indica que o mecanismo local aceitou o envio. Relatórios usam status `sent` / `failed`, nunca `delivered`.
