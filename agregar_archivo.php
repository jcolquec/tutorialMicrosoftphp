<?php

session_start();
require_once __DIR__ . '/../vendor/autoload.php';
require 'funciones.php'; // Incluir el archivo de funciones
require 'config.php'; // Incluir el archivo de configuración

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException; 

try{
    // Verificar si la sesión de Microsoft está activa
    if (!isset($_SESSION['accessToken'])) {
        // Redirigir al usuario a la página de inicio de sesión si no hay una sesión activa
        header('Location: login.php');
        exit();
    }

    $client = new Client();

    // Establecer la zona horaria a "America/Santiago"
    date_default_timezone_set('America/Santiago');

    // Obtener el correo del usuario
    $correo = getUserEmail($client, $_SESSION['accessToken']);

    // Obtener el rango usado de la hoja de cálculo
    $usedRange = getWorksheetValues($client, $itemId, $worksheetIdRegistros, $_SESSION['accessToken'], $driveId);

    $lastRow = count($usedRange); // Determinar la última fila con datos

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

        // Agregar la nueva fila
        addRow($client, $itemId, $worksheetIdRegistros, $range, $data, $_SESSION['accessToken'], $driveId);

        header('Location: form_control.php?data=' . $idControl.'&success=1');
        exit();
    }
}catch(Exception $e){
    echo 'Error: ' . $e->getMessage();
}
?>