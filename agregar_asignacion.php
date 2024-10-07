<?php

session_start();
require_once __DIR__ . '/../vendor/autoload.php';
require 'funciones.php'; // Incluir el archivo de funciones
require 'config.php'; // Incluir el archivo de configuración

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $correo = isset($_POST['correo']) ? $_POST['correo'] : null;
    $control = isset($_POST['control']) ? $_POST['control'] : null;

    // Validar entrada
    if (empty($correo) || empty($control)) {
        echo 'Correo y control son requeridos.';
        header ('Location: gestionar_controles.php');
        exit();
    }

    try {
        $client = new Client();
        // Obtener el rango usado de la hoja de cálculo
        $usedRange = getWorksheetValues($client, $itemId, $worksheetIdCtrlxCorreo, $_SESSION['accessToken'], $driveId);
        $lastRow = count($usedRange); // Determinar la última fila con datos

        // Calcular la dirección de la celda en la nueva fila
        $newRow = $lastRow + 1;
        $range = "A$newRow:B$newRow"; // Rango de la nueva fila de la columna A a la B

        $data = [
            'values' => [
                [$correo, $control]
            ]
        ];

        // Agregar la nueva fila
        addRow($client, $itemId, $worksheetIdCtrlxCorreo, $range, $data, $_SESSION['accessToken'], $driveId);

        // Redirigir de vuelta a la página de asignaciones
        header('Location: gestionar_controles.php');
        exit();
    } catch (RequestException $e) {
        echo 'Error en la solicitud: ' . $e->getMessage();
    } catch (Exception $e) {
        echo 'Error obteniendo el access token: ' . $e->getMessage();
    }
}

?>