<?php

declare(strict_types=1);

session_start();

$statePath = __DIR__ . '/mock-state.json';
$baseUrl = getenv('BASE_URL') ?: currentBaseUrl();

$products = [
    [
        'sku' => 'SKU-001',
        'name' => 'Precision Safety Gloves',
        'category' => 'Safety',
        'description' => 'Cut-resistant nitrile work gloves for warehouse teams.',
        'price' => 12.45,
        'currency' => 'GBP',
        'uom' => 'PAIR',
        'classification' => '46181504',
        'accent' => '#2563eb',
    ],
    [
        'sku' => 'SKU-002',
        'name' => 'Industrial Bearing Kit',
        'category' => 'Maintenance',
        'description' => 'Assorted sealed bearings for production line repairs.',
        'price' => 84.20,
        'currency' => 'GBP',
        'uom' => 'KIT',
        'classification' => '31171500',
        'accent' => '#0f766e',
    ],
    [
        'sku' => 'SKU-003',
        'name' => 'Warehouse Label Rolls',
        'category' => 'Packaging',
        'description' => 'Thermal labels for dispatch and stock control.',
        'price' => 18.75,
        'currency' => 'GBP',
        'uom' => 'BOX',
        'classification' => '55121612',
        'accent' => '#b45309',
    ],
];

$state = loadState($statePath, $baseUrl);

function currentBaseUrl(): string
{
    $scheme = $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? null;
    if (!$scheme) {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    }

    $host = $_SERVER['HTTP_X_FORWARDED_HOST'] ?? ($_SERVER['HTTP_HOST'] ?? 'localhost:8000');

    return $scheme . '://' . $host;
}

function defaultState(string $baseUrl): array
{
    return [
        'returned_orders' => [],
        'erp_config' => [
            'from_domain' => 'NetworkId',
            'buyer_identity' => 'DEMO_BUYER',
            'to_domain' => 'DUNS',
            'supplier_identity' => 'REAL_WEBSTORE',
            'sender_domain' => 'NetworkId',
            'sender_identity' => 'DEMO_PROCUREMENT_SYSTEM',
            'shared_secret' => 'topsecret',
            'deployment_mode' => 'production',
            'user_agent' => 'PunchOut ERP Test Harness',
            'username' => 'jdoe12345',
            'user_id' => '12345',
            'user_name' => 'Jamie Buyer',
            'user_first_name' => 'Jamie',
            'user_last_name' => 'Buyer',
            'user_email' => 'buyer@example.test',
            'user_phone' => '555-555-5555',
            'browser_form_post_url' => $baseUrl . '/procurement/return',
            'supplier_setup_url' => '',
            'ship_to_address_id' => 'TEST',
            'ship_to_name' => 'TEST',
            'ship_to_street' => '123 Street Address',
            'ship_to_city' => 'Rockville',
            'ship_to_state' => 'MD',
            'ship_to_postal_code' => '20850',
            'ship_to_country' => 'US',
            'ship_to_country_code' => 'US',
        ],
        'webstore_config' => [
            'supplier_webstore_url' => getenv('SUPPLIER_WEBSTORE_URL') ?: '',
            'punchout_setup_url' => getenv('WEBSTORE_PUNCHOUT_SETUP_URL') ?: '',
            'order_request_url' => getenv('WEBSTORE_ORDER_REQUEST_URL') ?: '',
            'request_timeout_seconds' => 15,
        ],
    ];
}

function loadState(string $statePath, string $baseUrl): array
{
    $defaults = defaultState($baseUrl);
    if (!is_file($statePath)) {
        return $defaults;
    }

    $loaded = json_decode((string) file_get_contents($statePath), true);
    if (!is_array($loaded)) {
        return $defaults;
    }

    return array_replace_recursive($defaults, $loaded);
}

function saveState(string $statePath, array $state): void
{
    file_put_contents($statePath, json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX);
}

function h(string|int|float|null $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function x(string|int|float|null $value): string
{
    return htmlspecialchars((string) $value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
}

function money(float $value): string
{
    return number_format($value, 2, '.', '');
}

function configValue(array $config, string $key, string $default = ''): string
{
    $value = trim((string) ($config[$key] ?? ''));

    return $value === '' ? $default : $value;
}

function namePart(array $config, string $part): string
{
    $fullName = configValue($config, 'user_name');
    $pieces = preg_split('/\s+/', $fullName) ?: [];

    if ($part === 'first') {
        return configValue($config, 'user_first_name', $pieces[0] ?? '');
    }

    $derivedLast = count($pieces) > 1 ? implode(' ', array_slice($pieces, 1)) : '';

    return configValue($config, 'user_last_name', $derivedLast);
}

function uuid(): string
{
    $data = random_bytes(16);
    $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
    $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);

    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}

function findProduct(array $products, string $sku): array
{
    foreach ($products as $product) {
        if ($product['sku'] === $sku) {
            return $product;
        }
    }

    return $products[0];
}

function firstXmlValue(string $xml, string $xpath): string
{
    libxml_use_internal_errors(true);
    $doc = simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NONET);
    if (!$doc) {
        return '';
    }

    $result = $doc->xpath($xpath);
    if (!$result || !isset($result[0])) {
        return '';
    }

    return trim((string) $result[0]);
}

function loadXml(string $xml): ?SimpleXMLElement
{
    libxml_use_internal_errors(true);
    $doc = simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NONET);

    return $doc ?: null;
}

