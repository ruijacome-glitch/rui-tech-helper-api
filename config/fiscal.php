<?php

return [
    'isento_iva' => (bool) env('FISCAL_ISENTO_IVA', true),
    'iva_taxa' => (int) env('FISCAL_IVA_TAXA', 23),
    'motivo_isencao' => env('FISCAL_MOTIVO_ISENCAO', 'Isento de IVA'),
];
