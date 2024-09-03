<?php
session_start();
require 'vendor/autoload.php';

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Dotenv\Dotenv;

// Load .env file
$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();
$dotenv->required(['DRIVE_ID', 'ITEM_ID','WORKSHEET_ID_CTRL', 'WORKSHEET_ID_CTRLXCORREO']); 

$driveId = $_ENV['DRIVE_ID'];
$itemId = $_ENV['ITEM_ID'];
$worksheetIdRegistroCtrl = $_ENV['WORKSHEET_ID_REGISTROSCTRL'];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $correo = isset($_POST['correo']) ? $_POST['correo'] : null;
    $control = isset($_POST['control']) ? $_POST['control'] : null;

    // Validar entrada
    if (empty($correo) || empty($control)) {
        echo 'Correo y control son requeridos.';
        header ('Location: gestionar_controles.php');
        exit();
    }

    $client = new Client();

    try {

        // Obtener el rango usado de la hoja de cálculo
        $usedRange = getUsedRange($client, $itemId, $worksheetIdRegistroCtrl, $_SESSION['accessToken'], $driveId);
        $lastRow = count($usedRange['values']); // Determinar la última fila con datos

        // Calcular la dirección de la celda en la nueva fila
        $newRow = $lastRow + 1;
        $range = "A$newRow:B$newRow"; // Rango de la nueva fila de la columna A a la B

        $data = [
            'values' => [
                [$correo, $control]
            ]
        ];

        // Agregar la nueva fila
        addRow($client, $itemId, $worksheetIdRegistroCtrl, $range, $data, $_SESSION['accessToken'], $driveId);

        // Redirigir de vuelta a la página de asignaciones
        header('Location: gestionar_controles.php');
        exit();
    } catch (RequestException $e) {
        echo 'Error en la solicitud: ' . $e->getMessage();
    } catch (Exception $e) {
        echo 'Error obteniendo el access token: ' . $e->getMessage();
    }
}
/**
 * Obtiene el rango usado de la hoja de cálculo.
 */
function getUsedRange($client, $itemId, $worksheetId, $accessToken, $driveId) {
    try {
        $select = 'select';
        $response = $client->request('GET', "https://graph.microsoft.com/v1.0/drives/$driveId/items/$itemId/workbook/worksheets/$worksheetId/usedRange?$select=values", [
            'headers' => [
                'Authorization' => 'Bearer ' . $accessToken,
                'Content-Type' => 'application/json',
            ],
        ]);
        return json_decode($response->getBody(), true);
    } catch (RequestException $e) {
        throw new Exception('Error en la solicitud: ' . $e->getMessage());
    }
}

/**
 * Agrega una nueva fila a la hoja de cálculo.
 */
function addRow($client, $fileId, $worksheetId, $range, $data, $accessToken, $driveId) {
    try {
        $response = $client->request('PATCH', "https://graph.microsoft.com/v1.0/drives/$driveId/items/$itemId/workbook/worksheets/$worksheetId/range(address='$range')", [
            'headers' => [
                'Authorization' => 'Bearer ' . $accessToken,
                'Content-Type' => 'application/json',
            ],
            'json' => $data,
        ]);
        return json_decode($response->getBody(), true);
    } catch (RequestException $e) {
        throw new Exception('Error en la solicitud: ' . $e->getMessage());
    }
}
?>