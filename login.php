<?php
require_once __DIR__ . '/../vendor/autoload.php';
require 'config.php'; // Incluir el archivo de configuración

// URL de autorización
$auth_url = "https://login.microsoftonline.com/$tenant_id/oauth2/v2.0/authorize?" . http_build_query([
    'client_id' => $client_id,
    'response_type' => 'code',
    'redirect_uri' => $redirect_uri,
    'response_mode' => 'query',
    'scope' => 'https://graph.microsoft.com/.default',
    'state' => '12345' // Puedes cambiar esto por un valor generado dinámicamente
]);

// Redirige al usuario a la URL de autorización
header('Location: ' . $auth_url);
exit;

?>