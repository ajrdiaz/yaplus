<?php

namespace App\Services;

use Google\Client;
use Google\Service\Sheets;
use Illuminate\Support\Facades\Log;

class GoogleSheetsService
{
    private $client;
    private $service;

    public function __construct()
    {
        try {
            $this->client = new Client();
            
            // Configurar las credenciales
            $credentialsPath = storage_path('app/google-credentials.json');
            
            if (!file_exists($credentialsPath)) {
                Log::warning('Google Sheets: Archivo de credenciales no encontrado en: ' . $credentialsPath);
                return;
            }
            
            $this->client->setAuthConfig($credentialsPath);
            $this->client->addScope(Sheets::SPREADSHEETS_READONLY);
            $this->service = new Sheets($this->client);
            
        } catch (\Exception $e) {
            Log::error('Google Sheets: Error al configurar servicio: ' . $e->getMessage());
        }
    }

    /**
     * Verificar si el servicio está configurado
     */
    public function isConfigured(): bool
    {
        return $this->service !== null;
    }

    /**
     * Obtener información de una hoja de cálculo
     */
    public function getSpreadsheetInfo(string $spreadsheetId): ?array
    {
        try {
            $spreadsheet = $this->service->spreadsheets->get($spreadsheetId);
            
            return [
                'id' => $spreadsheet->getSpreadsheetId(),
                'title' => $spreadsheet->getProperties()->getTitle(),
                'url' => $spreadsheet->getSpreadsheetUrl(),
                'sheets' => collect($spreadsheet->getSheets())->map(function ($sheet) {
                    return [
                        'id' => $sheet->getProperties()->getSheetId(),
                        'title' => $sheet->getProperties()->getTitle(),
                    ];
                })->toArray(),
            ];
        } catch (\Exception $e) {
            Log::error('Error al obtener información de spreadsheet', [
                'spreadsheet_id' => $spreadsheetId,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Detectar automáticamente el nombre de la primera hoja
     */
    public function getFirstSheetName(string $spreadsheetId): ?string
    {
        try {
            $info = $this->getSpreadsheetInfo($spreadsheetId);
            if (!empty($info['sheets'])) {
                return $info['sheets'][0]['title'];
            }
            return null;
        } catch (\Exception $e) {
            Log::error('Error al obtener nombre de primera hoja', [
                'spreadsheet_id' => $spreadsheetId,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Leer datos de una hoja de cálculo
     * 
     * @param string $spreadsheetId ID de la hoja
     * @param string $range Rango a leer (ej: "Form Responses 1!A1:Z1000" o "A1:Z1000")
     * @return array|null
     */
    public function readSheet(string $spreadsheetId, string $range = 'A1:Z1000'): ?array
    {
        try {
            // Si el rango no incluye el nombre de la hoja, usar la primera hoja
            if (strpos($range, '!') === false) {
                $sheetName = $this->getFirstSheetName($spreadsheetId);
                if ($sheetName) {
                    $range = "'{$sheetName}'!{$range}";
                }
            }
            
            $response = $this->service->spreadsheets_values->get($spreadsheetId, $range);
            $values = $response->getValues();

            if (empty($values)) {
                return [];
            }

            // Primera fila son los headers (preguntas)
            $headers = array_shift($values);
            
            // Procesar cada respuesta
            $responses = [];
            foreach ($values as $row) {
                $response = [];
                foreach ($headers as $index => $header) {
                    $response[$header] = $row[$index] ?? '';
                }
                $responses[] = $response;
            }

            return [
                'headers' => $headers,
                'responses' => $responses,
                'total' => count($responses),
            ];

        } catch (\Exception $e) {
            Log::error('Error al leer hoja de cálculo', [
                'spreadsheet_id' => $spreadsheetId,
                'range' => $range,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Obtener solo las respuestas nuevas (después de una fecha)
     */
    public function getNewResponses(string $spreadsheetId, string $range, ?\DateTime $since = null): ?array
    {
        $data = $this->readSheet($spreadsheetId, $range);
        
        if (!$data || !$since) {
            return $data;
        }

        // Filtrar respuestas por fecha (asume que la primera columna es timestamp)
        $filtered = [];
        foreach ($data['responses'] as $response) {
            $timestamp = $response[$data['headers'][0]] ?? null;
            
            if ($timestamp) {
                try {
                    $responseDate = new \DateTime($timestamp);
                    if ($responseDate > $since) {
                        $filtered[] = $response;
                    }
                } catch (\Exception $e) {
                    // Si no se puede parsear la fecha, incluir la respuesta
                    $filtered[] = $response;
                }
            }
        }

        return [
            'headers' => $data['headers'],
            'responses' => $filtered,
            'total' => count($filtered),
        ];
    }

    /**
     * Extraer ID de spreadsheet de una URL
     */
    public static function extractSpreadsheetId(string $url): ?string
    {
        // Formato: https://docs.google.com/spreadsheets/d/SPREADSHEET_ID/edit
        if (preg_match('/\/spreadsheets\/d\/([a-zA-Z0-9-_]+)/', $url, $matches)) {
            return $matches[1];
        }
        
        return null;
    }
}
