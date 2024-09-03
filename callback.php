<?php
require 'vendor/autoload.php';
session_start();

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Dotenv\Dotenv;

// Load .env file
$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();
$dotenv->required(['CLIENT_ID', 'TENANT_ID', 'CLIENT_SECRET', 'REDIRECT_URI', 'DRIVE_ID', 'ITEM_ID', 'WORKSHEET_ID_CTRL', 'WORKSHEET_ID_CTRLXCORREO']);

// Configuración básica
$client_id = $_ENV['CLIENT_ID'];
$client_secret = $_ENV['CLIENT_SECRET'];
$tenant_id = $_ENV['TENANT_ID'];
$redirect_uri = $_ENV['REDIRECT_URI']; // Debe coincidir con la URI registrada
$driveId = $_ENV['DRIVE_ID'];
$itemId = $_ENV['ITEM_ID'];
$worksheetIdControles = $_ENV['WORKSHEET_ID_CTRL'];
$worksheetIdCtrlxCorreo = $_ENV['WORKSHEET_ID_CTRLXCORREO'];

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

            $dataControles = getWorksheetValues($client, $itemId, $worksheetIdControles, $_SESSION['accessToken'], $driveId);
            
            // Leer el rango de la hoja de códigos y correos
            $dataCodigos = getWorksheetValues($client, $itemId, $worksheetIdCtrlxCorreo, $_SESSION['accessToken'], $driveId);

            // Filtrar los datos de la hoja de controles en base a los códigos
            $filteredData = [];
            $filteredFormatData = ['format' => ['font' => []]];

            foreach ($dataControles['values'] as $rowIndex => $row) {
                if ($rowIndex == 0) {
                    // Agregar encabezados
                    $filteredData[] = [$row[0], $row[2], $row[4]];
                } else {
                    $codigo = $row[0]; // Suponiendo que el código está en la primera columna
                    foreach ($dataCodigos['values'] as $codigoRow) {
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
            echo 'Error refrescando el access token: ' . $e->getMessage();
        }
    }
}

/**
 * Obtiene el access token usando el authorization code.
 */
function getAccessToken(Client $client, $authorization_code, $tenant_id, $client_id, $client_secret, $redirect_uri) {
    $response = $client->request('POST', "https://login.microsoftonline.com/$tenant_id/oauth2/v2.0/token", [
        'form_params' => [
            'client_id' => $client_id,
            'client_secret' => $client_secret,
            'grant_type' => 'authorization_code',
            'code' => $authorization_code,
            'redirect_uri' => $redirect_uri,
            'scope' => 'https://graph.microsoft.com/.default'
        ],
    ]);

    return json_decode($response->getBody(), true);
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

/**
 * Obtiene el correo del usuario.
 */
function getUserEmail(Client $client, $accessToken) {
    $response = $client->request('GET', 'https://graph.microsoft.com/v1.0/me?$select=mail', [
        'headers' => [
            'Authorization' => 'Bearer ' . $accessToken,
            'Accept' => 'application/json',
        ],
    ]);

    return json_decode($response->getBody(), true);
}

/**
 * Obtiene los valores de la hoja de cálculo.
 */
function getWorksheetValues(Client $client, $itemId, $worksheetId, $accessToken, $driveId) {
    $select = 'select';
    $response = $client->request('GET', "https://graph.microsoft.com/v1.0/drives/$driveId/items/$itemId/workbook/worksheets/$worksheetId/usedRange?$select=text", [
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lista de Controles</title>
    <!-- Bootstrap CSS -->
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <!-- Incluir DataTables CSS -->
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.11.5/css/jquery.dataTables.css">

</head>
<body>
    <div class="container mt-5">
        
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1>Lista de Controles</h1>
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
        <table id="excelDataTable" class="table table-striped table-bordered">
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
    </script>
</body>
</html>
