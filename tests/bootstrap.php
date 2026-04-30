<?php declare(strict_types=1);

/**
 * Bootstrap file for SingleSignOn module tests.
 */
require __DIR__ . '/Bootstrap.php';

\SingleSignOnTest\Bootstrap::bootstrap(
    [
        'SingleSignOn',
    ],
    'SingleSignOnTest',
    __DIR__ . '/SingleSignOnTest'
);