function page(string $content, string $area = 'erp'): string
{
    $isSupplier = $area === 'supplier';
    $headerClass = $isSupplier ? 'supplier' : 'erp';
    $contextLabel = $isSupplier ? 'Supplier shop' : 'ERP / procurement';

    return '<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Mock cXML PunchOut</title>
  <style>
    :root {
      color-scheme: light;
      --ink: #172033;
      --muted: #5d6678;
      --line: #d9dee8;
      --soft: #f5f7fb;
      --panel: #ffffff;
      --brand: #1f6feb;
      --ok: #087f5b;
    }
    * { box-sizing: border-box; }
    body {
      margin: 0;
      font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
      color: var(--ink);
      background: #eef2f8;
      letter-spacing: 0;
    }
    header {
      color: #fff;
      border-bottom: 1px solid rgba(0,0,0,.24);
    }
    header.erp {
      background: #13213b;
    }
    header.supplier {
      background: #0f5f5c;
    }
    .bar {
      max-width: 1180px;
      margin: 0 auto;
      padding: 16px 20px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 16px;
    }
    .brand { font-weight: 750; font-size: 18px; }
    .pill {
      border: 1px solid rgba(255,255,255,.24);
      border-radius: 999px;
      padding: 6px 10px;
      color: #dbe7ff;
      font-size: 13px;
      white-space: nowrap;
    }
    main {
      max-width: 1180px;
      margin: 0 auto;
      padding: 28px 20px 48px;
    }
    h1 { margin: 0 0 8px; font-size: 30px; line-height: 1.15; }
    h2 { margin: 0 0 12px; font-size: 20px; }
    h3 { margin: 0 0 6px; font-size: 16px; }
    p { color: var(--muted); line-height: 1.5; }
    a { color: var(--brand); }
    .grid { display: grid; grid-template-columns: 1fr 360px; gap: 20px; align-items: start; }
    .products { display: grid; grid-template-columns: repeat(auto-fit, minmax(230px, 1fr)); gap: 14px; }
    .card, .panel {
      background: var(--panel);
      border: 1px solid var(--line);
      border-radius: 8px;
      box-shadow: 0 1px 2px rgba(20,28,44,.04);
    }
    .card { padding: 16px; min-height: 210px; display: flex; flex-direction: column; gap: 12px; }
    .panel { padding: 18px; }
    .swatch { width: 40px; height: 8px; border-radius: 999px; }
    .meta { color: var(--muted); font-size: 13px; }
    .price { font-size: 24px; font-weight: 760; margin-top: auto; }
    .row { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; }
    .stack { display: grid; gap: 14px; }
    label { display: grid; gap: 6px; font-size: 13px; font-weight: 650; color: var(--ink); }
    input[type="number"], input[type="text"], input[type="email"], input[type="url"], input[type="password"] {
      width: 82px;
      min-height: 38px;
      border: 1px solid var(--line);
      border-radius: 6px;
      padding: 8px;
      font: inherit;
      background: #fff;
    }
    input[type="text"], input[type="email"], input[type="url"], input[type="password"] {
      width: 100%;
    }
    .form-grid { display: grid; gap: 12px; }
    .admin-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; align-items: start; }
    .erp-panel { border-top: 4px solid #13213b; }
    .supplier-panel { border-top: 4px solid #0f5f5c; }
    .status-ok { border-left-color: var(--ok); background: #e8f7f1; }
    .status-error { border-left-color: #b42318; background: #fff1f0; }
    button, .button {
      min-height: 38px;
      border: 1px solid #185bc3;
      border-radius: 6px;
      background: var(--brand);
      color: #fff;
      padding: 8px 12px;
      font: inherit;
      font-weight: 650;
      text-decoration: none;
      cursor: pointer;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
    }
    .button.secondary, button.secondary {
      color: var(--ink);
      background: #fff;
      border-color: var(--line);
    }
    .button.ok, button.ok {
      background: var(--ok);
      border-color: #066f50;
    }
    .callout {
      border-left: 4px solid var(--brand);
      background: #eaf2ff;
      padding: 12px 14px;
      border-radius: 6px;
      color: #23324c;
    }
    .callout strong { color: var(--ink); }
    pre {
      margin: 0;
      overflow: auto;
      white-space: pre-wrap;
      overflow-wrap: anywhere;
      background: #0e1628;
      color: #d9e7ff;
      border-radius: 8px;
      padding: 14px;
      line-height: 1.45;
      font-size: 13px;
    }
    table { width: 100%; border-collapse: collapse; }
    th, td { padding: 10px 8px; border-bottom: 1px solid var(--line); text-align: left; vertical-align: top; }
    th { font-size: 12px; color: var(--muted); text-transform: uppercase; }
    .total { font-size: 22px; font-weight: 760; text-align: right; }
    .empty { color: var(--muted); padding: 20px; text-align: center; background: var(--soft); border-radius: 8px; }
    @media (max-width: 820px) {
      .grid { grid-template-columns: 1fr; }
      .admin-grid { grid-template-columns: 1fr; }
      .bar { align-items: flex-start; flex-direction: column; }
      h1 { font-size: 24px; }
    }
  </style>
</head>
<body>
  <header class="' . h($headerClass) . '">
    <div class="bar">
      <div class="brand">Mock cXML PunchOut Level 2</div>
      <div class="pill">' . h($contextLabel) . ' &middot; Buyer: Demo Procurement Co</div>
    </div>
  </header>
  <main>' . $content . '</main>
</body>
</html>';
}

function sendHtml(string $content, string $area = 'erp'): void
{
    header('Content-Type: text/html; charset=utf-8');
    echo page($content, $area);
}

function redirectTo(string $path): void
{
    header('Location: ' . $path, true, 303);
}

function derivedWebstoreEndpoint(string $supplierWebstoreUrl, string $path): string
{
    $supplierWebstoreUrl = rtrim(trim($supplierWebstoreUrl), '/');
    if ($supplierWebstoreUrl === '') {
        return '';
    }

    return $supplierWebstoreUrl . $path;
}

function effectiveWebstoreConfig(array $webstoreConfig): array
{
    $supplierWebstoreUrl = trim((string) ($webstoreConfig['supplier_webstore_url'] ?? ''));
    $setupUrl = trim((string) ($webstoreConfig['punchout_setup_url'] ?? ''));
    $orderUrl = trim((string) ($webstoreConfig['order_request_url'] ?? ''));

    return [
        'supplier_webstore_url' => $supplierWebstoreUrl,
        'punchout_setup_url' => $setupUrl ?: derivedWebstoreEndpoint($supplierWebstoreUrl, '/cxml/punchout/setup'),
        'order_request_url' => $orderUrl ?: derivedWebstoreEndpoint($supplierWebstoreUrl, '/cxml/order'),
        'request_timeout_seconds' => max(1, (int) ($webstoreConfig['request_timeout_seconds'] ?? 15)),
    ];
}

function postCxml(string $url, string $xml, int $timeoutSeconds): array
{
    if ($url === '') {
        return [
            'ok' => false,
            'status' => 0,
            'body' => '',
            'error' => 'No webstore endpoint is configured.',
        ];
    }

    $context = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => "Content-Type: text/xml; charset=utf-8\r\nAccept: text/xml, application/xml, */*\r\n",
            'content' => $xml,
            'ignore_errors' => true,
            'timeout' => max(1, $timeoutSeconds),
        ],
    ]);

    $body = @file_get_contents($url, false, $context);
    if ($body === false) {
        $error = error_get_last();

        return [
            'ok' => false,
            'status' => 0,
            'body' => '',
            'error' => $error['message'] ?? 'The webstore request failed.',
        ];
    }

    $status = 0;
    foreach ($http_response_header ?? [] as $header) {
        if (preg_match('#^HTTP/\S+\s+(\d{3})#', $header, $matches)) {
            $status = (int) $matches[1];
            break;
        }
    }

    return [
        'ok' => $status >= 200 && $status < 300,
        'status' => $status,
        'body' => $body,
        'error' => '',
    ];
}

