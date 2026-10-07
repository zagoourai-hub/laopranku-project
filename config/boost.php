<?php

return [
    'agents' => [
        'claude_code' => [
            // Keep boost:update from appending its guidelines to CLAUDE.md; they live in AGENTS.md.
            'guidelines_path' => 'AGENTS.md',
        ],
    ],
];
