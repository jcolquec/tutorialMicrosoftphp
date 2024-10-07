<?php

session_start();
require_once __DIR__ . '/../vendor/autoload.php';
require 'funciones.php'; // Incluir el archivo de funciones
require 'config.php'; // Incluir el archivo de configuración

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

// Verificar si la sesión de Microsoft está activa
if (!isset($_SESSION['accessToken'])) {
    // Redirigir al usuario a la página de inicio de sesión si no hay una sesión activa
    header('Location: login.php');
    exit();
}

// Variable de control para errores
$error_occurred = false;

try{

    // Solicita el access token usando el authorization code
    $client = new Client();
    // Obtener el valor del parámetro 'data' de la URL
    $data = isset($_GET['data']) ? $_GET['data'] : '';

    $dataControles = getWorksheetValues($client, $itemId, $worksheetIdControles, $_SESSION['accessToken'], $driveId);

    // Buscar el dato en el array de datos de la columna y guardarlo en una variable
    $foundData = null;
    foreach ($dataControles as $item) {
        
        if ($item[0] === $data) {
            $foundData = $item;
            break;
        }
    }
    
}catch (RequestException $e) {
    echo 'Error en la solicitud: ' . $e->getMessage();
}catch(Exception $e){
    echo 'Error: ' . $e->getMessage();
}

// Solo cargar el HTML si no ocurrió un error
if (!$error_occurred):
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detalle del Control</title>
    <!-- Incluir Bootstrap CSS -->
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <script>
        function agregarCampo() {
            const contenedor = document.getElementById('contenedor-enlaces');
            const nuevoCampo = document.createElement('div');
            nuevoCampo.classList.add('form-group');
            nuevoCampo.innerHTML = `
                <div class="input-group mb-3">
                    <input type="text" class="form-control" name="nombres[]" placeholder="Nombre del Archivo" required>
                    <input type="url" class="form-control" name="uris[]" placeholder="Enlace URI" required>
                    <div class="input-group-append">
                        <button type="button" class="btn btn-danger" onclick="eliminarCampo(this)">Eliminar</button>
                    </div>
                </div>
            `;
            contenedor.appendChild(nuevoCampo);
        }

        function eliminarCampo(boton) {
            boton.closest('.form-group').remove();
        }
    </script>
    <style>
        html, body {
            height: 100%;
            margin: 0;
            padding: 0;
            overflow-x: hidden;
        }

        body {
            display: flex;
            flex-direction: column;
            background-color: #1e1e1e;
            color: #ffffff;
        }

        .wrapper {
            display: flex;
            flex: 1;
        }

        .sidebar {
            height: 100vh;
            width: 250px;
            background-color: #343a40;
            padding-top: 20px;
            position: fixed;
            top: 0;
            left: -250px;
            transition: left 0.3s ease;
        }

        .menu-toggle {
            background-color: #343a40;
            color: white;
            border: none;
            padding: 10px;
            cursor: pointer;
            z-index: 1100;
            position: fixed;
            top: 10px;
            left: 10px;
        }

        .sidebar.open {
            left: 0; /* Mostrar el menú cuando está abierto */
        }

        .sidebar form {
            width: 100%;
            padding: 10px;
        }

        .sidebar button {
            width: 100%;
            margin-bottom: 10px;
        }

        .content {
            background-color: #1e1e1e;
            margin-left: 0px;
            padding: 20px;
            transition: margin-left 0.3s ease;
            overflow-x: auto;
            flex: 1;
            display: flex;
            flex-direction: column;
        }

        .content.shifted {
            margin-left: 250px; /* Ajustar el contenido cuando el menú está abierto */
        }

        .form-control, .form-control:disabled, .form-control[readonly], .form-control:focus, .modal-content{
            background-color: #333;
            color: #fff;
        }
        .dataTables_wrapper .dataTables_info{
            color: #ffffff !important;
        }

        @media (max-width: 768px) {
            .sidebar {
                width: 100%;
                left: -100%;
            }

            .content {
                margin-left: 0;
                width: 100%;
            }

            .content.shifted {
                margin-left: 100%;
            }

            .menu-toggle {
                display: block;
                position: fixed;
                top: 10px;
                left: 10px;
                z-index: 1000;
            }
        }

    </style>