function setupRequestXml(array $product, array $erpConfig): string
{
    $payloadId = time() . '.' . uuid() . '@mock-procurement';
    $buyerCookie = 'BUYER-' . uuid();
    $returnUrl = $erpConfig['browser_form_post_url'];
    $username = configValue($erpConfig, 'username', configValue($erpConfig, 'user_email'));
    $fullName = configValue($erpConfig, 'user_name');
    $firstName = namePart($erpConfig, 'first');
    $lastName = namePart($erpConfig, 'last');

    return '<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE cXML SYSTEM "http://xml.cxml.org/schemas/cXML/1.2.041/cXML.dtd">
<cXML payloadID="' . x($payloadId) . '" timestamp="' . x(date(DATE_ATOM)) . '">
  <Header>
    <From>
      <Credential domain="' . x(configValue($erpConfig, 'from_domain', 'NetworkId')) . '">
        <Identity>' . x($erpConfig['buyer_identity']) . '</Identity>
      </Credential>
    </From>
    <To>
      <Credential domain="' . x(configValue($erpConfig, 'to_domain', 'DUNS')) . '">
        <Identity>' . x($erpConfig['supplier_identity']) . '</Identity>
      </Credential>
    </To>
    <Sender>
      <Credential domain="' . x(configValue($erpConfig, 'sender_domain', 'NetworkId')) . '">
        <Identity>' . x($erpConfig['sender_identity']) . '</Identity>
        <SharedSecret>' . x($erpConfig['shared_secret']) . '</SharedSecret>
      </Credential>
      <UserAgent>' . x(configValue($erpConfig, 'user_agent', 'PunchOut ERP Test Harness')) . '</UserAgent>
    </Sender>
  </Header>
  <Request deploymentMode="' . x(configValue($erpConfig, 'deployment_mode', 'production')) . '">
    <PunchOutSetupRequest operation="create">
      <BuyerCookie>' . x($buyerCookie) . '</BuyerCookie>
      <Extrinsic name="User">' . x($username) . '</Extrinsic>
      <Extrinsic name="UniqueUsername">' . x($username) . '</Extrinsic>
      <Extrinsic name="UserId">' . x(configValue($erpConfig, 'user_id')) . '</Extrinsic>
      <Extrinsic name="UserEmail">' . x(configValue($erpConfig, 'user_email')) . '</Extrinsic>
      <Extrinsic name="UserFullName">' . x($fullName) . '</Extrinsic>
      <Extrinsic name="UserPrintableName">' . x($fullName) . '</Extrinsic>
      <Extrinsic name="FirstName">' . x($firstName) . '</Extrinsic>
      <Extrinsic name="LastName">' . x($lastName) . '</Extrinsic>
      <Extrinsic name="PhoneNumber">' . x(configValue($erpConfig, 'user_phone')) . '</Extrinsic>
      <BrowserFormPost>
        <URL>' . x($returnUrl) . '</URL>
      </BrowserFormPost>
      <SupplierSetup>
        <URL>' . x(configValue($erpConfig, 'supplier_setup_url')) . '</URL>
      </SupplierSetup>
      <ShipTo>
        <Address addressID="' . x(configValue($erpConfig, 'ship_to_address_id', 'TEST')) . '">
          <Name xml:lang="en">' . x(configValue($erpConfig, 'ship_to_name', 'TEST')) . '</Name>
          <PostalAddress>
            <Street>' . x(configValue($erpConfig, 'ship_to_street')) . '</Street>
            <City>' . x(configValue($erpConfig, 'ship_to_city')) . '</City>
            <State>' . x(configValue($erpConfig, 'ship_to_state')) . '</State>
            <PostalCode>' . x(configValue($erpConfig, 'ship_to_postal_code')) . '</PostalCode>
            <Country isoCountryCode="' . x(configValue($erpConfig, 'ship_to_country_code', 'US')) . '">' . x(configValue($erpConfig, 'ship_to_country', 'US')) . '</Country>
          </PostalAddress>
        </Address>
      </ShipTo>
      <Contact role="endUser">
        <Name xml:lang="en">' . x($fullName) . '</Name>
        <Email>' . x(configValue($erpConfig, 'user_email')) . '</Email>
      </Contact>
      <SelectedItem>
        <ItemID>
          <SupplierPartID>' . x($product['sku']) . '</SupplierPartID>
        </ItemID>
      </SelectedItem>
    </PunchOutSetupRequest>
  </Request>
</cXML>';
}

function parseStartUrl(string $responseXml): string
{
    return firstXmlValue($responseXml, '//PunchOutSetupResponse/StartPage/URL');
}

