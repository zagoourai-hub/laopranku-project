<?php

return [
    'gotenberg_url' => env('GOTENBERG_URL', 'http://gotenberg:3000'),

    /*
    | Format presets. A preset is data, never a class per campus (PRD section 4).
    | Lengths are centimetres, line spacing is a multiplier.
    */
    'templates' => [
        'default' => [
            'font_name' => 'Times New Roman',
            'font_size' => 12,
            'line_spacing' => 1.5,
            'margin_left_cm' => 4.0,
            'margin_top_cm' => 3.0,
            'margin_right_cm' => 3.0,
            'margin_bottom_cm' => 3.0,
            'first_line_indent_cm' => 1.25,
            'hanging_indent_cm' => 1.25,
        ],
    ],
];
