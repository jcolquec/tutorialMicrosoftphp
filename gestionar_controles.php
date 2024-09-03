<?php
session_start();
require 'vendor/autoload.php';

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Dotenv\Dotenv;

// Load .env file
$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();
$dotenv->required(['DRIVE_ID', 'ITEM_ID','WORKSHEET_ID_CTRL', 'WORKSHEET_ID_CTRLXCORREO']); 

$client = new Client();
$driveId = $_ENV['DRIVE_ID'];
$itemId = $_ENV['ITEM_ID'];
$worksheetIdControles = $_ENV['WORKSHEET_ID_CTRL'];
$worksheetIdCtrlxCorreo = $_ENV['WORKSHEET_ID_CTRLXCORREO'];

// Verificar si el token de acceso está presente y es válido
if (!isset($_SESSION['accessToken']) || time() >= $_SESSION['tokenExpires']) {
    // Si el token ha expirado, refrescar el token
    try {
        $accessToken = refreshAccessToken($client, $_SESSION['refreshToken'], $_ENV['TENANT_ID'], $_ENV['CLIENT_ID'], $_ENV['CLIENT_SECRET'], $_ENV['REDIRECT_URI']);

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
        echo 'Error refrescando el access token: ' . $e->getMessage();
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

/**
 * Obtiene los valores de la hoja de cálculo.
 */
function getWorksheetValues($client, $itemId, $worksheetId, $accessToken, $driveId) {
    try {
        $response = $client->request('GET', "https://graph.microsoft.com/v1.0/drives/$driveId/items/$itemId/workbook/worksheets/$worksheetId/usedRange", [
            'headers' => [
                'Authorization' => 'Bearer ' . $accessToken,
            ],
        ]);
        return json_decode($response->getBody(), true)['values'];
    } catch (RequestException $e) {
        throw new Exception('Error en la solicitud: ' . $e->getMessage());
    }
}

/**
 * Refresca el access token usando el refresh token.
 */
function refreshAccessToken(Client $client, $refresh_token, $tenant_id, $client_id, $client_secret, $redirect_uri) {
    $response = $client->request('POST', "https://login.microsoftonline.com/$tenant_id/oauth2/v2.0/token", [
        'form_params' => [
            'client_id' => $client_id,
            'client_secret' => $client_secret,
            'grant_type' => 'refresh_token',
            'refresh_token' => $refresh_token,
            'redirect_uri' => $redirect_uri,
            'scope' => 'https://graph.microsoft.com/.default'
        ],
    ]);

    return json_decode($response->getBody(), true);
}
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
</head>
<body>
    <div class="container mt-5">
        
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1>Asignar Controles</h1>
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
        <!-- Formulario para agregar nuevas asignaciones -->
        <form id="addForm" method="post" action="agregar_asignacion.php">
            <div class="form-group">
                <label for="correo">Correo del Usuario:</label>
                <input type="email" class="form-control" id="correo" name="correo" required>
            </div>
            <div class="form-group">
                <label for="control">Control a aplicar:</label>
                <select id="control" name="control" class="form-control">
                    <option value="">Todos</option>
                    <?php foreach (array_slice($dataControles, 1) as $row): // Omitir la primera fila ?>
                        <option value="<?php echo htmlspecialchars($row[0]); ?>"><?php echo htmlspecialchars($row[0]); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="btn btn-primary">Agregar Asignación</button>
        </form>
        <hr>
        <!-- Tabla para mostrar las asignaciones actuales -->
        <table id="asignacionesTable" class="table table-striped table-bordered">
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
                placeholder: "Seleccione un objeto",
                allowClear: true
            });
        });
    </script>
</body>
</html>