function setupResponseXml(string $incomingCxml, string $startPageUrl): string
{
    $buyerCookie = firstXmlValue($incomingCxml, '//PunchOutSetupRequest/BuyerCookie');
    $payloadId = time() . '.' . uuid() . '@mock-webstore';

    return '<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE cXML SYSTEM "http://xml.cxml.org/schemas/cXML/1.2.041/cXML.dtd">
<cXML payloadID="' . x($payloadId) . '" timestamp="' . x(date(DATE_ATOM)) . '">
  <Response>
    <Status code="200" text="OK">Success</Status>
    <PunchOutSetupResponse>
      <BuyerCookie>' . x($buyerCookie) . '</BuyerCookie>
      <StartPage>
        <URL>' . x($startPageUrl) . '</URL>
      </StartPage>
    </PunchOutSetupResponse>
  </Response>
</cXML>';
}



function punchoutOrderLines(string $cxml): array
{
    $doc = loadXml($cxml);
    if (!$doc) {
        return [];
    }

    $lines = [];
    foreach ($doc->xpath('//PunchOutOrderMessage/ItemIn') ?: [] as $item) {
        $lines[] = [
            'quantity' => (string) ($item['quantity'] ?? '1'),
            'supplier_part_id' => trim((string) ($item->ItemID->SupplierPartID ?? '')),
            'description' => trim((string) ($item->ItemDetail->Description ?? '')),
            'unit_price' => trim((string) ($item->ItemDetail->UnitPrice->Money ?? '0.00')),
            'currency' => isset($item->ItemDetail->UnitPrice->Money['currency'])
                ? trim((string) $item->ItemDetail->UnitPrice->Money['currency'])
                : 'GBP',
            'uom' => trim((string) ($item->ItemDetail->UnitOfMeasure ?? 'EA')),
            'classification' => trim((string) ($item->ItemDetail->Classification ?? '')),
        ];
    }

    return $lines;
}

function orderRequestXml(array $returnedOrder, array $erpConfig): string
{
    $orderId = 'PO-' . date('Ymd-His') . '-' . substr($returnedOrder['id'], 0, 8);
    $buyerCookie = firstXmlValue($returnedOrder['cxml'], '//PunchOutOrderMessage/BuyerCookie');
    $total = firstXmlValue($returnedOrder['cxml'], '//PunchOutOrderMessageHeader/Total/Money') ?: '0.00';
    $currency = 'GBP';
    $doc = loadXml($returnedOrder['cxml']);
    if ($doc) {
        $money = $doc->xpath('//PunchOutOrderMessageHeader/Total/Money');
        if ($money && isset($money[0]['currency'])) {
            $currency = (string) $money[0]['currency'];
        }
    }

    $items = '';
    foreach (punchoutOrderLines($returnedOrder['cxml']) as $index => $line) {
        $lineNumber = $index + 1;
        $items .= '    <ItemOut quantity="' . x($line['quantity']) . '" lineNumber="' . $lineNumber . '">
      <ItemID>
        <SupplierPartID>' . x($line['supplier_part_id']) . '</SupplierPartID>
      </ItemID>
      <ItemDetail>
        <UnitPrice>
          <Money currency="' . x($line['currency']) . '">' . x($line['unit_price']) . '</Money>
        </UnitPrice>
        <Description xml:lang="en">' . x($line['description']) . '</Description>
        <UnitOfMeasure>' . x($line['uom']) . '</UnitOfMeasure>
        <Classification domain="UNSPSC">' . x($line['classification']) . '</Classification>
      </ItemDetail>
    </ItemOut>
';
    }

    return '<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE cXML SYSTEM "http://xml.cxml.org/schemas/cXML/1.2.064/cXML.dtd">
<cXML payloadID="' . x(uuid() . '@mock-procurement') . '" timestamp="' . x(date(DATE_ATOM)) . '">
  <Header>
    <From>
      <Credential domain="' . x(configValue($erpConfig, 'from_domain', 'NetworkId')) . '">
        <Identity>' . x($erpConfig['buyer_identity']) . '</Identity>
      </Credential>
    </From>
    <To>
      <Credential domain="' . x(configValue($erpConfig, 'to_domain', 'DUNS')) . '">
        <Identity>' . x($erpConfig['supplier_identity']) . '</Identity>
      </Credential>
    </To>
    <Sender>
      <Credential domain="' . x(configValue($erpConfig, 'sender_domain', 'NetworkId')) . '">
        <Identity>' . x($erpConfig['sender_identity']) . '</Identity>
        <SharedSecret>' . x($erpConfig['shared_secret']) . '</SharedSecret>
      </Credential>
      <UserAgent>Mock ERP Order Sender</UserAgent>
    </Sender>
  </Header>
  <Request deploymentMode="test">
    <OrderRequest>
      <OrderRequestHeader orderID="' . x($orderId) . '" orderDate="' . x(date(DATE_ATOM)) . '" type="new">
        <Total>
          <Money currency="' . x($currency) . '">' . x($total) . '</Money>
        </Total>
        <ShipTo>
          <Address isoCountryCode="GB" addressID="MAIN-WAREHOUSE">
            <Name xml:lang="en">Demo Procurement Co</Name>
            <PostalAddress>
              <Street>1 Procurement Way</Street>
              <City>Manchester</City>
              <PostalCode>M1 1AA</PostalCode>
              <Country isoCountryCode="GB">United Kingdom</Country>
            </PostalAddress>
          </Address>
        </ShipTo>
        <Extrinsic name="BuyerCookie">' . x($buyerCookie) . '</Extrinsic>
      </OrderRequestHeader>
' . $items . '    </OrderRequest>
  </Request>
</cXML>';
}

