<?php

use Symfony\Component\Dotenv\Dotenv;

require dirname(__DIR__).'/vendor/autoload.php';

if (method_exists(Dotenv::class, 'bootEnv')) {
    (new Dotenv())->bootEnv(dirname(__DIR__).'/.env');
}

/**
 * Some CI jobs run phpunit directly (without `make jwt-keys-test`),
 * so we must ensure test JWT keys exist before booting Symfony in APP_ENV=test.
 */
if (($_SERVER['APP_ENV'] ?? $_ENV['APP_ENV'] ?? null) === 'test') {
    $jwtDir = dirname(__DIR__).'/config/jwt/test';
    $privateKeyPath = $jwtDir.'/private.pem';
    $publicKeyPath = $jwtDir.'/public.pem';

    if (!is_dir($jwtDir)) {
        mkdir($jwtDir, 0775, true);
    }

    $privateKey = is_file($privateKeyPath) ? @file_get_contents($privateKeyPath) : false;
    $publicKey = is_file($publicKeyPath) ? @file_get_contents($publicKeyPath) : false;

    $privateKeyValid = is_string($privateKey) && $privateKey !== '' && openssl_pkey_get_private($privateKey) !== false;
    $publicKeyValid = is_string($publicKey) && $publicKey !== '' && openssl_pkey_get_public($publicKey) !== false;

    if (!$privateKeyValid || !$publicKeyValid) {
        $resource = openssl_pkey_new([
            'private_key_bits' => 4096,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);

        if ($resource === false) {
            throw new RuntimeException('Unable to generate JWT key pair for tests.');
        }

        $exportedPrivateKey = '';
        if (!openssl_pkey_export($resource, $exportedPrivateKey)) {
            throw new RuntimeException('Unable to export private JWT key for tests.');
        }

        $details = openssl_pkey_get_details($resource);
        $exportedPublicKey = $details['key'] ?? null;
        if (!is_string($exportedPublicKey) || $exportedPublicKey === '') {
            throw new RuntimeException('Unable to export public JWT key for tests.');
        }

        file_put_contents($privateKeyPath, $exportedPrivateKey);
        @chmod($privateKeyPath, 0600);
        file_put_contents($publicKeyPath, $exportedPublicKey);
        @chmod($publicKeyPath, 0644);
    }
}

if ($_SERVER['APP_DEBUG']) {
    umask(0000);
}
