<?php
require_once 'config.php';

define('EFI_BASE_URL', getenv('EFI_SANDBOX') === 'true' ? 'https://efipay.com.br' : 'https://efipay.com.br');
define('EFI_CLIENT_ID', getenv('EFI_CLIENT_ID') ?: '');
define('EFI_CLIENT_SECRET', getenv('EFI_CLIENT_SECRET') ?: '');
define('EFI_CHAVE_PIX', getenv('EFI_CHAVE_PIX') ?: '');

function efiCertPath(): string {
    $caminho = sys_get_temp_dir() . '/efi_cert.pem';
    $conteudo = getenv('EFI_CERT_PEM') ?: '';
    if ($conteudo !== '' && !file_exists($caminho)) {
        file_put_contents($caminho, str_replace('\n', "\n", $conteudo));
    }
    return $caminho;
}

function efiRequest(string $metodo, string $endpoint, ?array $body = null, ?string $token = null): array {
    $cert = efiCertPath();
    $ch = curl_init(EFI_BASE_URL . $endpoint);

    $headers = ['Content-Type: application/json'];
    if ($token) {
        $headers[] = 'Authorization: Bearer ' . $token;
    } else {
        curl_setopt($ch, CURLOPT_USERPWD, EFI_CLIENT_ID . ':' . EFI_CLIENT_SECRET);
    }

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST  => $metodo,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_SSLCERT        => $cert,
        CURLOPT_SSLKEY         => $cert,
    ]);

    if ($body !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    }

    $resp = curl_exec($ch);
    $err  = curl_error($ch);
    curl_close($ch);

    if ($err) {
        throw new Exception('Erro cURL Efí: ' . $err);
    }
    return json_decode($resp, true) ?: [];
}

function efiGetToken(): string {
    $res = efiRequest('POST', '/oauth/token', ['grant_type' => 'client_credentials']);
    if (empty($res['access_token'])) {
        throw new Exception('Falha ao autenticar na Efí: ' . json_encode($res));
    }
    return $res['access_token'];
}

function efiCriarCobrancaPix(string $txid, float $valor, string $descricao): array {
    $token = efiGetToken();
    $payload = [
        'calendario' => ['expiracao' => 3600],
        'valor'      => ['original' => number_format($valor, 2, '.', '')],
        'chave'      => EFI_CHAVE_PIX,
        'solicitacaoPagador' => mb_substr($descricao, 0, 140)
    ];
    $cob = efiRequest('PUT', '/v2/cob/' . $txid, $payload, $token);
    if (empty($cob['pixCopiaECola'])) {
        throw new Exception('Erro ao gerar PIX Efí: ' . json_encode($cob));
    }
    return $cob;
}

function enviarParaProvedorSMM(int $providerServiceId, string $link, int $quantidade): array {
    $ch = curl_init(SMM_API_URL);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query([
            'key'      => SMM_API_KEY,
            'action'   => 'add',
            'service'  => $providerServiceId,
            'link'     => $link,
            'quantity' => $quantidade
        ])
    ]);
    $resp = curl_exec($ch);
    curl_close($ch);
    return json_decode($resp, true) ?: [];
}