</head>
<body>
    <button class="btn btn-secondary menu-toggle" onclick="toggleMenu()">☰</button>
    <div class="sidebar" id="sidebar">
        <div class="menu-title">
            <h2 class="text-center">Menú</h2>
        </div>
        <form action="callback.php" method="post">
            <button type="submit" class="btn btn-secondary">Lista de Controles</button>
        </form>
        <form action="gestionar_controles.php" method="post">
            <button type="submit" class="btn btn-secondary">Gestionar controles</button>
        </form>
        <form action="bitacoraControles.php" method="post">
            <button type="submit" class="btn btn-info">Ver bitácora</button>
        </form>
        <form action="logout.php" method="post">
            <button type="submit" class="btn btn-primary">Cerrar Sesión</button>
        </form>
    </div>
    <div class="content" id="content">
        <div class="container mt-5">
            <!-- Contenedor para el título y el botón de cerrar sesión -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1>Detalle del Control: <?php echo $foundData[0]; ?></h1>
            </div>
            <p class="mb-4">Descripción de Control: <?php echo substr($foundData[2], 8); ?></p>
            <h2 class="mb-4">Formulario de Revisión de Control</h2>

            <form action="agregar_archivo.php" method="post" id="controlForm" onsubmit="return validateForm()">
                <div class="form-group">
                    <label for="dato">Control:</label>
                    <input type="text" class="form-control" id="dato" name="dato" value="<?php echo $foundData[0]; ?>" readonly>
                </div>
                
                <div class="form-group">
                    <label for="objeto">Objeto a controlar:</label>
                    <div id="error-message" class="alert alert-danger d-none" role="alert">
                        Los valores deben ser iguales.
                    </div>
                    <input type="text" class="form-control" id="objeto" name="objeto" required>
                </div>
                <div class="form-group">
                    <label for="objetoConfirm">Repita el Objeto a controlar:</label>
                    <input type="text" class="form-control" id="objetoConfirm" name="objetoConfirm" required>
                </div>
                <div class="form-group">
                    <p>Pregunta: ¿Se encuentran completadas correctamente TODAS las comprobaciones/revisiones de este control?</p>
                    <select class="form-control" id="respuesta" name="respuesta" required>
                        <option value="">Seleccione una opción</option>
                        <option value="Sí">Sí</option>
                        <option value="No">No</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="observaciones">Observaciones:</label>
                    <textarea class="form-control" id="observaciones" name="observaciones" required></textarea>
                </div>
                <h2 class="mb-4">Agregar Nuevo Enlace</h2>
                <p>Por favor, ingrese el nombre del archivo y su enlace correspondiente para respaldar sus respuestas:</p>
                <div id="contenedor-enlaces" class="mb-3">
                    <div class="form-group">
                        <div class="input-group mb-3">
                            <input type="text" class="form-control" name="nombres[]" placeholder="Nombre del Archivo" required>
                            <input type="url" class="form-control" name="uris[]" placeholder="Enlace URI" required>
                            <div class="input-group-append">
                                <button type="button" class="btn btn-danger" onclick="eliminarCampo(this)">Eliminar</button>
                            </div>
                        </div>
                    </div>
                </div>
                <button type="button" class="btn btn-primary mb-3" onclick="agregarCampo()">Agregar Enlace</button>
                <div class="form-group">
                    <button type="submit" class="btn btn-success">Enviar respuestas</button>
                </div>
            </form>
            
        </div>
        </div>
        <!-- Modal de éxito -->
        <div class="modal fade" id="successModal" tabindex="-1" aria-labelledby="successModalLabel" aria-hidden="true" data-backdrop="static" data-keyboard="false">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="successModalLabel">Éxito</h5>
                    </div>
                    <div class="modal-body">
                        Formulario registrado con éxito.
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-success" id="acceptButton">Aceptar</button>
                    </div>
                </div>
            </div>
        </div>
    
    <!-- Incluir Bootstrap JS y dependencias -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.4/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const objetoInput = document.getElementById('objeto');
        const objetoConfirmInput = document.getElementById('objetoConfirm');
        const errorMessage = document.getElementById('error-message');

        function validateInputs() {
            if (objetoInput.value !== objetoConfirmInput.value) {
                errorMessage.classList.remove('d-none');
            } else {
                errorMessage.classList.add('d-none');
            }
        }

        objetoInput.addEventListener('input', validateInputs);
        objetoConfirmInput.addEventListener('input', validateInputs);
    });

    document.getElementById('controlForm').onsubmit = validateForm;

    $(document).ready(function() {
        // Mostrar ventana emergente si el formulario se ha registrado con éxito
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.has('success')) {
            $('#successModal').modal('show');
        }
    });

    document.getElementById('acceptButton').addEventListener('click', function() {
        window.location.href = 'callback.php';
    });

    function toggleMenu() {
        var sidebar = document.getElementById('sidebar');
        var content = document.getElementById('content');
        sidebar.classList.toggle('open');
        content.classList.toggle('shifted');
    }
    </script>

</body>
</html>
<?php
endif;
?>