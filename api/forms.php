<?php
require __DIR__ . '/config.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Methods: POST, OPTIONS');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Only POST allowed.']);
    exit;
}

try {
    $body = $_POST;
    $email = safeTrim($body['email'] ?? $body['E-Mail'] ?? null);
    if ($email === null || $email === '') {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'E-Mail is required.']);
        exit;
    }

    $source = normalizeSource($body, $_SERVER);
    $company = safeTrim($body['company'] ?? $body['Unternehmen'] ?? null);
    $website = safeTrim($body['website'] ?? $body['Website'] ?? null);
    $firstName = safeTrim($body['first_name'] ?? $body['Vorname'] ?? $body['firstname'] ?? null);
    $lastName = safeTrim($body['last_name'] ?? $body['Nachname'] ?? $body['lastname'] ?? null);
    $fullName = safeTrim($body['full_name'] ?? $body['name'] ?? null) ?? trim(($firstName ?? '') . ' ' . ($lastName ?? ''));
    $phone = safeTrim($body['phone'] ?? $body['Telefon'] ?? null);
    $productInterest = safeTrim($body['product_interest'] ?? $body['Produktinteresse'] ?? $body['interest'] ?? null);
    $city = safeTrim($body['city'] ?? $body['Stadt'] ?? null);
    $sourceChannel = safeTrim($body['source_channel'] ?? $body['Quelle'] ?? null);
    $interest = safeTrim($body['interest'] ?? null);
    $profile = safeTrim($body['profile'] ?? $body['profil'] ?? null);
    $message = safeTrim($body['message'] ?? null);
    $consentContact = normalizeBoolean($body['consent_contact'] ?? $body['Einwilligung Kontakt'] ?? $body['contact-consent'] ?? false);
    $consentPrivacy = normalizeBoolean($body['consent_privacy'] ?? $body['Datenschutz akzeptiert'] ?? $body['privacy-consent'] ?? false);

    $dsn = dbDsn();
    $pdo = null;

    try {
        $pdo = dbConnect();
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Database is not configured yet. Add MariaDB credentials to .env and run db/init.php.',
            'debug' => $e->getMessage(),
        ]);
        exit;
    }

    $sql = 'INSERT INTO form_submissions (
        source,
        company,
        website,
        first_name,
        last_name,
        full_name,
        email,
        phone,
        product_interest,
        city,
        source_channel,
        interest,
        profile,
        message,
        consent_contact,
        consent_privacy,
        raw_payload,
        created_at
    ) VALUES (
        :source,
        :company,
        :website,
        :first_name,
        :last_name,
        :full_name,
        :email,
        :phone,
        :product_interest,
        :city,
        :source_channel,
        :interest,
        :profile,
        :message,
        :consent_contact,
        :consent_privacy,
        :raw_payload,
        NOW()
    )';

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':source' => $source,
        ':company' => $company,
        ':website' => $website,
        ':first_name' => $firstName,
        ':last_name' => $lastName,
        ':full_name' => $fullName,
        ':email' => $email,
        ':phone' => $phone,
        ':product_interest' => $productInterest,
        ':city' => $city,
        ':source_channel' => $sourceChannel,
        ':interest' => $interest,
        ':profile' => $profile,
        ':message' => $message,
        ':consent_contact' => $consentContact ? 1 : 0,
        ':consent_privacy' => $consentPrivacy ? 1 : 0,
        ':raw_payload' => json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    ]);

    $id = (int) $pdo->lastInsertId();

    $smtpConfig = smtpConfig();
    $smtpReady = $smtpConfig['host'] !== '' && $smtpConfig['username'] !== '' && $smtpConfig['password'] !== '';

    if ($smtpReady) {
        $subject = 'Nuevo lead: ' . ($company ?: $fullName ?: 'AIESEC formulario');
        $mailBody = "Fuente: {$source}\n";
        $mailBody .= "Empresa: " . ($company ?: '-') . "\n";
        $mailBody .= "Nombre: " . ($fullName ?: '-') . "\n";
        $mailBody .= "Email: {$email}\n";
        $mailBody .= "Telefono: " . ($phone ?: '-') . "\n";
        $mailBody .= "Producto / interés: " . ($productInterest ?: $interest ?: '-') . "\n";
        $mailBody .= "Ciudad: " . ($city ?: '-') . "\n";
        $mailBody .= "Cómo nos encontró: " . ($sourceChannel ?: '-') . "\n";
        $mailBody .= "Website: " . ($website ?: '-') . "\n";
        $mailBody .= "Perfil: " . ($profile ?: '-') . "\n";
        $mailBody .= "Mensaje: " . ($message ?: '-') . "\n";
        $mailBody .= "Consent contacto: " . ($consentContact ? 'Sí' : 'No') . "\n";
        $mailBody .= "Consent privacidad: " . ($consentPrivacy ? 'Sí' : 'No') . "\n";

        try {
            sendSmtpMail($smtpConfig, $subject, $mailBody, $smtpConfig['to'], $smtpConfig['from']);
        } catch (Throwable $mailError) {
            http_response_code(201);
            echo json_encode([
                'success' => true,
                'message' => 'Lead saved in database, but email notification could not be sent.',
                'id' => $id,
                'debug' => $mailError->getMessage(),
            ]);
            exit;
        }
    }

    echo json_encode([
        'success' => true,
        'message' => 'Form submitted successfully.',
        'id' => $id,
    ]);
    exit;
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Unexpected server error.',
        'debug' => $e->getMessage(),
    ]);
    exit;
}
