<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Ambiente SEFAZ
    |--------------------------------------------------------------------------
    | 1 = Produção | 2 = Homologação
    */
    'sefaz' => [
        'ambiente'  => (int) env('SEFAZ_AMBIENTE', 2),
        'timeout'   => (int) env('SEFAZ_TIMEOUT', 60),
        'retry'     => (int) env('SEFAZ_RETRY', 3),
    ],

    /*
    |--------------------------------------------------------------------------
    | CT-e / CIOT
    |--------------------------------------------------------------------------
    */
    'cte' => [
        'ambiente' => (int) env('CTE_SEFAZ_AMBIENTE', 2),
    ],

    'ciot' => [
        'endpoint_prod' => env('CTE_ANTT_ENDPOINT', 'https://ws.antt.gov.br/CIOT/ServicoCIOT.svc'),
        'endpoint_hom'  => 'https://homologacao.antt.gov.br/CIOT/ServicoCIOT.svc',
    ],

    /*
    |--------------------------------------------------------------------------
    | Paths S3 para XMLs e PDFs fiscais
    |--------------------------------------------------------------------------
    */
    'storage' => [
        'disk'       => env('FISCAL_DISK', 's3'),
        'path_xml'   => 'xmls',
        'path_pdf'   => 'pdfs',
        'path_certs' => 'certs',
    ],

    /*
    |--------------------------------------------------------------------------
    | NFS-e — padrões municipais suportados
    |--------------------------------------------------------------------------
    */
    'nfse' => [
        'padroes_suportados' => [
            'abrasf',       // ABRASF 2.04 — padrão nacional
            'paulistana',   // São Paulo (SP)
            'ginfes',       // vários municípios
            'betha',        // vários municípios
            'elotech',      // vários municípios
        ],
    ],
];
