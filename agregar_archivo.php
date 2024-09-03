<?php
require 'vendor/autoload.php'; // Cargar Guzzle HTTP client
session_start();
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Dotenv\Dotenv;

// Load .env file
$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();
$dotenv->required(['DRIVE_ID', 'ITEM_ID','WORKSHEET_ID_CTRL', 'WORKSHEET_ID_CTRLXCORREO']); 

$driveId = $_ENV['DRIVE_ID'];
$itemId = $_ENV['ITEM_ID'];
$worksheetIdControles = $_ENV['WORKSHEET_ID_CTRL'];
$worksheetIdRegistroCtrl = $_ENV['WORKSHEET_ID_REGISTROSCTRL'];

try{
    // Verificar si la sesión de Microsoft está activa
    if (!isset($_SESSION['accessToken'])) {
        // Redirigir al usuario a la página de inicio de sesión si no hay una sesión activa
        header('Location: login.php');
        exit();
    }
    // Solicita el access token usando el authorization code
    $client = new Client();
    // Establecer la zona horaria a "America/Santiago"
    date_default_timezone_set('America/Santiago');

    // Obtener el correo del usuario
    $correo = getUserEmail($client, $_SESSION['accessToken']);

    // Obtener el rango usado de la hoja de cálculo
    $usedRange = getUsedRange($client, $itemId, $worksheetIdRegistroCtrl, $_SESSION['accessToken']);

    $lastRow = count($usedRange['values']); // Determinar la última fila con datos

    // Calcular la dirección de la celda en la nueva fila
    $newRow = $lastRow + 1;
    $range = "A$newRow:G$newRow"; // Rango de la nueva fila de la columna A a la G


    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        
        $idControl = $_POST['dato'];
        $objeto = $_POST['objeto'];
        $respuesta = $_POST['respuesta'];
        $observaciones = $_POST['observaciones'];
        $nombres = $_POST['nombres'];
        $uris = $_POST['uris'];
        $fechaHora = date('Y-m-d H:i:s');

        // Concatenar todos los nombres y URIs en una sola cadena
        $nombresUrisConcatenados = '';
        for ($i = 0; $i < count($nombres); $i++) {
            $nombresUrisConcatenados .= htmlspecialchars($nombres[$i]) . ";" . htmlspecialchars($uris[$i]) . "\n";
        }
        
        $data = [
            'values' => [
                [$correo['mail'], $idControl, $objeto, $respuesta, $observaciones, $nombresUrisConcatenados, $fechaHora]
            ]
        ];
    
        $response = $client->request('PATCH', "https://graph.microsoft.com/v1.0/me/drive/items/$fileId/workbook/worksheets/$worksheetId/range(address='$range')", [
            'headers' => [
                'Authorization' => 'Bearer ' . $_SESSION['accessToken'],
                'Content-Type' => 'application/json',
            ],
            'json' => $data,
        ]);
        header('Location: form_control.php?data=' . $idControl.'&success=1');
    exit();
    }
}catch(Exception $e){
    echo 'Error: ' . $e->getMessage();
}

/**
 * Obtiene el correo del usuario.
 */
function getUserEmail(Client $client, $accessToken) {
    try {
        $response = $client->request('GET', 'https://graph.microsoft.com/v1.0/me?$select=mail', [
            'headers' => [
                'Authorization' => 'Bearer ' . $accessToken,
                'Accept' => 'application/json',
            ],
        ]);
        return json_decode($response->getBody(), true);
    } catch (RequestException $e) {
        throw new Exception('Error en la solicitud: ' . $e->getMessage());
    }
}
/**
 * Obtiene el rango usado de la hoja de cálculo.
 */
function getUsedRange(Client $client, $fileId, $worksheetId, $accessToken) {
    try {
        $response = $client->request('GET', "https://graph.microsoft.com/v1.0/me/drive/items/$fileId/workbook/worksheets/$worksheetId/usedRange", [
            'headers' => [
                'Authorization' => 'Bearer ' . $accessToken,
            ],
        ]);
        return json_decode($response->getBody(), true);
    } catch (RequestException $e) {
        throw new Exception('Error en la solicitud: ' . $e->getMessage());
    }
}
?>