<?php
session_start();
require 'vendor/autoload.php';

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $correo = isset($_POST['correo']) ? $_POST['correo'] : null;
    $control = isset($_POST['control']) ? $_POST['control'] : null;

    // Validar entrada
    if (empty($correo) || empty($control)) {
        echo 'Correo y control son requeridos.';
        header ('Location: asignar_controles.php');
        exit();
    }

    $client = new Client();

    try {
        // URL del archivo en OneDrive (obtén esta URL desde la API de Graph o de OneDrive)
        $fileId = '01PO6ARCWT75GMVQXSLFE3QUOCC6LAFYAG';
        $worksheetId = 'controlesAsignadosxRUT'; // El ID de la hoja o el nombre

        // Leer el rango de la hoja de códigos y correos
        $values = getWorksheetValues($client, $fileId, $worksheetId, $_SESSION['accessToken']);

        // Encontrar la fila que contiene el registro a eliminar
        $rowIndex = findRowIndex($values, $correo, $control);

        if ($rowIndex != -1) {
            // Eliminar la fila
            deleteRow($client, $fileId, $worksheetId, $rowIndex, $_SESSION['accessToken']);

            // Redirigir de vuelta a la página de asignaciones
            header('Location: asignar_controles.php');
            exit();
        } else {
            echo 'Registro no encontrado.';
        }

    } catch (RequestException $e) {
        echo 'Error en la solicitud: ' . $e->getMessage();
    } catch (Exception $e) {
        echo 'Error obteniendo el access token: ' . $e->getMessage();
    }
}
/**
 * Obtiene los valores de la hoja de cálculo.
 */
function getWorksheetValues($client, $fileId, $worksheetId, $accessToken) {
    $response = $client->request('GET', "https://graph.microsoft.com/v1.0/me/drive/items/$fileId/workbook/worksheets/$worksheetId/usedRange", [
        'headers' => [
            'Authorization' => 'Bearer ' . $accessToken,
        ],
    ]);

    $data = json_decode($response->getBody(), true);
    return $data['values'];
}

/**
 * Encuentra el índice de la fila que contiene el registro a eliminar.
 */
function findRowIndex($values, $correo, $control) {
    foreach ($values as $index => $row) {
        if ($row[0] == $correo && $row[1] == $control) {
            return $index;
        }
    }
    return -1;
}

/**
 * Elimina la fila especificada.
 */
function deleteRow($client, $fileId, $worksheetId, $rowIndex, $accessToken) {
    // Eliminar la fila
    $response = $client->request('POST', "https://graph.microsoft.com/v1.0/me/drive/items/$fileId/workbook/worksheets/$worksheetId/range(address='A" . ($rowIndex + 1) . ":B" . ($rowIndex + 1) . "')/delete", [
        'headers' => [
            'Authorization' => 'Bearer ' . $accessToken,
        ],
    ]);
}

?>