<?php

return [
    /**
     * Taxa cobrada pelo sistema sobre cada transaction
     */
    'systemTax' => 0.05,

    /**
     * Token (JWT) de autenticação
     */
    'jwtKey' => env('JWT_KEY'),

    /**
     * Tempo que uma transaction não confirmada ainda é considerada válida
     */
    'notConfirmedTTL' => '1 minute'
];