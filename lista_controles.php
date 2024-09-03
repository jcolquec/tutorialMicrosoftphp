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

// Verificar si el token de acceso está presente y es válido
if (!isset($_SESSION['accessToken']) || time() >= $_SESSION['tokenExpires']) {
    // Si el token ha expirado, refrescar el token
    try {
        $client = new Client();
        $accessToken = refreshAccessToken($client, $_SESSION['refreshToken'], $tenant_id, $client_id, $client_secret, $redirect_uri);

        // Guardar el nuevo token de acceso y el token de refresco en la sesión
        $_SESSION['accessToken'] = $accessToken['access_token'];
        $_SESSION['refreshToken'] = $accessToken['refresh_token'];
        $_SESSION['tokenExpires'] = time() + $accessToken['expires_in'];
    } catch (RequestException $e) {
        echo 'Error en la solicitud: ' . $e->getMessage();
        exit();
    } catch (Exception $e) {
        echo 'Error refrescando el access token: ' . $e->getMessage();
        exit();
    }
}

try {
    $client = new Client();

    // Obtener el correo del usuario
    $correo = getUserEmail($client, $_SESSION['accessToken']);

    $dataControles = getWorksheetValues($client, $itemId, $worksheetIdControles, $_SESSION['accessToken'], $driveId);
    // Leer un rango específico
    
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
function getWorksheetValues(Client $client, $fileId, $worksheetId, $accessToken, $driveId) {
    $response = $client->request('GET', "https://graph.microsoft.com/v1.0/drives/$driveId/items/$fileId/workbook/worksheets/$worksheetId/usedRange?$select=text", [
        'headers' => [
            'Authorization' => 'Bearer ' . $accessToken,
        ],
    ]);

    return json_decode($response->getBody(), true);
}
?>