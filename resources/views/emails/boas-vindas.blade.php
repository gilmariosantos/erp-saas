<x-mail::message>
# Bem-vindo ao NotaGestão, {{ $nomeResponsavel }}! 🎉

A conta da **{{ $razaoSocial }}** está pronta para usar. Você tem **{{ $diasTrial }} dias grátis** para explorar tudo — sem cartão de crédito.

## Seus primeiros passos

1. **Configure sua empresa** — dados fiscais e certificado digital
2. **Cadastre produtos e clientes** — a base para vender e emitir notas
3. **Emita sua primeira nota** — teste em homologação antes de valer

<x-mail::button :url="$urlSistema">
Acessar meu sistema
</x-mail::button>

Qualquer dúvida, é só responder este e-mail. Estamos aqui para ajudar.

Abraços,<br>
Equipe NotaGestão
</x-mail::message>
