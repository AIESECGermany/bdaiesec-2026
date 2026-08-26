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
    writeAppLog('Invalid HTTP method received.', ['method' => $_SERVER['REQUEST_METHOD'] ?? null, 'uri' => $_SERVER['REQUEST_URI'] ?? null]);
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Only POST allowed.']);
    exit;
}

try {
    $body = $_POST;
    writeAppLog('Form submission started.', [
        'method' => $_SERVER['REQUEST_METHOD'] ?? null,
        'uri' => $_SERVER['REQUEST_URI'] ?? null,
        'keys' => array_keys($body),
        'source' => normalizeSource($body, $_SERVER),
    ]);

    $email = safeTrim($body['email'] ?? $body['E-Mail'] ?? null);
    if ($email === null || $email === '') {
        writeAppLog('Email validation failed for form submission.', ['body' => $body]);
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'E-Mail is required.']);
        exit;
    }

    $source = normalizeSource($body, $_SERVER);
    $campaignContext = normalizeCampaignContext($body, $_SERVER);
    $company = safeTrim(readFieldValue($body, ['company', 'Unternehmen']));
    $website = safeTrim(readFieldValue($body, ['website', 'Website']));
    $firstName = safeTrim(readFieldValue($body, ['first_name', 'Vorname', 'firstname']));
    $lastName = safeTrim(readFieldValue($body, ['last_name', 'Nachname', 'lastname']));
    $fullName = safeTrim(readFieldValue($body, ['full_name', 'name'])) ?? trim(($firstName ?? '') . ' ' . ($lastName ?? ''));
    $phone = safeTrim(readFieldValue($body, ['phone', 'Telefon']));
    $productInterest = safeTrim(readFieldValue($body, ['product_interest', 'Produktinteresse', 'interest']));
    $city = safeTrim(readFieldValue($body, ['city', 'Stadt']));
    $sourceChannel = safeTrim(readFieldValue($body, ['source_channel', 'Quelle']));
    $interest = safeTrim($body['interest'] ?? null);
    $profile = safeTrim(readFieldValue($body, ['profile', 'profil']));
    $message = safeTrim($body['message'] ?? null);
    $consentContact = normalizeBoolean(readFieldValue($body, ['consent_contact', 'Einwilligung_Kontakt', 'Einwilligung Kontakt', 'contact-consent']));
    $consentPrivacy = normalizeBoolean(readFieldValue($body, ['consent_privacy', 'Datenschutz_akzeptiert', 'Datenschutz akzeptiert', 'privacy-consent']));

    $dsn = dbDsn();
    $pdo = null;

    try {
        $pdo = dbConnect();
    } catch (Throwable $e) {
        writeAppLog('Database connection failed.', ['error' => $e->getMessage()]);
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
        social_source,
        referrer_url,
        landing_url,
        utm_source,
        utm_medium,
        utm_campaign,
        utm_content,
        utm_term,
        campaign_params,
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
        :social_source,
        :referrer_url,
        :landing_url,
        :utm_source,
        :utm_medium,
        :utm_campaign,
        :utm_content,
        :utm_term,
        :campaign_params,
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
        ':social_source' => $campaignContext['social_source'],
        ':referrer_url' => $campaignContext['referrer_url'],
        ':landing_url' => $campaignContext['landing_url'],
        ':utm_source' => $campaignContext['utm_source'],
        ':utm_medium' => $campaignContext['utm_medium'],
        ':utm_campaign' => $campaignContext['utm_campaign'],
        ':utm_content' => $campaignContext['utm_content'],
        ':utm_term' => $campaignContext['utm_term'],
        ':campaign_params' => $campaignContext['campaign_params'],
        ':consent_contact' => $consentContact ? 1 : 0,
        ':consent_privacy' => $consentPrivacy ? 1 : 0,
        ':raw_payload' => json_encode($body + ['campaign_context' => $campaignContext], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    ]);

    $id = (int) $pdo->lastInsertId();

    $smtpConfig = smtpConfig();
    $smtpReady = $smtpConfig['host'] !== '' && $smtpConfig['username'] !== '' && $smtpConfig['password'] !== '';

    if ($smtpReady) {
        $subject = 'New lead: ' . ($company ?: $fullName ?: 'AIESEC form');
        $mailBody = "Source: {$source}\n";
        $mailBody .= "Company: " . ($company ?: '-') . "\n";
        $mailBody .= "Name: " . ($fullName ?: '-') . "\n";
        $mailBody .= "Email: {$email}\n";
        $mailBody .= "Phone: " . ($phone ?: '-') . "\n";
        $mailBody .= "Product / interest: " . ($productInterest ?: $interest ?: '-') . "\n";
        $mailBody .= "City: " . ($city ?: '-') . "\n";
        $mailBody .= "How they found us: " . ($sourceChannel ?: '-') . "\n";
        $mailBody .= "Website: " . ($website ?: '-') . "\n";
        $mailBody .= "Referrer URL: " . ($campaignContext['referrer_url'] ?: '-') . "\n";
        $mailBody .= "Landing URL: " . ($campaignContext['landing_url'] ?: '-') . "\n";
        $mailBody .= "UTM source: " . ($campaignContext['utm_source'] ?: '-') . "\n";
        $mailBody .= "UTM medium: " . ($campaignContext['utm_medium'] ?: '-') . "\n";
        $mailBody .= "UTM campaign: " . ($campaignContext['utm_campaign'] ?: '-') . "\n";
        $mailBody .= "Campaign params: " . ($campaignContext['campaign_params'] !== '' ? $campaignContext['campaign_params'] : '-') . "\n";
        $mailBody .= "Profile: " . ($profile ?: '-') . "\n";
        $mailBody .= "Message: " . ($message ?: '-') . "\n";
        $mailBody .= "Contact consent: " . ($consentContact ? 'Yes' : 'No') . "\n";
        $mailBody .= "Privacy consent: " . ($consentPrivacy ? 'Yes' : 'No') . "\n";

        try {
            sendSmtpMail($smtpConfig, $subject, $mailBody, $smtpConfig['to'], $smtpConfig['from']);
            writeAppLog('SMTP email sent successfully.', ['id' => $id, 'to' => $smtpConfig['to'], 'subject' => $subject]);
        } catch (Throwable $mailError) {
            writeAppLog('SMTP email failed.', ['id' => $id, 'error' => $mailError->getMessage(), 'to' => $smtpConfig['to']]);
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

    writeAppLog('Form saved successfully.', ['id' => $id, 'source' => $source, 'email' => $email]);

    echo json_encode([
        'success' => true,
        'message' => 'Form submitted successfully.',
        'id' => $id,
    ]);
    exit;
} catch (Throwable $e) {
    writeAppLog('Unexpected server error.', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Unexpected server error.',
        'debug' => $e->getMessage(),
    ]);
    exit;
}
