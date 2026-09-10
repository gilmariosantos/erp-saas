<x-mail::message>
# Olá, {{ $nomeResponsavel }}

@if($diasRestantes <= 1)
Seu teste grátis do NotaGestão **termina amanhã**.
@else
Seu teste grátis do NotaGestão termina em **{{ $diasRestantes }} dias**.
@endif

Para não perder o acesso ao sistema, aos seus dados e à emissão de notas, escolha um plano e continue operando sem interrupção.

<x-mail::button :url="$urlAssinatura">
Escolher meu plano
</x-mail::button>

Se precisar de mais tempo ou tiver dúvidas sobre qual plano escolher, responda este e-mail.

Equipe NotaGestão
</x-mail::message>