function procurementHome(array $products, array $state): string
{
    $cards = '';
    foreach ($products as $product) {
        $cards .= '
    <div class="card">
      <div class="swatch" style="background:' . h($product['accent']) . '"></div>
      <div>
        <h3>' . h($product['name']) . '</h3>
        <div class="meta">' . h($product['category']) . ' &middot; ' . h($product['sku']) . '</div>
      </div>
      <p>' . h($product['description']) . '</p>
      <div class="price">' . h($product['currency']) . ' ' . money($product['price']) . '</div>
      <form method="post" action="/procurement/punchout">
        <input type="hidden" name="sku" value="' . h($product['sku']) . '">
        <button type="submit">PunchOut to item</button>
      </form>
    </div>';
    }

    $latest = $state['returned_orders'][0] ?? null;
    $approveAction = $latest && ($latest['status'] ?? 'pending') === 'pending'
        ? '<form method="post" action="/procurement/orders/' . h($latest['id']) . '/approve">
            <button type="submit" class="ok">Approve and send PO</button>
          </form>'
        : '';
    $orderStatus = $latest ? '<div class="meta">Status: ' . h($latest['status'] ?? 'pending') . '</div>' : '';
    $returned = $latest
        ? '<div class="callout"><strong>Last return:</strong> ' . h($latest['created_at']) . '</div>' . $orderStatus . $approveAction . '<pre>' . h($latest['cxml']) . '</pre>'
        : '<div class="empty">No PunchOutOrderMessage has been returned yet.</div>';

    return '
    <div class="grid">
      <section class="stack">
        <div>
          <h1>Procurement search results</h1>
          <p>Clicking a result sends a cXML PunchOutSetupRequest to the configured webstore and opens the returned StartPage URL.</p>
          <a class="button secondary" href="/admin">PunchOut setup admin</a>
        </div>
        <div class="products">' . $cards . '</div>
      </section>
      <aside class="panel stack">
        <h2>Returned basket</h2>
        ' . $returned . '
      </aside>
    </div>';
}

function adminPage(array $erpConfig, array $webstoreConfig, string $message = ''): string
{
    $status = $message ? '<div class="callout status-ok"><strong>Saved:</strong> ' . h($message) . '</div>' : '';
    $effectiveWebstoreConfig = effectiveWebstoreConfig($webstoreConfig);
    $supplierWebstoreUrl = (string) ($webstoreConfig['supplier_webstore_url'] ?? '');
    $setupUrl = (string) ($webstoreConfig['punchout_setup_url'] ?? '');
    $orderUrl = (string) ($webstoreConfig['order_request_url'] ?? '');
    $effectiveSetupUrl = (string) ($effectiveWebstoreConfig['punchout_setup_url'] ?? '');
    $effectiveOrderUrl = (string) ($effectiveWebstoreConfig['order_request_url'] ?? '');
    $timeout = (int) ($effectiveWebstoreConfig['request_timeout_seconds'] ?? 15);

    return '
    <div class="stack">
      <div>
        <h1>PunchOut setup admin</h1>
        <p>Configure the ERP identities and the real webstore endpoints used for PunchOut setup and approved purchase orders.</p>
        <a class="button secondary" href="/">Back to ERP search</a>
      </div>
      ' . $status . '
      <div class="admin-grid">
        <section class="panel stack erp-panel">
          <h2>ERP setup values</h2>
          <p>These are written into the outgoing PunchOutSetupRequest.</p>
          <form method="post" action="/admin/erp" class="form-grid">
            <label>From credential domain
              <input type="text" name="from_domain" value="' . h($erpConfig['from_domain'] ?? 'NetworkId') . '">
            </label>
            <label>From Identity / buyer
              <input type="text" name="buyer_identity" value="' . h($erpConfig['buyer_identity']) . '">
            </label>
            <label>To credential domain
              <input type="text" name="to_domain" value="' . h($erpConfig['to_domain'] ?? 'DUNS') . '">
            </label>
            <label>To Identity / supplier
              <input type="text" name="supplier_identity" value="' . h($erpConfig['supplier_identity']) . '">
            </label>
            <label>Sender credential domain
              <input type="text" name="sender_domain" value="' . h($erpConfig['sender_domain'] ?? 'NetworkId') . '">
            </label>
            <label>Sender Identity / ERP system
              <input type="text" name="sender_identity" value="' . h($erpConfig['sender_identity']) . '">
            </label>
            <label>Shared secret sent by ERP
              <input type="text" name="shared_secret" value="' . h($erpConfig['shared_secret']) . '">
            </label>
            <label>Deployment mode
              <input type="text" name="deployment_mode" value="' . h($erpConfig['deployment_mode'] ?? 'production') . '">
            </label>
            <label>UserAgent
              <input type="text" name="user_agent" value="' . h($erpConfig['user_agent'] ?? 'PunchOut ERP Test Harness') . '">
            </label>
            <label>Username / UniqueUsername
              <input type="text" name="username" value="' . h($erpConfig['username'] ?? '') . '">
            </label>
            <label>User ID
              <input type="text" name="user_id" value="' . h($erpConfig['user_id'] ?? '') . '">
            </label>
            <label>End user name
              <input type="text" name="user_name" value="' . h($erpConfig['user_name'] ?? '') . '">
            </label>
            <label>First name
              <input type="text" name="user_first_name" value="' . h($erpConfig['user_first_name'] ?? '') . '">
            </label>
            <label>Last name
              <input type="text" name="user_last_name" value="' . h($erpConfig['user_last_name'] ?? '') . '">
            </label>
            <label>End user email
              <input type="email" name="user_email" value="' . h($erpConfig['user_email'] ?? '') . '" placeholder="buyer@example.com">
            </label>
            <label>Phone number
              <input type="text" name="user_phone" value="' . h($erpConfig['user_phone'] ?? '') . '">
            </label>
            <label>BrowserFormPost return URL
              <input type="url" name="browser_form_post_url" value="' . h($erpConfig['browser_form_post_url']) . '">
            </label>
            <label>SupplierSetup URL
              <input type="url" name="supplier_setup_url" value="' . h($erpConfig['supplier_setup_url'] ?? '') . '">
            </label>
            <label>ShipTo address ID
              <input type="text" name="ship_to_address_id" value="' . h($erpConfig['ship_to_address_id'] ?? '') . '">
            </label>
            <label>ShipTo name
              <input type="text" name="ship_to_name" value="' . h($erpConfig['ship_to_name'] ?? '') . '">
            </label>
            <label>ShipTo street
              <input type="text" name="ship_to_street" value="' . h($erpConfig['ship_to_street'] ?? '') . '">
            </label>
            <label>ShipTo city
              <input type="text" name="ship_to_city" value="' . h($erpConfig['ship_to_city'] ?? '') . '">
            </label>
            <label>ShipTo state
              <input type="text" name="ship_to_state" value="' . h($erpConfig['ship_to_state'] ?? '') . '">
            </label>
            <label>ShipTo postal code
              <input type="text" name="ship_to_postal_code" value="' . h($erpConfig['ship_to_postal_code'] ?? '') . '">
            </label>
            <label>ShipTo country
              <input type="text" name="ship_to_country" value="' . h($erpConfig['ship_to_country'] ?? '') . '">
            </label>
            <label>ShipTo country code
              <input type="text" name="ship_to_country_code" value="' . h($erpConfig['ship_to_country_code'] ?? '') . '">
            </label>
            <button type="submit">Save ERP values</button>
          </form>
        </section>
        <section class="panel stack supplier-panel">
          <h2>Webstore endpoints</h2>
          <p>Set the supplier webstore base URL, or override the exact cXML endpoints if your routes differ.</p>
          <form method="post" action="/admin/webstore" class="form-grid">
            <label>Supplier webstore base URL
              <input type="url" name="supplier_webstore_url" value="' . h($supplierWebstoreUrl) . '" placeholder="https://store.example.test">
            </label>
            <label>PunchOut setup URL override
              <input type="url" name="punchout_setup_url" value="' . h($setupUrl) . '" placeholder="https://store.example.test/cxml/punchout/setup">
            </label>
            <label>OrderRequest URL override
              <input type="url" name="order_request_url" value="' . h($orderUrl) . '" placeholder="https://store.example.test/cxml/order">
            </label>
            <label>Request timeout seconds
              <input type="number" name="request_timeout_seconds" value="' . h((string) $timeout) . '" min="1">
            </label>
            <button type="submit" class="ok">Save webstore endpoints</button>
          </form>
        </section>
      </div>
      <section class="panel stack">
        <h2>ERP return endpoint</h2>
        <p>Set the BrowserFormPost URL to a public URL for this app when the real webstore is not running on the same machine. With ngrok, this is usually <code>https://your-ngrok-host/procurement/return</code>.</p>
        <table>
          <tbody>
            <tr><th>BrowserFormPost URL</th><td>' . h($erpConfig['browser_form_post_url']) . '</td></tr>
            <tr><th>End user</th><td>' . h(($erpConfig['user_name'] ?? '') . ' <' . ($erpConfig['user_email'] ?? '') . '>') . '</td></tr>
            <tr><th>Supplier webstore URL</th><td>' . ($supplierWebstoreUrl === '' ? 'Not configured' : h($supplierWebstoreUrl)) . '</td></tr>
            <tr><th>Effective PunchOut setup URL</th><td>' . ($effectiveSetupUrl === '' ? 'Not configured' : h($effectiveSetupUrl)) . '</td></tr>
            <tr><th>Effective OrderRequest URL</th><td>' . ($effectiveOrderUrl === '' ? 'Not configured' : h($effectiveOrderUrl)) . '</td></tr>
          </tbody>
        </table>
        <form method="post" action="/admin/reset">
          <button type="submit" class="secondary">Reset ERP defaults</button>
        </form>
      </section>
    </div>';
}

