<?php
namespace App\Services\WhatsApp;

class WhatsAppGatewayFactory {
    /**
     * Create appropriate WhatsApp gateway based on environment & configuration
     * 
     * Rule:
     * - WHATSAPP_GATEWAY=fonnte -> returns FonnteWhatsAppGateway
     *   (If FONNTE_TOKEN is empty or invalid, FonnteWhatsAppGateway itself marks message as FAILED. NO SILENT FALLBACK to simulation!)
     * - WHATSAPP_GATEWAY=simulated (or unset) -> returns SimulatedWhatsAppGateway (explicit simulation)
     * 
     * @param HttpTransportInterface|null $transport Optional transport for testing/mocking
     * @return WhatsAppGatewayInterface
     */
    public static function create(?HttpTransportInterface $transport = null): WhatsAppGatewayInterface {
        $gatewayType = strtolower(trim((string)env('WHATSAPP_GATEWAY', env('WA_GATEWAY', ''))));
        
        $token = trim((string)env('FONNTE_TOKEN', env('WA_API_KEY', env('WA_API_TOKEN', ''))));
        $apiUrl = env('WA_API_URL', 'https://api.fonnte.com/send');

        if ($gatewayType === 'fonnte' || (!empty($token) && $gatewayType !== 'simulated')) {
            // NO SILENT FALLBACK! Return real gateway even if token is empty, so it honestly fails
            return new FonnteWhatsAppGateway($token, $transport, $apiUrl);
        }

        // Default or explicit: simulated
        return new SimulatedWhatsAppGateway();
    }
}
