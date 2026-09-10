<?php

use Illuminate\Support\Facades\Schedule;

/*
 * Agendamento de tarefas (Laravel 11).
 * Requer o scheduler rodando (Supervisor erp-scheduler ou cron).
 */

// Avisos de trial expirando — todos os dias às 9h
Schedule::command('saas:avisar-trials')->dailyAt('09:00');
