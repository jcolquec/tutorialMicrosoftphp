<?php
session_start();
require 'vendor/autoload.php';
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Dotenv\Dotenv;

// Load .env file
$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();
$dotenv->required(['DRIVE_ID', 'ITEM_ID','WORKSHEET_ID_CTRL']); 

$client = new Client();
$driveId = $_ENV['DRIVE_ID'];
$itemId = $_ENV['ITEM_ID'];
$worksheetIdControles = $_ENV['WORKSHEET_ID_CTRL'];

// Verificar si la sesión de Microsoft está activa
if (!isset($_SESSION['accessToken'])) {
    // Redirigir al usuario a la página de inicio de sesión si no hay una sesión activa
    header('Location: login.php');
    exit();
}
try{

    // Solicita el access token usando el authorization code
    $client = new Client();
    // Obtener el valor del parámetro 'data' de la URL
    $data = isset($_GET['data']) ? $_GET['data'] : '';

    $dataControles = getWorksheetValues($client, $itemId, $worksheetIdControles, $_SESSION['accessToken'], $driveId);

    // Buscar el dato en el array de datos de la columna y guardarlo en una variable
    $foundData = null;
    foreach ($dataControles['values'] as $item) {
        
        if ($item[0] === $data) {
            $foundData = $item;
            break;
        }
    }
    
}catch (RequestException $e) {
    echo 'Error en la solicitud: ' . $e->getMessage();
}catch(Exception $e){
    echo $e->getMessage();
}

/**
 * Obtiene los valores de la hoja de cálculo.
 */
function getWorksheetValues(Client $client, $itemId, $worksheetId, $accessToken, $driveId) {
    $select = 'select';
    $response = $client->request('GET', "https://graph.microsoft.com/v1.0/drives/$driveId/items/$itemId/workbook/worksheets/$worksheetId/usedRange?$select=values", [
        'headers' => [
            'Authorization' => 'Bearer ' . $accessToken,
        ],
    ]);

    return json_decode($response->getBody(), true);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
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
</head>
    <body>
        <div class="container mt-5">
            
            <!-- Contenedor para el título y el botón de cerrar sesión -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1>Detalle del Control: <?php echo $foundData[0]; ?></h1>
                <div>
                    <form action="callback.php" method="post" class="d-inline">
                        <button type="submit" class="btn btn-secondary">Lista de Controles</button>
                    </form>
                    <form action="gestionar_controles.php" method="post" class="d-inline">
                        <button type="submit" class="btn btn-secondary">Gestionar controles</button>
                    </form>
                    <form action="bitacoraControles.php" method="post" class="d-inline">
                        <button type="submit" class="btn btn-info">Ver bitácora</button>
                    </form>
                    <form action="logout.php" method="post" class="d-inline">
                        <button type="submit" class="btn btn-primary">Cerrar Sesión</button>
                    </form>
                </div>
            </div>
            <p class="mb-4">Descripción de Control: <?php echo substr($foundData[2], 8); ?></p>
            <h2 class="mb-4">Formulario de Revisión de Control</h2>

            <form action="agregar_archivo.php" method="post" id="controlForm" onsubmit="return validateForm()">
                <div class="form-group">
                    <label for="dato">Dato:</label>
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
                    <label for="respuesta">Respuesta (Sí o No):</label>
                    <input type="text" class="form-control" id="respuesta" name="respuesta" required>
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
        <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.4/dist/umd/popper.min.js"></script>
        <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
        <script>
        function validateForm() {
            var objeto = document.getElementById('objeto').value;
            var objetoConfirm = document.getElementById('objetoConfirm').value;
            var errorMessage = document.getElementById('error-message');

            if (objeto !== objetoConfirm) {
                errorMessage.classList.remove('d-none');
                return false;
            }
            errorMessage.classList.add('d-none');
            return true;
        }

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
        </script>
    </body>
    
</html>