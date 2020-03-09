<?php
    return [
        'OAAPIURL'=>env('OAAPIURL','http://localhost:7098'),//curl模式的Api
        'OAAPIROUTE'=>env('OAAPIROUTE','/api/flow/apiAuditing'),//curl模式的Api
        'OAAPITIMEOUT'=>env('OAAPITIMEOUT',10),
    ];