function setupExchangePage(string $setupXml, array $result): string
{
    $responseXml = (string) ($result['body'] ?? '');
    $startUrl = parseStartUrl($responseXml);
    $statusClass = ($result['ok'] ?? false) && $startUrl !== '' ? 'status-ok' : 'status-error';
    $httpStatus = (int) ($result['status'] ?? 0);
    $error = (string) ($result['error'] ?? '');
    $statusText = (($result['ok'] ?? false) && $startUrl !== '')
        ? 'The webstore accepted the setup request and returned a StartPage URL.'
        : 'The webstore did not return a usable PunchOutSetupResponse.';
    $detail = $httpStatus ? 'HTTP ' . $httpStatus : ($error ?: 'No HTTP response');
    $primaryAction = (($result['ok'] ?? false) && $startUrl !== '')
        ? '<a class="button" href="' . h($startUrl) . '">Open webstore StartPage</a>'
        : '<a class="button" href="/admin">Fix endpoint settings</a>';

    return '
    <div class="grid">
      <section class="stack">
        <h1>PunchOut setup exchanged</h1>
        <p>The ERP generated cXML and posted it to the configured webstore PunchOut setup URL.</p>
        <div class="callout ' . $statusClass . '"><strong>Setup result:</strong> ' . h($statusText) . '<br><span class="meta">' . h($detail) . '</span></div>
        ' . $primaryAction . '
        <a class="button secondary" href="/">Cancel</a>
      </section>
      <aside class="stack">
        <div class="panel stack">
          <h2>Request</h2>
          <pre>' . h($setupXml) . '</pre>
        </div>
        <div class="panel stack">
          <h2>Response</h2>
          <pre>' . h($responseXml) . '</pre>
        </div>
      </aside>
    </div>';
}

function returnedOrderPage(array $returnedOrder): string
{
    $approveAction = ($returnedOrder['status'] ?? 'pending') === 'pending'
        ? '<form method="post" action="/procurement/orders/' . h($returnedOrder['id']) . '/approve">
            <button type="submit" class="ok">Approve and send PO to webstore</button>
          </form>'
        : '<div class="callout status-ok"><strong>Order status:</strong> ' . h($returnedOrder['status'] ?? '') . '</div>';

    return '
    <div class="grid">
      <section class="stack">
        <h1>Basket returned</h1>
        <p>The ERP received the PunchOutOrderMessage. In a real flow this is now a requisition waiting for buyer approval.</p>
        <div class="callout"><strong>Validation:</strong> BuyerCookie, item lines, quantities, unit prices, currency, UOM, and classifications are visible in the returned cXML.</div>
        ' . $approveAction . '
        <a class="button secondary" href="/">Back to procurement search</a>
      </section>
      <aside class="panel stack">
        <h2>Received cXML</h2>
        <div class="meta">Status: ' . h($returnedOrder['status'] ?? 'pending') . '</div>
        <pre>' . h($returnedOrder['cxml']) . '</pre>
      </aside>
    </div>';
}

