<?php
/**
 * Servicio de IA (Google Gemini) para generar soluciones automáticas basadas en el historial del cliente
 */

require_once __DIR__ . '/../config/ai.php';

class AIService
{
    private string $apiKey;
    private string $model;
    private int $timeout;
    private string $apiUrl = 'https://generativelanguage.googleapis.com/v1/models/';

    public function __construct()
    {
        $config = require __DIR__ . '/../config/ai.php';
        $this->apiKey = $config['gemini']['api_key'];
        $this->model  = $config['gemini']['model'];
        $this->timeout = $config['gemini']['timeout'];
    }

    /**
     * Genera una solución sugerida basada en la solicitud del cliente y su historial
     */
    public function generateSolution(array $requestData, array $clientHistory = []): array
    {
        $prompt = $this->buildPrompt($requestData, $clientHistory);

        $payload = [
            'contents' => [
                [
                    'role' => 'user',
                    'parts' => [['text' => $prompt]]
                ]
            ],
            'generationConfig' => [
                'temperature'     => 0.3,
                'maxOutputTokens' => 2048,
                'responseMimeType' => 'application/json',
            ],
        ];

        $response = $this->callGemini($payload);

        if (isset($response['error'])) {
            return [
                'success' => false,
                'error'   => $response['error']['message'] ?? 'Error desconocido de IA',
            ];
        }

        $content = $response['candidates'][0]['content']['parts'][0]['text'] ?? '';

        return [
            'success'   => true,
            'solution'  => $this->parseSolution($content),
            'raw'       => $content,
        ];
    }

    /**
     * Construye el prompt con contexto del cliente y su historial
     */
    private function buildPrompt(array $requestData, array $clientHistory): string
    {
        $prompt = "NUEVA SOLICITUD DE CLIENTE:\n";
        $prompt .= "------------------------\n";
        $prompt .= "Nombre: {$requestData['nombre']}\n";
        $prompt .= "Email: {$requestData['email']}\n";
        $prompt .= "Teléfono: " . ($requestData['telefono'] ?: 'No proporcionado') . "\n";
        $prompt .= "Tipo: {$requestData['tipo']}\n";
        $prompt .= "Prioridad: {$requestData['prioridad']}\n";
        $prompt .= "Descripción: {$requestData['descripcion']}\n\n";

        if (!empty($clientHistory)) {
            $prompt .= "HISTORIAL DEL CLIENTE (últimas 5 solicitudes):\n";
            $prompt .= "------------------------\n";
            foreach ($clientHistory as $ticket) {
                $prompt .= "- {$ticket['codigo_caso']} | {$ticket['tipo']} | {$ticket['estado']} | {$ticket['fecha_creacion']}\n";
                $prompt .= "  Descripción: {$ticket['descripcion']}\n";
            }
            $prompt .= "\n";
        } else {
            $prompt .= "HISTORIAL: Cliente sin solicitudes previas.\n\n";
        }

        $prompt .= "INSTRUCCIONES:\n";
        $prompt .= "Eres un experto en atención al cliente de Everline Service. Analiza la solicitud y genera ÚNICAMENTE un JSON válido con esta estructura exacta:\n";
        $prompt .= "{\n";
        $prompt .= '  "resumen": "string (máx 2 líneas)",' . "\n";
        $prompt .= '  "categoria_sugerida": "string (Soporte técnico|Facturación|Información de producto|Reclamo|Otro)",' . "\n";
        $prompt .= '  "prioridad_sugerida": "string (Baja|Media|Alta|Urgente) con justificación breve",' . "\n";
        $prompt .= '  "solucion_inmediata": ["string", "string", "string"],' . "\n";
        $prompt .= '  "acciones_equipo": ["string", "string", "string"],' . "\n";
        $prompt .= '  "escalamiento": "string (sí/no y área si aplica)",' . "\n";
        $prompt .= '  "tiempo_estimado": "string (ej: 2-4 horas, 1 día, 2-3 días)",' . "\n";
        $prompt .= '  "conocimiento_base": ["string", "string"]' . "\n";
        $prompt .= "}\n";
        $prompt .= "NO incluyas markdown, NO incluyas texto extra, SOLO el JSON.";

        return $prompt;
    }

    /**
     * Parsea la respuesta JSON de la IA
     */
    private function parseSolution(string $content): array
    {
        $json = json_decode($content, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            // Intentar extraer JSON si viene envuelto en texto
            if (preg_match('/\{.*\}/s', $content, $matches)) {
                $json = json_decode($matches[0], true);
            }
        }

        if (!$json || json_last_error() !== JSON_ERROR_NONE) {
            return [
                'resumen'            => 'No se pudo parsear la respuesta de IA',
                'categoria_sugerida' => null,
                'prioridad_sugerida' => null,
                'solucion_inmediata' => [],
                'acciones_equipo'    => [],
                'escalamiento'       => 'Revisar manualmente',
                'tiempo_estimado'    => 'Desconocido',
                'conocimiento_base'  => [],
            ];
        }

        // Asegurar estructura completa
        return [
            'resumen'             => $json['resumen'] ?? 'Sin resumen',
            'categoria_sugerida'  => $json['categoria_sugerida'] ?? null,
            'prioridad_sugerida'  => $json['prioridad_sugerida'] ?? null,
            'solucion_inmediata'  => $json['solucion_inmediata'] ?? [],
            'acciones_equipo'     => $json['acciones_equipo'] ?? [],
            'escalamiento'        => $json['escalamiento'] ?? 'No especificado',
            'tiempo_estimado'     => $json['tiempo_estimado'] ?? 'Desconocido',
            'conocimiento_base'   => $json['conocimiento_base'] ?? [],
        ];
    }

    /**
     * Realiza la llamada HTTP a Gemini API
     */
    private function callGemini(array $payload): array
    {
        $url = $this->apiUrl . $this->model . ':generateContent?key=' . $this->apiKey;
        
        $ch = curl_init($url);
        
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
            ],
            CURLOPT_TIMEOUT        => $this->timeout,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error    = curl_error($ch);
        curl_close($ch);

        if ($error) {
            return ['error' => ['message' => 'Error de conexión: ' . $error]];
        }

        $data = json_decode($response, true);

        if ($httpCode >= 400) {
            return ['error' => $data['error'] ?? ['message' => "HTTP $httpCode"]];
        }

        return $data;
    }
}