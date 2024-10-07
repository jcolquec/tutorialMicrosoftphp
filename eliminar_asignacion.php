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

    $client = new Client();

    try {
        // Leer el rango de la hoja de códigos y correos
        $values = getWorksheetValues($client, $itemId, $worksheetIdCtrlxCorreo, $_SESSION['accessToken'], $driveId);

        // Encontrar la fila que contiene el registro a eliminar
        $rowIndex = findRowIndex($values, $correo, $control);

        if ($rowIndex != -1) {
            // Eliminar la fila
            deleteRow($client, $itemId, $worksheetIdCtrlxCorreo, $rowIndex, $_SESSION['accessToken'], $driveId);

            // Redirigir de vuelta a la página de asignaciones
            header('Location: gestionar_controles.php');
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

?>