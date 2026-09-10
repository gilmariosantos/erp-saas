<x-mail::message>
# Pagamento confirmado! ✓

Obrigado, {{ $nomeResponsavel }}. Recebemos seu pagamento e sua assinatura está ativa.

<x-mail::panel>
**Fatura:** {{ $numeroFatura }}
**Valor:** R$ {{ number_format($valor, 2, ',', '.') }}
**Plano:** {{ $planoNome }}
**Próxima cobrança:** {{ $proximaCobranca }}
</x-mail::panel>

Seu acesso continua liberado sem interrupções. Bom trabalho!

Equipe NotaGestão
</x-mail::message>
