<?php

session_start();
require_once __DIR__ . '/../vendor/autoload.php';
require 'funciones.php'; // Incluir el archivo de funciones
require 'config.php'; // Incluir el archivo de configuración

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

// Variable de control para errores
$error_occurred = false;

// Verifica que el código de autorización esté presente
if (isset($_GET['code'])) {
    $authorization_code = $_GET['code'];

    // Solicita el access token usando el authorization code
    $client = new Client();

    try {
        $accessToken = getAccessToken($client, $authorization_code, $tenant_id, $client_id, $client_secret, $redirect_uri);

        // Guardar el token de acceso y el token de refresco en la sesión
        $_SESSION['accessToken'] = $accessToken['access_token'];
        $_SESSION['refreshToken'] = $accessToken['refresh_token'];
        $_SESSION['tokenExpires'] = time() + $accessToken['expires_in'];

        // Redirigir a la misma página para usar el nuevo token
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit();
    } catch (RequestException $e) {
        echo 'Error en la solicitud: ' . $e->getMessage();
    } catch (Exception $e) {
        echo 'Error obteniendo el access token: ' . $e->getMessage();
    }
} else {
    // Verificar si el token ha expirado
    if (isset($_SESSION['accessToken']) && time() < $_SESSION['tokenExpires']) {
        try {
            $client = new Client();

            // Obtener el correo del usuario
            $correo = getUserEmail($client, $_SESSION['accessToken']);
            
            $correoValido = validarUsuario($client, $itemId, $worksheetUsuarios, $_SESSION['accessToken'], $correo['mail'], $driveId);
            if (!$correoValido) {
                echo 'No tienes permisos para acceder a esta página.';
                exit();
            }
            
            // Obtener el datos del archivo de Excel
            $dataControles = getWorksheetValues($client, $itemId, $worksheetIdControles, $_SESSION['accessToken'], $driveId);
            
            // Leer el rango de la hoja de códigos y correos
            $dataCodigos = getWorksheetValues($client, $itemId, $worksheetIdCtrlxCorreo, $_SESSION['accessToken'], $driveId);
            
            // Filtrar los datos de la hoja de controles en base a los códigos
            $filteredData = [];
            $filteredFormatData = ['format' => ['font' => []]];

            foreach ($dataControles as $rowIndex => $row) {
                if ($rowIndex == 0) {
                    // Agregar encabezados
                    $filteredData[] = [$row[0], $row[2], $row[4]];
                } else {
                    $codigo = $row[0]; // Suponiendo que el código está en la primera columna
                    foreach ($dataCodigos as $codigoRow) {
                        if ($codigo == $codigoRow[1] && $correo['mail'] == $codigoRow[0]) {
                            $filteredData[] = [$row[0], substr($row[2], 8), $row[4]];
                            $filteredFormatData['format']['font'][] = isset($formatData['format']['font'][$rowIndex]) ? $formatData['format']['font'][$rowIndex] : [];
                            break;
                        }
                    }
                }
            }
        } catch (RequestException $e) {
            echo 'Error en la solicitud: ' . $e->getMessage();
        }
    } else {
        // Si el token ha expirado, refrescar el token
        try {
            $client = new Client();
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
        } catch (Exception $e) {
            echo 'Error: ' . $e->getMessage();
        }
    }
}

// Solo cargar el HTML si no ocurrió un error
if (!$error_occurred):
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lista de Controles</title>
    <!-- Bootstrap CSS -->
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <!-- Incluir DataTables CSS -->
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.11.5/css/jquery.dataTables.css">
    <style>
        html, body {
            height: 100%;
            margin: 0;
            padding: 0;
            overflow-x: hidden;
        }

        body {
            background-color: #1e1e1e;
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

        label, select, input {
            color: #ffffff;
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

        /* Estilos personalizados para los botones de DataTables */
        .dt-button {
            background-color: #ffffff !important;
            color: #000000 !important;
            border: 1px solid #000000 !important;
        }

        .dt-button:hover {
            background-color: #e0e0e0 !important;
            color: #000000 !important;
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
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1>Lista de Controles</h1>
            </div>
            <div class="table-responsive">
                <table id="excelDataTable" class="table table-dark table-striped">
                    <thead>
                        <tr>
                            <?php if (!empty($filteredData[0])): ?>
                                <?php foreach ($filteredData[0] as $header): ?>
                                    <th><?= htmlspecialchars($header) ?></th>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php for ($i = 1; $i < count($filteredData); $i++): ?>
                            <tr>
                                <?php foreach ($filteredData[$i] as $j => $cell): ?>
                                    <?php if ($j == 0): ?>
                                        <td><a href="form_control.php?data=<?=$filteredData[$i][0]?>"><?= nl2br(htmlspecialchars($cell)) ?></a></td>
                                    <?php else: ?>
                                        <td><?= nl2br(str_replace('**', '<b>', str_replace('**', '</b>', htmlspecialchars($cell)))) ?></td>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </tr>
                        <?php endfor; ?>
                    </tbody>
                </table>
            </div>
        </div>    
    </div>

    <!-- Incluir jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- Incluir DataTables JS -->
    <script type="text/javascript" charset="utf8" src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.js"></script>
    <!-- Incluir Bootstrap JS y dependencias -->
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.4/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <!-- Inicializar DataTables -->
    <script>
        $(document).ready(function() {
            $('#excelDataTable').DataTable({
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
                    },
                    "aria": {
                        "sortAscending": ": activar para ordenar la columna en orden ascendente",
                        "sortDescending": ": activar para ordenar la columna en orden descendente"
                    },
                    "buttons": {
                        "copy": "Copiar",
                        "excel": "Exportar a Excel",
                        "pdf": "Exportar a PDF",
                        "print": "Imprimir"
                    },
                    "decimal": ",",
                    "thousands": ".",
                    "loadingRecords": "Cargando...",
                    "processing": "Procesando...",
                    "emptyTable": "No hay datos disponibles en la tabla",
                    "infoPostFix": "",
                    "infoThousands": ".",
                },
                
                dom: 'Bfrtip',
                buttons: [
                    'copy', 'csv', 'excel', 'pdf', 'print'
                ]
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