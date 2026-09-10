<?php
namespace App\Console\Commands;

use App\Mail\TrialExpirandoMail;
use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

/**
 * Envia avisos de trial expirando.
 * Agendar no scheduler para rodar 1x/dia.
 *
 * Avisa quando faltam 3 dias e quando falta 1 dia para o fim do trial.
 */
class EnviarAvisosTrialCommand extends Command
{
    protected $signature = 'saas:avisar-trials';
    protected $description = 'Envia e-mails de aviso para trials próximos do fim';

    public function handle(): int
    {
        $diasAviso = [3, 1]; // avisa faltando 3 dias e 1 dia
        $enviados = 0;

        foreach ($diasAviso as $dias) {
            $alvo = now()->addDays($dias)->toDateString();

            $tenants = Tenant::whereHas('subscription', function ($q) use ($alvo) {
                $q->where('status', 'trial')
                  ->whereDate('trial_ends_at', $alvo);
            })->with('subscription')->get();

            foreach ($tenants as $tenant) {
                if (! $tenant->email_responsavel) continue;

                Mail::to($tenant->email_responsavel)->send(
                    new TrialExpirandoMail(
                        nomeResponsavel: $tenant->nome_responsavel ?? 'cliente',
                        diasRestantes: $dias,
                        urlAssinatura: $this->urlAssinatura($tenant),
                    )
                );
                $enviados++;
            }
        }

        $this->info("Avisos de trial enviados: {$enviados}");
        return self::SUCCESS;
    }

    private function urlAssinatura(Tenant $tenant): string
    {
        $dominio = config('tenancy.central_domains')[0] ?? config('app.url');
        return "https://{$tenant->subdominio}.{$dominio}/assinatura";
    }
}
