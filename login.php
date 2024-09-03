<?php
use Dotenv\Dotenv;
require 'vendor/autoload.php';
// Load .env file
$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();
$dotenv->required(['CLIENT_ID', 'TENANT_ID', 'REDIRECT_URI']); 
// Configuración básica
$client_id = $_ENV['CLIENT_ID'];
$tenant_id = $_ENV['TENANT_ID'];
$redirect_uri = $_ENV['REDIRECT_URI'];  // Debe coincidir con la URI registrada

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
