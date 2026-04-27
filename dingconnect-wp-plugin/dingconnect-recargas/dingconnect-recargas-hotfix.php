<?php

if (!defined('ABSPATH')) {
    exit;
}

// Shim de compatibilidad: mantiene operativas instalaciones antiguas que apuntan al archivo hotfix.
$canonical_entrypoint = __DIR__ . '/dingconnect-recargas.php';
if (file_exists($canonical_entrypoint)) {
    require_once $canonical_entrypoint;
}
