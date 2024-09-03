<?php
session_start();
require 'vendor/autoload.php';
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Dotenv\Dotenv;

// Load .env file
$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();
$dotenv->required(['DRIVE_ID', 'ITEM_ID','WORKSHEET_ID_REGISTROSCTRL']); 

$driveId = $_ENV['DRIVE_ID'];
$itemId = $_ENV['ITEM_ID'];
$worksheetIdRegistros = $_ENV['WORKSHEET_ID_REGISTROSCTRL'];

// Verificar si la sesión de Microsoft está activa
if (!isset($_SESSION['accessToken'])) {
    // Redirigir al usuario a la página de inicio de sesión si no hay una sesión activa
    header('Location: login.php');
    exit();
}

try{
    // Solicita el access token usando el authorization code
    $client = new Client();

    // Obtener los datos de la hoja de registros
    $dataRegistros = getWorksheetValues($client, $driveId, $itemId, $worksheetIdRegistros, $_SESSION['accessToken']);

    // Filtrar los datos de la hoja de registros en base al correo
    $filteredData = [];
    $filteredFormatData = ['format' => ['font' => []]];

    foreach ($dataRegistros['text'] as $rowIndex => $row) {
        
        if ($rowIndex == 0) {
            continue;
        } else {
            
            $filteredData[] = $row;
            $filteredFormatData['format']['font'][] = isset($formatData['format']['font'][$rowIndex]) ? $formatData['format']['font'][$rowIndex] : [];
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
function getWorksheetValues(Client $client, $driveId, $itemId, $worksheetId, $accessToken) {
    try {
        $select= 'select';
        $response = $client->request('GET', "https://graph.microsoft.com/v1.0/drives/$driveId/items/$itemId/workbook/worksheets/$worksheetId/usedRange?$select=text", [
            'headers' => [
                'Authorization' => 'Bearer ' . $accessToken,
            ],
        ]);
        return json_decode($response->getBody(), true);
    } catch (RequestException $e) {
        throw new Exception('Error en la solicitud: ' . $e->getMessage());
    }
}
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
    <link rel="stylesheet" href="https://cdn.datatables.net/1.10.21/css/jquery.dataTables.min.css">
    <!-- Incluir DataTables Buttons CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/1.7.1/css/buttons.dataTables.min.css">
</head>
<body>
    <div class="container mt-5">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1>Bitácora de Controles</h1>
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
        <!-- Campos de entrada para los filtros -->
        <div class="row mb-3">
            <div class="col-md-4">
                <label for="filterObjeto">Filtrar por Objeto a controlar:</label>
                <select id="filterObjeto" class="form-control">
                    <option value="">Todos</option>
                    <?php foreach ($filteredData as $objeto): ?>
                        <option value="<?php echo htmlspecialchars($objeto[2]); ?>"><?php echo htmlspecialchars($objeto[2]); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label for="filterFechaInicio">Filtrar por Fecha (Inicio):</label>
                <input type="date" id="filterFechaInicio" class="form-control">
            </div>
            <div class="col-md-4">
                <label for="filterFechaFin">Filtrar por Fecha (Fin):</label>
                <input type="date" id="filterFechaFin" class="form-control">
            </div>
        </div>
        <table id="bitacoraTable" class="table table-striped table-bordered">
            <thead>
                <tr>
                    <th>Correo Usuario</th>
                    <th>ID Control</th>
                    <th>Objeto a controlar</th>
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

    <!-- Incluir Bootstrap JS y dependencias -->
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.4/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script>
        $(document).ready(function() {
            var table = $('#bitacoraTable').DataTable({
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
    </script>
</body>
</html>