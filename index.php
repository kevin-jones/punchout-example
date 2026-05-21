<?php

declare(strict_types=1);

session_start();

$baseUrl = getenv('BASE_URL') ?: 'http://localhost:8000';

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

$_SESSION['punchout_sessions'] ??= [];
$_SESSION['returned_orders'] ??= [];
$_SESSION['erp_config'] ??= [
    'buyer_identity' => 'DEMO_BUYER',
    'supplier_identity' => 'MOCK_SUPPLIER',
    'sender_identity' => 'DEMO_PROCUREMENT_SYSTEM',
    'shared_secret' => 'topsecret',
    'browser_form_post_url' => $baseUrl . '/procurement/return',
];
$_SESSION['supplier_config'] ??= [
    'expected_buyer_identity' => 'DEMO_BUYER',
    'supplier_identity' => 'MOCK_SUPPLIER',
    'expected_sender_identity' => 'DEMO_PROCUREMENT_SYSTEM',
    'shared_secret' => 'topsecret',
];

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
    input[type="number"], input[type="text"], input[type="url"], input[type="password"] {
      width: 82px;
      min-height: 38px;
      border: 1px solid var(--line);
      border-radius: 6px;
      padding: 8px;
      font: inherit;
      background: #fff;
    }
    input[type="text"], input[type="url"], input[type="password"] {
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

function sendXml(string $xml, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: text/xml; charset=utf-8');
    echo $xml;
}

function redirectTo(string $path): void
{
    header('Location: ' . $path, true, 303);
}

function setupRequestXml(array $product, array $erpConfig): string
{
    $payloadId = time() . '.' . uuid() . '@mock-procurement';
    $buyerCookie = 'BUYER-' . uuid();
    $returnUrl = $erpConfig['browser_form_post_url'];

    return '<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE cXML SYSTEM "http://xml.cxml.org/schemas/cXML/1.2.064/cXML.dtd">
<cXML payloadID="' . x($payloadId) . '" timestamp="' . x(date(DATE_ATOM)) . '">
  <Header>
    <From>
      <Credential domain="NetworkId">
        <Identity>' . x($erpConfig['buyer_identity']) . '</Identity>
      </Credential>
    </From>
    <To>
      <Credential domain="NetworkId">
        <Identity>' . x($erpConfig['supplier_identity']) . '</Identity>
      </Credential>
    </To>
    <Sender>
      <Credential domain="NetworkId">
        <Identity>' . x($erpConfig['sender_identity']) . '</Identity>
        <SharedSecret>' . x($erpConfig['shared_secret']) . '</SharedSecret>
      </Credential>
      <UserAgent>Mock Procurement Harness</UserAgent>
    </Sender>
  </Header>
  <Request deploymentMode="test">
    <PunchOutSetupRequest operation="create">
      <BuyerCookie>' . x($buyerCookie) . '</BuyerCookie>
      <BrowserFormPost>
        <URL>' . x($returnUrl) . '</URL>
      </BrowserFormPost>
      <Contact role="endUser">
        <Name xml:lang="en">Jamie Buyer</Name>
        <Email>buyer@example.test</Email>
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

function setupResponseXml(string $sessionId, string $baseUrl): string
{
    return '<?xml version="1.0" encoding="UTF-8"?>
<cXML payloadID="' . x(uuid() . '@mock-supplier') . '" timestamp="' . x(date(DATE_ATOM)) . '">
  <Response>
    <Status code="200" text="OK">PunchOut session created</Status>
    <PunchOutSetupResponse>
      <StartPage>
        <URL>' . x($baseUrl . '/supplier/start/' . $sessionId) . '</URL>
      </StartPage>
    </PunchOutSetupResponse>
  </Response>
</cXML>';
}

function createPunchoutSessionFromXml(string $xml, string $baseUrl, array $supplierConfig): array
{
    $buyerIdentity = firstXmlValue($xml, '//Header/From/Credential/Identity');
    $supplierIdentity = firstXmlValue($xml, '//Header/To/Credential/Identity');
    $senderIdentity = firstXmlValue($xml, '//Sender/Credential/Identity');
    $sharedSecret = firstXmlValue($xml, '//Sender/Credential/SharedSecret');
    $failures = [];

    if ($buyerIdentity !== $supplierConfig['expected_buyer_identity']) {
        $failures[] = 'From Identity does not match the supplier expected buyer identity.';
    }
    if ($supplierIdentity !== $supplierConfig['supplier_identity']) {
        $failures[] = 'To Identity does not match the supplier identity.';
    }
    if ($senderIdentity !== $supplierConfig['expected_sender_identity']) {
        $failures[] = 'Sender Identity does not match the supplier expected sender identity.';
    }
    if ($sharedSecret !== $supplierConfig['shared_secret']) {
        $failures[] = 'SharedSecret does not match.';
    }

    if ($failures) {
        return [
            'ok' => false,
            'xml' => '<?xml version="1.0" encoding="UTF-8"?>
<cXML payloadID="' . x(uuid() . '@mock-supplier') . '" timestamp="' . x(date(DATE_ATOM)) . '">
  <Response>
    <Status code="401" text="Unauthorized">' . x(implode(' ', $failures)) . '</Status>
  </Response>
</cXML>',
            'status' => 401,
            'failures' => $failures,
        ];
    }

    $sessionId = uuid();
    $_SESSION['punchout_sessions'][$sessionId] = [
        'id' => $sessionId,
        'buyer_cookie' => firstXmlValue($xml, '//PunchOutSetupRequest/BuyerCookie'),
        'browser_form_post_url' => firstXmlValue($xml, '//PunchOutSetupRequest/BrowserFormPost/URL'),
        'buyer_identity' => $buyerIdentity ?: 'DEMO_BUYER',
        'selected_sku' => firstXmlValue($xml, '//PunchOutSetupRequest/SelectedItem/ItemID/SupplierPartID') ?: 'SKU-001',
        'basket' => [],
        'created_at' => date(DATE_ATOM),
    ];

    return [
        'ok' => true,
        'session_id' => $sessionId,
        'xml' => setupResponseXml($sessionId, $baseUrl),
        'status' => 200,
    ];
}

function parseStartUrl(string $responseXml): string
{
    return firstXmlValue($responseXml, '//PunchOutSetupResponse/StartPage/URL');
}

function basketTotal(array $session, array $products): float
{
    $total = 0.0;
    foreach ($session['basket'] as $line) {
        $product = findProduct($products, $line['sku']);
        $total += $product['price'] * $line['quantity'];
    }

    return $total;
}

function orderMessageXml(array $session, array $products): string
{
    $lines = '';
    foreach ($session['basket'] as $index => $line) {
        $product = findProduct($products, $line['sku']);
        $lineNumber = $index + 1;
        $lines .= '    <ItemIn quantity="' . x((string) $line['quantity']) . '">
      <ItemID>
        <SupplierPartID>' . x($product['sku']) . '</SupplierPartID>
      </ItemID>
      <ItemDetail>
        <UnitPrice>
          <Money currency="' . x($product['currency']) . '">' . money($product['price']) . '</Money>
        </UnitPrice>
        <Description xml:lang="en">' . x($product['name']) . '</Description>
        <UnitOfMeasure>' . x($product['uom']) . '</UnitOfMeasure>
        <Classification domain="UNSPSC">' . x($product['classification']) . '</Classification>
      </ItemDetail>
      <Distribution>
        <Accounting name="lineNumber">
          <AccountingSegment id="' . $lineNumber . '">
            <Name xml:lang="en">Line ' . $lineNumber . '</Name>
            <Description xml:lang="en">' . x($product['category']) . '</Description>
          </AccountingSegment>
        </Accounting>
      </Distribution>
    </ItemIn>
';
    }

    return '<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE cXML SYSTEM "http://xml.cxml.org/schemas/cXML/1.2.064/cXML.dtd">
<cXML payloadID="' . x(uuid() . '@mock-supplier') . '" timestamp="' . x(date(DATE_ATOM)) . '">
  <Message deploymentMode="test">
    <PunchOutOrderMessage>
      <BuyerCookie>' . x($session['buyer_cookie']) . '</BuyerCookie>
      <PunchOutOrderMessageHeader operationAllowed="edit">
        <Total>
          <Money currency="GBP">' . money(basketTotal($session, $products)) . '</Money>
        </Total>
      </PunchOutOrderMessageHeader>
' . $lines . '    </PunchOutOrderMessage>
  </Message>
</cXML>';
}

function procurementHome(array $products): string
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

    $latest = $_SESSION['returned_orders'][0] ?? null;
    $returned = $latest
        ? '<div class="callout"><strong>Last return:</strong> ' . h($latest['created_at']) . '</div><pre>' . h($latest['cxml']) . '</pre>'
        : '<div class="empty">No PunchOutOrderMessage has been returned yet.</div>';

    return '
    <div class="grid">
      <section class="stack">
        <div>
          <h1>Procurement search results</h1>
          <p>Clicking a result sends a mock cXML PunchOutSetupRequest and opens the supplier storefront on the selected Level 2 item.</p>
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

function adminPage(array $erpConfig, array $supplierConfig, string $message = ''): string
{
    $status = $message ? '<div class="callout status-ok"><strong>Saved:</strong> ' . h($message) . '</div>' : '';
    $matches = [
        'Buyer identity' => $erpConfig['buyer_identity'] === $supplierConfig['expected_buyer_identity'],
        'Supplier identity' => $erpConfig['supplier_identity'] === $supplierConfig['supplier_identity'],
        'Sender identity' => $erpConfig['sender_identity'] === $supplierConfig['expected_sender_identity'],
        'Shared secret' => $erpConfig['shared_secret'] === $supplierConfig['shared_secret'],
    ];
    $matchRows = '';
    foreach ($matches as $label => $ok) {
        $matchRows .= '<tr><td>' . h($label) . '</td><td>' . ($ok ? 'Matches' : 'Mismatch') . '</td></tr>';
    }

    return '
    <div class="stack">
      <div>
        <h1>PunchOut setup admin</h1>
        <p>Edit the values the ERP sends and the values the supplier storefront expects. The PunchOut setup step only succeeds when the identities and shared secret match.</p>
        <a class="button secondary" href="/">Back to ERP search</a>
      </div>
      ' . $status . '
      <div class="admin-grid">
        <section class="panel stack erp-panel">
          <h2>ERP setup values</h2>
          <p>These are written into the outgoing PunchOutSetupRequest.</p>
          <form method="post" action="/admin/erp" class="form-grid">
            <label>From Identity / buyer
              <input type="text" name="buyer_identity" value="' . h($erpConfig['buyer_identity']) . '">
            </label>
            <label>To Identity / supplier
              <input type="text" name="supplier_identity" value="' . h($erpConfig['supplier_identity']) . '">
            </label>
            <label>Sender Identity / ERP system
              <input type="text" name="sender_identity" value="' . h($erpConfig['sender_identity']) . '">
            </label>
            <label>Shared secret sent by ERP
              <input type="text" name="shared_secret" value="' . h($erpConfig['shared_secret']) . '">
            </label>
            <label>BrowserFormPost return URL
              <input type="url" name="browser_form_post_url" value="' . h($erpConfig['browser_form_post_url']) . '">
            </label>
            <button type="submit">Save ERP values</button>
          </form>
        </section>
        <section class="panel stack supplier-panel">
          <h2>Supplier expected values</h2>
          <p>These are checked before the supplier creates a PunchOut session.</p>
          <form method="post" action="/admin/supplier" class="form-grid">
            <label>Expected buyer identity
              <input type="text" name="expected_buyer_identity" value="' . h($supplierConfig['expected_buyer_identity']) . '">
            </label>
            <label>Supplier identity
              <input type="text" name="supplier_identity" value="' . h($supplierConfig['supplier_identity']) . '">
            </label>
            <label>Expected sender identity
              <input type="text" name="expected_sender_identity" value="' . h($supplierConfig['expected_sender_identity']) . '">
            </label>
            <label>Shared secret expected by supplier
              <input type="text" name="shared_secret" value="' . h($supplierConfig['shared_secret']) . '">
            </label>
            <button type="submit" class="ok">Save supplier values</button>
          </form>
        </section>
      </div>
      <section class="panel stack">
        <h2>Current compatibility check</h2>
        <table>
          <thead><tr><th>Field</th><th>Status</th></tr></thead>
          <tbody>' . $matchRows . '</tbody>
        </table>
        <form method="post" action="/admin/reset">
          <button type="submit" class="secondary">Reset demo credentials</button>
        </form>
      </section>
    </div>';
}

function setupExchangePage(string $setupXml, array $result): string
{
    $responseXml = $result['xml'];
    $startUrl = parseStartUrl($responseXml);
    $statusClass = $result['ok'] ? 'status-ok' : 'status-error';
    $statusText = $result['ok']
        ? 'Supplier accepted the setup request and created a StartPage URL.'
        : 'Supplier rejected the setup request. Check the admin values on both sides.';
    $primaryAction = $result['ok']
        ? '<a class="button" href="' . h($startUrl) . '">Open supplier StartPage</a>'
        : '<a class="button" href="/admin">Fix setup values</a>';

    return '
    <div class="grid">
      <section class="stack">
        <h1>PunchOut setup exchanged</h1>
        <p>The procurement harness generated cXML and the supplier setup handler validated it against the supplier-side setup values.</p>
        <div class="callout ' . $statusClass . '"><strong>Setup result:</strong> ' . h($statusText) . '</div>
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

function storefront(array $session, array $products): string
{
    $selected = findProduct($products, $session['selected_sku']);
    $productCards = '';
    foreach ($products as $product) {
        $defaultQty = $product['sku'] === $selected['sku'] ? 2 : 1;
        $productCards .= '
    <div class="card">
      <div class="swatch" style="background:' . h($product['accent']) . '"></div>
      <h3>' . h($product['name']) . '</h3>
      <div class="meta">' . h($product['category']) . ' &middot; ' . h($product['sku']) . ' &middot; ' . h($product['uom']) . '</div>
      <p>' . h($product['description']) . '</p>
      <div class="price">' . h($product['currency']) . ' ' . money($product['price']) . '</div>
      <form method="post" action="/supplier/session/' . h($session['id']) . '/basket/add" class="row">
        <input type="hidden" name="sku" value="' . h($product['sku']) . '">
        <label class="row">Qty <input type="number" name="quantity" value="' . $defaultQty . '" min="1"></label>
        <button type="submit">Add</button>
      </form>
    </div>';
    }

    $basket = '<div class="empty">Basket is empty.</div>';
    if ($session['basket']) {
        $rows = '';
        foreach ($session['basket'] as $line) {
            $product = findProduct($products, $line['sku']);
            $rows .= '<tr>
      <td>' . h($product['sku']) . '</td>
      <td>' . h($product['name']) . '</td>
      <td>' . h((string) $line['quantity']) . '</td>
      <td>' . h($product['currency']) . ' ' . money($product['price'] * $line['quantity']) . '</td>
    </tr>';
        }
        $basket = '
          <table>
            <thead><tr><th>SKU</th><th>Item</th><th>Qty</th><th>Total</th></tr></thead>
            <tbody>' . $rows . '</tbody>
          </table>
          <div class="total">GBP ' . money(basketTotal($session, $products)) . '</div>
          <form method="post" action="/supplier/session/' . h($session['id']) . '/return">
            <button type="submit" class="ok">Return basket</button>
          </form>';
    }

    return '
    <div class="grid">
      <section class="stack">
        <div class="callout"><strong>Auto signed in:</strong> Jamie Buyer landed on ' . h($selected['name']) . ' from the Level 2 SelectedItem.</div>
        <div>
          <h1>Supplier storefront</h1>
          <p>PunchOut mode is active. The buyer can build a basket here, then return it to procurement instead of checking out.</p>
          <a class="button secondary" href="/admin">PunchOut setup admin</a>
        </div>
        <div class="products">' . $productCards . '</div>
      </section>
      <aside class="panel stack">
        <h2>PunchOut basket</h2>
        <div class="meta">BuyerCookie: ' . h($session['buyer_cookie']) . '</div>
        ' . $basket . '
        <a class="button secondary" href="/">Back to procurement</a>
      </aside>
    </div>';
}

function autoPostPage(string $returnUrl, string $cxml): string
{
    return '<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Returning basket</title>
  <style>
    body { margin: 0; font-family: system-ui, sans-serif; background: #eef2f8; color: #172033; }
    header { background: #0f5f5c; color: #fff; border-bottom: 1px solid rgba(0,0,0,.24); }
    .bar { max-width: 1180px; margin: 0 auto; padding: 16px 20px; display: flex; align-items: center; justify-content: space-between; gap: 16px; }
    .brand { font-weight: 750; font-size: 18px; }
    .pill { border: 1px solid rgba(255,255,255,.24); border-radius: 999px; padding: 6px 10px; color: #d8f7f5; font-size: 13px; white-space: nowrap; }
    main { max-width: 720px; margin: 12vh auto; background: #fff; border: 1px solid #d9dee8; border-radius: 8px; padding: 24px; }
    button { min-height: 38px; border: 1px solid #066f50; border-radius: 6px; background: #087f5b; color: #fff; padding: 8px 12px; font: inherit; font-weight: 650; }
  </style>
</head>
<body>
  <header>
    <div class="bar">
      <div class="brand">Mock cXML PunchOut Level 2</div>
      <div class="pill">Supplier shop &middot; Buyer: Demo Procurement Co</div>
    </div>
  </header>
  <main>
    <h1>Returning basket to procurement</h1>
    <p>The supplier site is posting the PunchOutOrderMessage back to the original BrowserFormPost URL.</p>
    <form method="post" action="' . h($returnUrl) . '">
      <input type="hidden" name="cXML-urlencoded" value="' . h($cxml) . '">
      <button type="submit">Continue</button>
    </form>
  </main>
  <script>document.forms[0].submit();</script>
</body>
</html>';
}

$method = $_SERVER['REQUEST_METHOD'];
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/';

if ($method === 'GET' && $path === '/') {
    sendHtml(procurementHome($products));
    return;
}

if ($method === 'GET' && $path === '/admin') {
    sendHtml(adminPage($_SESSION['erp_config'], $_SESSION['supplier_config'], (string) ($_GET['saved'] ?? '')));
    return;
}

if ($method === 'POST' && $path === '/admin/erp') {
    $_SESSION['erp_config'] = [
        'buyer_identity' => trim((string) ($_POST['buyer_identity'] ?? '')),
        'supplier_identity' => trim((string) ($_POST['supplier_identity'] ?? '')),
        'sender_identity' => trim((string) ($_POST['sender_identity'] ?? '')),
        'shared_secret' => trim((string) ($_POST['shared_secret'] ?? '')),
        'browser_form_post_url' => trim((string) ($_POST['browser_form_post_url'] ?? '')) ?: $baseUrl . '/procurement/return',
    ];
    redirectTo('/admin?saved=ERP setup values updated');
    return;
}

if ($method === 'POST' && $path === '/admin/supplier') {
    $_SESSION['supplier_config'] = [
        'expected_buyer_identity' => trim((string) ($_POST['expected_buyer_identity'] ?? '')),
        'supplier_identity' => trim((string) ($_POST['supplier_identity'] ?? '')),
        'expected_sender_identity' => trim((string) ($_POST['expected_sender_identity'] ?? '')),
        'shared_secret' => trim((string) ($_POST['shared_secret'] ?? '')),
    ];
    redirectTo('/admin?saved=Supplier setup values updated');
    return;
}

if ($method === 'POST' && $path === '/admin/reset') {
    $_SESSION['erp_config'] = [
        'buyer_identity' => 'DEMO_BUYER',
        'supplier_identity' => 'MOCK_SUPPLIER',
        'sender_identity' => 'DEMO_PROCUREMENT_SYSTEM',
        'shared_secret' => 'topsecret',
        'browser_form_post_url' => $baseUrl . '/procurement/return',
    ];
    $_SESSION['supplier_config'] = [
        'expected_buyer_identity' => 'DEMO_BUYER',
        'supplier_identity' => 'MOCK_SUPPLIER',
        'expected_sender_identity' => 'DEMO_PROCUREMENT_SYSTEM',
        'shared_secret' => 'topsecret',
    ];
    redirectTo('/admin?saved=Demo credentials reset');
    return;
}

if ($method === 'POST' && $path === '/procurement/punchout') {
    $product = findProduct($products, (string) ($_POST['sku'] ?? 'SKU-001'));
    $setupXml = setupRequestXml($product, $_SESSION['erp_config']);
    $result = createPunchoutSessionFromXml($setupXml, $baseUrl, $_SESSION['supplier_config']);
    sendHtml(setupExchangePage($setupXml, $result));
    return;
}

if ($method === 'POST' && $path === '/cxml/punchout/setup') {
    $xml = file_get_contents('php://input') ?: '';
    $result = createPunchoutSessionFromXml($xml, $baseUrl, $_SESSION['supplier_config']);
    sendXml($result['xml'], $result['status']);
    return;
}

if ($method === 'POST' && $path === '/procurement/return') {
    $cxml = (string) ($_POST['cXML-urlencoded'] ?? '');
    array_unshift($_SESSION['returned_orders'], [
        'cxml' => $cxml,
        'created_at' => date('d/m/Y H:i:s'),
    ]);
    sendHtml('
    <div class="grid">
      <section class="stack">
        <h1>Basket returned</h1>
        <p>The procurement system received the PunchOutOrderMessage and can now turn the basket into a requisition.</p>
        <div class="callout"><strong>Validation:</strong> BuyerCookie, item lines, quantities, unit prices, currency, UOM, and classifications are visible in the returned cXML.</div>
        <a class="button" href="/">Back to procurement search</a>
      </section>
      <aside class="panel stack">
        <h2>Received cXML</h2>
        <pre>' . h($cxml) . '</pre>
      </aside>
    </div>');
    return;
}

if ($method === 'GET' && preg_match('#^/supplier/start/([a-f0-9-]+)$#', $path, $matches)) {
    if (!isset($_SESSION['punchout_sessions'][$matches[1]])) {
        http_response_code(404);
        echo 'Session not found';
        return;
    }

    redirectTo('/supplier/session/' . $matches[1]);
    return;
}

if ($method === 'GET' && preg_match('#^/supplier/session/([a-f0-9-]+)$#', $path, $matches)) {
    $session = $_SESSION['punchout_sessions'][$matches[1]] ?? null;
    if (!$session) {
        http_response_code(404);
        echo 'Session not found';
        return;
    }

    sendHtml(storefront($session, $products), 'supplier');
    return;
}

if ($method === 'POST' && preg_match('#^/supplier/session/([a-f0-9-]+)/basket/add$#', $path, $matches)) {
    $sessionId = $matches[1];
    if (!isset($_SESSION['punchout_sessions'][$sessionId])) {
        http_response_code(404);
        echo 'Session not found';
        return;
    }

    $sku = (string) ($_POST['sku'] ?? '');
    $quantity = max(1, (int) ($_POST['quantity'] ?? 1));
    $basket = &$_SESSION['punchout_sessions'][$sessionId]['basket'];
    foreach ($basket as &$line) {
        if ($line['sku'] === $sku) {
            $line['quantity'] += $quantity;
            redirectTo('/supplier/session/' . $sessionId);
            return;
        }
    }

    $basket[] = ['sku' => $sku, 'quantity' => $quantity];
    redirectTo('/supplier/session/' . $sessionId);
    return;
}

if ($method === 'POST' && preg_match('#^/supplier/session/([a-f0-9-]+)/return$#', $path, $matches)) {
    $session = $_SESSION['punchout_sessions'][$matches[1]] ?? null;
    if (!$session) {
        http_response_code(404);
        echo 'Session not found';
        return;
    }

    header('Content-Type: text/html; charset=utf-8');
    echo autoPostPage($session['browser_form_post_url'], orderMessageXml($session, $products));
    return;
}

http_response_code(404);
echo 'Not found';