function orderApprovalPage(array $returnedOrder): string
{
    $ok = ($returnedOrder['supplier_response_ok'] ?? false) === true;
    $statusClass = $ok ? 'status-ok' : 'status-error';
    $statusText = $ok
        ? 'The webstore accepted the approved OrderRequest.'
        : 'The webstore rejected the OrderRequest or did not respond successfully.';
    $httpStatus = (int) ($returnedOrder['supplier_response_status'] ?? 0);
    $detail = $httpStatus ? 'HTTP ' . $httpStatus : ((string) ($returnedOrder['supplier_response_error'] ?? 'No HTTP response'));

    return '
    <div class="grid">
      <section class="stack">
        <h1>Order approved</h1>
        <p>The ERP converted the returned PunchOut basket into an approved purchase order and sent a cXML OrderRequest to the configured webstore endpoint.</p>
        <div class="callout ' . $statusClass . '"><strong>Webstore response:</strong> ' . h($statusText) . '<br><span class="meta">' . h($detail) . '</span></div>
        <a class="button secondary" href="/">Back to ERP search</a>
      </section>
      <aside class="stack">
        <div class="panel stack">
          <h2>OrderRequest sent</h2>
          <pre>' . h($returnedOrder['order_request_cxml'] ?? '') . '</pre>
        </div>
        <div class="panel stack">
          <h2>Webstore response</h2>
          <pre>' . h($returnedOrder['supplier_response_cxml'] ?? '') . '</pre>
        </div>
      </aside>
    </div>';
}

function supplierHome(array $products): string
{
    $cards = '';
    foreach ($products as $product) {
        $cards .= '
    <div class="card">
      <div class="swatch" style="background:' . h($product['accent']) . '"></div>
      <div>
        <h3><a href="/supplier/product/' . h(urlencode($product['sku'])) . '">' . h($product['name']) . '</a></h3>
        <div class="meta">' . h($product['category']) . ' &middot; ' . h($product['sku']) . '</div>
      </div>
      <p>' . h($product['description']) . '</p>
      <div class="price">' . h($product['currency']) . ' ' . money($product['price']) . '</div>
    </div>';
    }

    return '
    <div>
      <h1>Supplier shop</h1>
      <p>Browse products below.</p>
      <div class="products">' . $cards . '</div>
    </div>';
}

function supplierProductPage(array $product): string
{
    return '
    <div>
      <h1>' . h($product['name']) . '</h1>
      <div class="swatch" style="background:' . h($product['accent']) . '"></div>
      <p>' . h($product['description']) . '</p>
      <div class="meta">' . h($product['category']) . ' &middot; ' . h($product['sku']) . '</div>
      <div class="price">' . h($product['currency']) . ' ' . money($product['price']) . '</div>
      <p><strong>SKU:</strong> ' . h($product['sku']) . '</p>
      <a class="button secondary" href="/supplier">Back to supplier shop</a>
    </div>';
}

$method = $_SERVER['REQUEST_METHOD'];
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/';

if ($method === 'GET' && $path === '/') {
    sendHtml(procurementHome($products, $state));
    return;
}

if ($method === 'GET' && $path === '/admin') {
    sendHtml(adminPage($state['erp_config'], $state['webstore_config'], (string) ($_GET['saved'] ?? '')));
    return;
}

if ($method === 'POST' && $path === '/admin/erp') {
    $state['erp_config'] = [
        'from_domain' => trim((string) ($_POST['from_domain'] ?? 'NetworkId')),
        'buyer_identity' => trim((string) ($_POST['buyer_identity'] ?? '')),
        'to_domain' => trim((string) ($_POST['to_domain'] ?? 'DUNS')),
        'supplier_identity' => trim((string) ($_POST['supplier_identity'] ?? '')),
        'sender_domain' => trim((string) ($_POST['sender_domain'] ?? 'NetworkId')),
        'sender_identity' => trim((string) ($_POST['sender_identity'] ?? '')),
        'shared_secret' => trim((string) ($_POST['shared_secret'] ?? '')),
        'deployment_mode' => trim((string) ($_POST['deployment_mode'] ?? 'production')) ?: 'production',
        'user_agent' => trim((string) ($_POST['user_agent'] ?? 'PunchOut ERP Test Harness')) ?: 'PunchOut ERP Test Harness',
        'username' => trim((string) ($_POST['username'] ?? '')),
        'user_id' => trim((string) ($_POST['user_id'] ?? '')),
        'user_name' => trim((string) ($_POST['user_name'] ?? '')),
        'user_first_name' => trim((string) ($_POST['user_first_name'] ?? '')),
        'user_last_name' => trim((string) ($_POST['user_last_name'] ?? '')),
        'user_email' => trim((string) ($_POST['user_email'] ?? '')),
        'user_phone' => trim((string) ($_POST['user_phone'] ?? '')),
        'browser_form_post_url' => trim((string) ($_POST['browser_form_post_url'] ?? '')) ?: $baseUrl . '/procurement/return',
        'supplier_setup_url' => trim((string) ($_POST['supplier_setup_url'] ?? '')),
        'ship_to_address_id' => trim((string) ($_POST['ship_to_address_id'] ?? '')),
        'ship_to_name' => trim((string) ($_POST['ship_to_name'] ?? '')),
        'ship_to_street' => trim((string) ($_POST['ship_to_street'] ?? '')),
        'ship_to_city' => trim((string) ($_POST['ship_to_city'] ?? '')),
        'ship_to_state' => trim((string) ($_POST['ship_to_state'] ?? '')),
        'ship_to_postal_code' => trim((string) ($_POST['ship_to_postal_code'] ?? '')),
        'ship_to_country' => trim((string) ($_POST['ship_to_country'] ?? '')),
        'ship_to_country_code' => trim((string) ($_POST['ship_to_country_code'] ?? '')),
    ];
    saveState($statePath, $state);
    redirectTo('/admin?saved=ERP setup values updated');
    return;
}

