<?php

declare(strict_types=1);

return [
    'frontend' => [
        'consenti/external-script-blocker' => [
            'target' => \Consenti\Consenti\Middleware\ExternalScriptBlockerMiddleware::class,
            'after' => [
                'typo3/cms-frontend/prepare-tsfe-rendering',
            ],
            'before' => [
                'typo3/cms-frontend/output-compression',
            ],
        ],
    ],
];
