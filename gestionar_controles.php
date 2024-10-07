<?php

session_start();
require_once __DIR__ . '/../vendor/autoload.php';
require 'funciones.php'; // Incluir el archivo de funciones
require 'config.php'; // Incluir el archivo de configuración

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

$client = new Client();

// Verificar si la sesión de Microsoft está activa
if (!isset($_SESSION['accessToken'])) {
    // Redirigir al usuario a la página de inicio de sesión si no hay una sesión activa
    header('Location: login.php');
    exit();
}

// Variable de control para errores
$error_occurred = false;

// Verificar si el token de acceso está presente y es válido
if (!isset($_SESSION['accessToken']) || time() >= $_SESSION['tokenExpires']) {
    // Si el token ha expirado, refrescar el token
    try {
        $accessToken = refreshAccessToken($client, $_SESSION['refreshToken'], $tenant_id, $client_id, $client_secret, $redirect_uri);

        // Guardar el nuevo token de acceso y el token de refresco en la sesión
        $_SESSION['accessToken'] = $accessToken['access_token'];
        $_SESSION['refreshToken'] = $accessToken['refresh_token'];
        $_SESSION['tokenExpires'] = time() + $accessToken['expires_in'];

        // Redirigir a la misma página para usar el nuevo token
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit();
    } catch (RequestException $e) {
        echo 'Error en la solicitud: ' . $e->getMessage();
        exit();
    } catch (Exception $e) {
        echo 'Error: ' . $e->getMessage();
        exit();
    }
}
try {
    // Obtener el token de acceso de la sesión
    $accessToken = $_SESSION['accessToken'];

    // Leer el rango de la hoja de códigos y correos
    $asignaciones = getWorksheetValues($client, $itemId, $worksheetIdCtrlxCorreo, $accessToken, $driveId);

    // Leer un rango específico
    $dataControles = getWorksheetValues($client, $itemId, $worksheetIdControles, $accessToken, $driveId);
    
}catch (Exception $e) {
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
    <title>Asignar Controles</title>
    <!-- Bootstrap CSS -->
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <!-- Incluir DataTables CSS -->
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.11.5/css/jquery.dataTables.css">
    <!-- Incluir Select2 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
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

        .dataTables_wrapper .dataTables_length select{
            color: #ffffff;
        }

        #asignacionesTable_filter{
            text-align: left;
        }
        .table {
            background-color: #1e1e1e;
            color: #ffffff;
            width: 100%;
            flex: 1;
        }

        .table thead th {
            background-color: #333333;
            color: #ffffff;
        }

        .table tbody tr:nth-child(odd) {
            background-color: #2a2a2a;
        }

        .table tbody tr:nth-child(even) {
            background-color: #1e1e1e;
        }

        .table tbody tr:hover {
            background-color: #444444;
        }

        .table a {
            color: #1e90ff;
        }

        .table a:hover {
            color: #ff4500;
        }

        label, .dataTables_wrapper .dataTables_info{
            color: #ffffff !important;
        }
        option{
            background-color: #333;
            color : #ffffff;
        }

        input, #control{
            width: 100%;
            background-color: #333;
            color: #ffffff;
            border: 1px solid #aaa;
            border-radius: 4px;
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
            .select2 .select2-container .select2-container--default{
                width: 100%;
            }
        }

        .selection{
            border-radius: 4px;
        }
        .select2-selection.select2-selection--single{
            background-color: #333; /* Fondo del campo select */
            color: #fff; /* Color del texto del campo select */
        }
        /* Estilo para el contenedor select2 */
        .select2-container--default .select2-selection--single .select2-selection__clear{
            background-color: #333; /* Fondo del campo select */
            color: #fff; /* Color del texto del campo select */
            
        }
        .select2.select2-container.select2-container--default.select2-container--below,.select2.select2-container.select2-container--default{
            width: 100%;
        }

        #correo{
            padding-left: 8px;
            padding-right: 20px;
        }
        #select2-control-container{
            color: #fff; /* Color del texto del campo select */

        }
        /* Estilo para las opciones del desplegable */
        .select2-container--default .select2-results__option {
            background-color: #333; /* Fondo de las opciones */
            color: #fff; /* Color del texto de las opciones */
        }

        /* Estilo para las opciones seleccionadas */
        .select2-container--default .select2-results__option--highlighted[aria-selected] {
            background-color: #555; /* Fondo de la opción seleccionada */
            color: #fff; /* Color del texto de la opción seleccionada */
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
            <h1>Asignar Controles</h1>
            <!-- Formulario para agregar nuevas asignaciones -->
            <form id="addForm" method="post" action="agregar_asignacion.php" style="max-width: 400px;">
                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label for="correo">Correo del Usuario:</label>
                        <input type="email" id="correo" name="correo" required>
                    </div>
                    <div class="form-group col-md-6">
                        <label for="control">Control a asignar:</label>
                        <select id="control" name="control">
                            <option value="">Todos</option>
                            <?php foreach (array_slice($dataControles, 1) as $row): // Omitir la primera fila ?>
                                <option value="<?php echo htmlspecialchars($row[0]); ?>"><?php echo htmlspecialchars($row[0]); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <button type="submit" class="btn btn-primary">Agregar Asignación</button>
            </form>
            <hr>
            <!-- Tabla para mostrar las asignaciones actuales -->
            <div class="table-responsive">
                <table id="asignacionesTable" class="table table-dark table-striped">
                    <thead>
                        <tr>
                            <th>Correo del Usuario</th>
                            <th>Código del Control</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach (array_slice($asignaciones, 1) as $asignacion): ?>
                            <tr>
                                <td><?= htmlspecialchars($asignacion[0]) ?></td>
                                <td><?= htmlspecialchars($asignacion[1]) ?></td>
                                <td>
                                    <form method="post" action="eliminar_asignacion.php" style="display:inline;">
                                        <input type="hidden" name="correo" value="<?= htmlspecialchars($asignacion[0]) ?>">
                                        <input type="hidden" name="control" value="<?= htmlspecialchars($asignacion[1]) ?>">
                                        <button type="submit" class="btn btn-danger">Eliminar</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>    
    <!-- Incluir jQuery y DataTables JS -->
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script type="text/javascript" charset="utf8" src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.js"></script>
    <!-- Incluir Select2 JS -->
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
        $(document).ready(function() {
            $('#asignacionesTable').DataTable({
                "language": {
                    "lengthMenu": "Mostrar _MENU_ registros por página",
                    "zeroRecords": "No se encontraron registros",
                    "info": "Mostrando página _PAGE_ de _PAGES_",
                    "infoEmpty": "No hay registros disponibles",
                    "infoFiltered": "(filtrado de _MAX_ registros totales)",
                    "search": "Buscar:",
                    "paginate": {
                        "first": "Primero",
                        "last": "Último",
                        "next": "Siguiente",
                        "previous": "Anterior"
                    }
                }
            });
            $('#control').select2({
                placeholder: "Seleccione un control",
                allowClear: true
            });
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