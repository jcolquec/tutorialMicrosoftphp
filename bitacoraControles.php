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

    // Obtener los datos de la hoja de registros
    $dataRegistros = getWorksheetValues($client, $itemId, $worksheetIdRegistros, $_SESSION['accessToken'], $driveId);

    // Filtrar los datos de la hoja de registros en base al correo
    $filteredData = [];
    $filteredFormatData = ['format' => ['font' => []]];

    foreach ($dataRegistros as $rowIndex => $row) {
        
        if ($rowIndex == 0) {
            continue;
        } else {
            
            $filteredData[] = $row;
            $filteredFormatData['format']['font'][] = isset($formatData['format']['font'][$rowIndex]) ? $formatData['format']['font'][$rowIndex] : [];
        }
    }
    // Función de comparación para ordenar por fecha (elemento 6)
    usort($filteredData, function($a, $b) {
        $dateA = DateTime::createFromFormat('d/m/Y', $a[6]);
        $dateB = DateTime::createFromFormat('d/m/Y', $b[6]);
        return strcmp($dateA, $dateB);
    });

}catch (RequestException $e) {
    echo 'Error en la solicitud: ' . $e->getMessage();
}catch(Exception $e){
    echo 'Error: ' . $e->getMessage();
}
// Solo cargar el HTML si no ocurrió un error
if (!$error_occurred):
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bitácora de Controles</title>
    <!-- Incluir Bootstrap CSS -->
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <!-- Incluir DataTables CSS -->
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.11.5/css/jquery.dataTables.css">
    <!-- Incluir DataTables Buttons CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/1.7.1/css/buttons.dataTables.min.css">
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

        .sidebar form {
            width: 100%;
            padding: 10px;
        }

        .sidebar button {
            width: 100%;
            margin-bottom: 10px;
        }
        .sidebar.open {
            left: 0; /* Mostrar el menú cuando está abierto */
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

        .form-control, .form-control:focus {
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

            .sidebar.active {
                transform: translateX(0);
            }

            .content {
                margin-left: 0;
                width: 100%;
            }

            .content.active {
                margin-left: 250px;
                width: calc(100% - 250px);
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
                <h1>Bitácora de Controles</h1>
            </div>
            <!-- Campos de entrada para los filtros -->
            <div class="row mb-3">
                <div class="col-md-2">
                    <label for="filterObjeto">Objeto controlado:</label>
                    <select id="filterObjeto" class="form-control">
                        <option value="">Todos</option>
                        <?php
                        $uniqueValues = []; // Array para almacenar valores únicos
                        foreach ($filteredData as $fila):
                            // Verificar si el valor ya está en el array de valores únicos
                            if (in_array($fila[2], $uniqueValues)) {
                                // Si el valor ya está en el array, continuar con la iteración
                                continue;
                            }
                            // Añadir el valor al array de valores únicos
                            $uniqueValues[] = $fila[2];
                            ?>
                            <option value="<?php echo htmlspecialchars($fila[2]); ?>"><?php echo htmlspecialchars($fila[2]); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label for="filterFechaInicio">Fecha inicio:</label>
                    <input type="date" id="filterFechaInicio" class="form-control">
                </div>
                <div class="col-md-2">
                    <label for="filterFechaFin">Fecha fin:</label>
                    <input type="date" id="filterFechaFin" class="form-control">
                </div>
            </div>
            <div class="table-responsive">
                <table id="bitacoraTable" class="table table-dark table-striped">
                    <thead>
                        <tr>
                            <th>Correo Usuario</th>
                            <th>ID Control</th>
                            <th>Objeto controlado</th>
                            <th>Respuesta</th>
                            <th>Observaciones</th>
                            <th>Nombre y URI del archivo</th>
                            <th>Fecha y hora</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($filteredData as $row): ?>
                            <tr>
                                <?php foreach ($row as $cell): ?>
                                    <td><?php echo htmlspecialchars($cell); ?></td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Incluir jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- Incluir DataTables JS -->
    <script type="text/javascript" charset="utf8" src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.js"></script>
    <!-- Incluir DataTables Buttons JS -->
    <script src="https://cdn.datatables.net/buttons/1.7.1/js/dataTables.buttons.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.1.3/jszip.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/1.7.1/js/buttons.html5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/1.7.1/js/buttons.print.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.36/pdfmake.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.36/vfs_fonts.js"></script>
    <!-- Incluir Moment.js para el manejo de fechas -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.29.1/moment.min.js"></script>
    <!-- Incluir DataTables DateTime plugin -->
    <script src="https://cdn.datatables.net/datetime/1.1.0/js/dataTables.dateTime.min.js"></script>

    <!-- Incluir Bootstrap JS y dependencias -->
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.4/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script>
        $(document).ready(function() {
            var table = $('#bitacoraTable').DataTable({
                columnDefs: [
                    {
                        targets: 6, // Índice de la columna "Fecha y hora"
                        render: function(data, type, row) {
                            if (type === 'sort' || type === 'type') {
                                return moment(data, 'DD/MM/YYYY HH:mm:ss').format('YYYYMMDDHHmmss');
                            }
                            return data;
                        }
                    }
                ],
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

            // Filtro por Objeto a controlar
            $('#filterObjeto').on('change', function() {
                table.column(2).search(this.value).draw();
            });

            // Filtro por rango de fechas
            
            $.fn.dataTable.ext.search.push(
                function(settings, data, dataIndex) {
                    var min = $('#filterFechaInicio').val();
                    var max = $('#filterFechaFin').val();
                    var date = data[6]; // Asumiendo que la columna "Fecha y hora" es la séptima columna (índice 6)
                    
                    // Función para convertir el formato de fecha
                    function parseDate(dateStr) {
                        var parts = dateStr.split(' ');
                        var dateParts = parts[0].split('/');
                        var timeParts = parts[1].split(':');
                        return new Date(dateParts[2], dateParts[1] - 1, dateParts[0], timeParts[0], timeParts[1]);
                    }

                    var dateObj = parseDate(date);
                    
                    // Convertir las fechas de inicio y fin a objetos Date
                    var minDate = min ? new Date(min) : null;
                    var maxDate = max ? new Date(max) : null;

                    // Convertir las fechas de inicio y fin a objetos Date y ajustar a medianoche
                    var minDate = min ? new Date(min + 'T00:00:00') : null;
                    var maxDate = max ? new Date(max + 'T23:59:59') : null;
                    
                    // Comparar las fechas
                    if (
                        (!minDate || dateObj >= minDate) &&
                        (!maxDate || dateObj <= maxDate)
                    ) {
                        return true;
                    }
                    return false;
                }
            );

            $('#filterFechaInicio, #filterFechaFin').on('change', function() {
                table.draw();
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