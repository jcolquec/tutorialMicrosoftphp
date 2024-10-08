<?php

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

/**
 * Obtiene los valores de la hoja de cálculo.
 */
function getWorksheetValues($client, $itemId, $worksheetId, $accessToken, $driveId) {
    try {
        $select = 'values';
        $response = $client->request('GET', "https://graph.microsoft.com/v1.0/drives/$driveId/items/$itemId/workbook/worksheets/$worksheetId/usedRange?$select=text", [
            'headers' => [
                'Authorization' => 'Bearer ' . $accessToken,
            ],
        ]);
        return json_decode($response->getBody(), true)['text'];
    } catch (RequestException $e) {
        throw new Exception('Error en la solicitud: ' . $e->getMessage());
    }
}

/**
 * Obtiene el access token usando el authorization code.
 */
function getAccessToken(Client $client, $authorization_code, $tenant_id, $client_id, $client_secret, $redirect_uri) {
    try {
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

/**
 * Agrega una nueva fila a la hoja de cálculo.
 */
function addRow($client, $itemId, $worksheetId, $range, $data, $accessToken, $driveId) {
    try {
        $response = $client->request('PATCH', "https://graph.microsoft.com/v1.0/drives/$driveId/items/$itemId/workbook/worksheets/$worksheetId/range(address='$range')", [
            'headers' => [
                'Authorization' => 'Bearer ' . $accessToken,
                'Content-Type' => 'application/json',
            ],
            'json' => $data,
        ]);
        return json_decode($response->getBody(), true);
    } catch (RequestException $e) {
        throw new Exception('Error en la solicitud: ' . $e->getMessage());
    }
}

/**
 * Encuentra el índice de la fila que contiene el registro a eliminar.
 */
function findRowIndex($values, $correo, $control) {
    foreach ($values as $index => $row) {
        if ($row[0] == $correo && $row[1] == $control) {
            return $index;
        }
    }
    return -1;
}

/**
 * Elimina la fila especificada.
 */
function deleteRow($client, $itemId, $worksheetId, $rowIndex, $accessToken, $driveId) {
    try {
        // Eliminar la fila
        $response = $client->request('POST', "https://graph.microsoft.com/v1.0/drives/$driveId/items/$itemId/workbook/worksheets/$worksheetId/range(address='A" . ($rowIndex + 1) . ":B" . ($rowIndex + 1) . "')/delete", [
            'headers' => [
                'Authorization' => 'Bearer ' . $accessToken,
            ],
        ]);
    } catch (RequestException $e) {
        throw new Exception('Error en la solicitud: ' . $e->getMessage());
    }
}

/**
 * Obtiene el correo del usuario.
 */
function getUserEmail(Client $client, $accessToken) {
    try {
        $response = $client->request('GET', 'https://graph.microsoft.com/v1.0/me?$select=mail', [
            'headers' => [
                'Authorization' => 'Bearer ' . $accessToken,
                'Accept' => 'application/json',
            ],
        ]);
        return json_decode($response->getBody(), true);
    } catch (RequestException $e) {
        throw new Exception('Error en la solicitud: ' . $e->getMessage());
    }
}

/**
 * Valida el correo del usuario en archivo Excel.
 */
function validarUsuario($client, $itemId, $worksheetId, $accessToken, $correo, $driveId) {
    try {
        // Obtener los valores de la hoja de cálculo
        $dataUsuarios = getWorksheetValues($client, $itemId, $worksheetId, $accessToken, $driveId);
        
        // Recorrer los datos de los usuarios
        foreach ($dataUsuarios as $row) {
            if ($row[0] == $correo) {
                return true;
            }
        }
        // Retornar false si el correo no se encuentra        
        return false;
    } catch (RequestException $e) {
        // Lanzar una excepción con un mensaje claro
        throw new Exception('Error en la solicitud: ' . $e->getMessage());
    }
}

?>
