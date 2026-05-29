<?php

uses(
    App\Providers\TestServiceProvider::class,
)->in('tests');

uses()
    ->beforeEach(function () {
        // Garante que cada teste roda no banco de teste correto
        config(['database.default' => 'mysql']);
    })
    ->in('tests/Feature', 'tests/Integration');
