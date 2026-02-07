<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Essential Ports
    |--------------------------------------------------------------------------
    |
    | Ports that should always be allowed in the firewall.
    | These rules cannot be deleted through the panel.
    |
    */
    'essential_ports' => [
        ['port' => '22', 'protocol' => 'tcp', 'comment' => 'SSH'],
        ['port' => '80', 'protocol' => 'tcp', 'comment' => 'HTTP'],
        ['port' => '443', 'protocol' => 'tcp', 'comment' => 'HTTPS'],
        ['port' => '8000', 'protocol' => 'tcp', 'comment' => 'VSISPanel'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Policies
    |--------------------------------------------------------------------------
    */
    'default_incoming' => 'deny',
    'default_outgoing' => 'allow',

    /*
    |--------------------------------------------------------------------------
    | Panel Port
    |--------------------------------------------------------------------------
    |
    | The port that the VSISPanel runs on.
    |
    */
    'panel_port' => env('PANEL_PORT', 8000),
];
