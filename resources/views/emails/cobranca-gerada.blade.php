<x-mail::message>
# Fatura {{ $numeroFatura }}

Olá, {{ $nomeResponsavel }}. Sua fatura foi gerada:

<x-mail::panel>
**Valor:** R$ {{ number_format($valor, 2, ',', '.') }}
**Vencimento:** {{ $vencimento }}
**Forma de pagamento:** {{ ucfirst($metodoPagamento) }}
</x-mail::panel>

@if($metodoPagamento === 'pix' && $pixCopiaCola)
## Pague com PIX
Copie o código abaixo e cole no app do seu banco:

<x-mail::panel>
{{ $pixCopiaCola }}
</x-mail::panel>
@endif

@if($metodoPagamento === 'boleto' && $linhaDigitavel)
## Linha digitável do boleto
<x-mail::panel>
{{ $linhaDigitavel }}
</x-mail::panel>
@endif

@if($linkPagamento)
<x-mail::button :url="$linkPagamento">
Pagar agora
</x-mail::button>
@endif

Assim que o pagamento for confirmado, você recebe a confirmação por e-mail.

Equipe NotaGestão
</x-mail::message>