if ($method === 'POST' && $path === '/admin/webstore') {
    $state['webstore_config'] = [
        'supplier_webstore_url' => trim((string) ($_POST['supplier_webstore_url'] ?? '')),
        'punchout_setup_url' => trim((string) ($_POST['punchout_setup_url'] ?? '')),
        'order_request_url' => trim((string) ($_POST['order_request_url'] ?? '')),
        'request_timeout_seconds' => max(1, (int) ($_POST['request_timeout_seconds'] ?? 15)),
    ];
    saveState($statePath, $state);
    redirectTo('/admin?saved=Webstore endpoints updated');
    return;
}

if ($method === 'POST' && $path === '/admin/reset') {
    $state['erp_config'] = [
        'from_domain' => 'NetworkId',
        'buyer_identity' => 'DEMO_BUYER',
        'to_domain' => 'DUNS',
        'supplier_identity' => 'REAL_WEBSTORE',
        'sender_domain' => 'NetworkId',
        'sender_identity' => 'DEMO_PROCUREMENT_SYSTEM',
        'shared_secret' => 'topsecret',
        'deployment_mode' => 'production',
        'user_agent' => 'PunchOut ERP Test Harness',
        'username' => 'jdoe12345',
        'user_id' => '12345',
        'user_name' => 'Jamie Buyer',
        'user_first_name' => 'Jamie',
        'user_last_name' => 'Buyer',
        'user_email' => 'buyer@example.test',
        'user_phone' => '555-555-5555',
        'browser_form_post_url' => $baseUrl . '/procurement/return',
        'supplier_setup_url' => '',
        'ship_to_address_id' => 'TEST',
        'ship_to_name' => 'TEST',
        'ship_to_street' => '123 Street Address',
        'ship_to_city' => 'Rockville',
        'ship_to_state' => 'MD',
        'ship_to_postal_code' => '20850',
        'ship_to_country' => 'US',
        'ship_to_country_code' => 'US',
    ];
    $state['webstore_config'] = [
        'supplier_webstore_url' => getenv('SUPPLIER_WEBSTORE_URL') ?: '',
        'punchout_setup_url' => getenv('WEBSTORE_PUNCHOUT_SETUP_URL') ?: '',
        'order_request_url' => getenv('WEBSTORE_ORDER_REQUEST_URL') ?: '',
        'request_timeout_seconds' => 15,
    ];
    $state['returned_orders'] = [];
    saveState($statePath, $state);
    redirectTo('/admin?saved=ERP defaults reset');
    return;
}

if ($method === 'POST' && $path === '/procurement/punchout') {
    $product = findProduct($products, (string) ($_POST['sku'] ?? 'SKU-001'));
    $setupXml = setupRequestXml($product, $state['erp_config']);
    $webstoreConfig = effectiveWebstoreConfig($state['webstore_config']);
    $result = postCxml(
        (string) ($webstoreConfig['punchout_setup_url'] ?? ''),
        $setupXml,
        (int) ($webstoreConfig['request_timeout_seconds'] ?? 15)
    );
    sendHtml(setupExchangePage($setupXml, $result));
    return;
}

if ($method === 'POST' && $path === '/procurement/return') {
    $cxml = (string) ($_POST['cXML-urlencoded'] ?? '');
    $returnedOrder = [
        'id' => uuid(),
        'cxml' => $cxml,
        'status' => 'pending',
        'created_at' => date('d/m/Y H:i:s'),
    ];
    array_unshift($state['returned_orders'], $returnedOrder);
    saveState($statePath, $state);
    sendHtml(returnedOrderPage($returnedOrder));
    return;
}

if ($method === 'POST' && preg_match('#^/procurement/orders/([a-f0-9-]+)/approve$#', $path, $matches)) {
    foreach ($state['returned_orders'] as $index => $returnedOrder) {
        if ($returnedOrder['id'] !== $matches[1]) {
            continue;
        }

        $orderRequestXml = orderRequestXml($returnedOrder, $state['erp_config']);
        $webstoreConfig = effectiveWebstoreConfig($state['webstore_config']);
        $supplierResponse = postCxml(
            (string) ($webstoreConfig['order_request_url'] ?? ''),
            $orderRequestXml,
            (int) ($webstoreConfig['request_timeout_seconds'] ?? 15)
        );
        $state['returned_orders'][$index]['status'] = $supplierResponse['ok'] ? 'approved and sent' : 'approval send failed';
        $state['returned_orders'][$index]['order_request_cxml'] = $orderRequestXml;
        $state['returned_orders'][$index]['supplier_response_cxml'] = $supplierResponse['body'];
        $state['returned_orders'][$index]['supplier_response_ok'] = $supplierResponse['ok'];
        $state['returned_orders'][$index]['supplier_response_status'] = $supplierResponse['status'];
        $state['returned_orders'][$index]['supplier_response_error'] = $supplierResponse['error'];
        saveState($statePath, $state);

        sendHtml(orderApprovalPage($state['returned_orders'][$index]));
        return;
    }

    http_response_code(404);
    echo 'Returned order not found';
    return;
}

if ($method === 'GET' && $path === '/supplier') {
    sendHtml(supplierHome($products), 'supplier');
    return;
}

if ($method === 'GET' && preg_match('#^/supplier/product/(.+)$#', $path, $matches)) {
    $sku = urldecode($matches[1]);
    $product = findProduct($products, $sku);
    sendHtml(supplierProductPage($product), 'supplier');
    return;
}

if ($method === 'POST' && $path === '/cxml/punchout/setup') {
    $body = (string) file_get_contents('php://input');
    $supplierPartId = firstXmlValue($body, '//PunchOutSetupRequest/SelectedItem/ItemID/SupplierPartID');
    $startPageUrl = $supplierPartId !== ''
        ? $baseUrl . '/supplier/product/' . urlencode($supplierPartId)
        : $baseUrl . '/supplier';
    header('Content-Type: text/xml; charset=utf-8');
    echo setupResponseXml($body, $startPageUrl);
    return;
}

http_response_code(404);
echo 'Not found';
