# PunchOut ERP Test Harness

This branch is the ERP/procurement side of a cXML PunchOut flow. It no longer includes a mock supplier storefront. Instead, it generates ERP cXML, posts it to configurable webstore endpoints, accepts returned baskets, and sends approved purchase orders back to the configured webstore.

## Run locally

```bash
php -S localhost:8000 index.php
```

Then open:

```text
http://localhost:8000
```

Runtime state is stored in `mock-state.json`.

## Flow

1. The ERP search page shows Level 2 product results.
2. Clicking a result generates a `PunchOutSetupRequest` with `BuyerCookie`, `BrowserFormPost`, shared secret, and `SelectedItem`.
3. The ERP posts that cXML to the configured webstore PunchOut setup URL.
4. The webstore should respond with a `PunchOutSetupResponse` containing a `StartPage` URL.
5. The ERP displays the request, response, HTTP status, and an action to open the returned `StartPage`.
6. The buyer completes the PunchOut session in the real webstore.
7. The webstore posts a `PunchOutOrderMessage` back to the ERP `BrowserFormPost` URL.
8. The ERP shows the returned basket as a pending requisition.
9. Approval converts the basket into a cXML `OrderRequest`.
10. The ERP posts the approved `OrderRequest` to the configured webstore order URL and displays the response.

## Setup Admin

Open:

```text
http://localhost:8000/admin
```

Configure:

- ERP identities sent in cXML: buyer identity, supplier identity, sender identity, and shared secret.
- End user name and end user email. These are sent in the `PunchOutSetupRequest` as `Contact role="endUser"`:

```xml
<Contact role="endUser">
  <Name xml:lang="en">Jamie Buyer</Name>
  <Email>buyer@example.com</Email>
</Contact>
```

- `BrowserFormPost` return URL: the URL the webstore posts the basket back to.
- Supplier webstore base URL, for example `https://store.example.test`.
- Optional webstore endpoint overrides if your routes differ from `/cxml/punchout/setup` and `/cxml/order`.
- HTTP request timeout.

With only the supplier webstore base URL set, the ERP posts to:

```text
https://store.example.test/cxml/punchout/setup
https://store.example.test/cxml/order
```

The webstore owns credential validation. If setup fails, the ERP page shows the HTTP status and response body returned by the webstore.

## Ngrok / Public Testing

When the webstore cannot reach your local machine directly, expose this ERP harness and set the admin `BrowserFormPost` URL to:

```text
https://your-ngrok-host/procurement/return
```

If this app itself is behind ngrok, you can also start it without `BASE_URL`; it derives local return defaults from the incoming host. Set `BASE_URL` only when you need to force generated local URLs.

## Environment Defaults

You can prefill webstore settings with environment variables:

```bash
SUPPLIER_WEBSTORE_URL=https://store.example.test \
php -S localhost:8000 index.php
```

If your webstore uses different paths, use explicit endpoints:

```bash
WEBSTORE_PUNCHOUT_SETUP_URL=https://store.example.test/custom/setup \
WEBSTORE_ORDER_REQUEST_URL=https://store.example.test/custom/order \
php -S localhost:8000 index.php
```

Values saved in the admin UI are persisted to `mock-state.json`.
