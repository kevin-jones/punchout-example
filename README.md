# PunchOut Example

That is the smallest useful cXML PunchOut Level 2 implementation path for customer sign-in, basket creation and basket return.

## Runnable mock flow

This repo includes a dependency-free PHP mock so devs can see the flow in a browser.

Start it with:

```bash
php -S localhost:8000 index.php
```

Then open:

```text
http://localhost:8000
```

The mock demonstrates:

1. A procurement search page with Level 2 product results.
2. A generated `PunchOutSetupRequest` containing `BuyerCookie`, `BrowserFormPost`, shared secret, and `SelectedItem`.
3. A supplier `/cxml/punchout/setup` endpoint that authenticates the request and returns a `PunchOutSetupResponse` with a `StartPage` URL.
4. A mock supplier storefront that auto-signs in the buyer and lands on the selected item.
5. PunchOut basket building with normal checkout replaced by a return-basket action.
6. A generated `PunchOutOrderMessage`.
7. Auto-posting the basket back to the procurement `BrowserFormPost` URL.
8. A procurement return page that displays the received cXML.

Mock session and basket state is stored in the browser's PHP session for this demo.

### Mock setup admin

Open the setup admin at:

```text
http://localhost:8000/admin
```

The admin screen has two sides:

1. ERP setup values: what the procurement system writes into the outgoing `PunchOutSetupRequest`.
2. Supplier expected values: what the storefront requires before it creates a PunchOut session.

The demo setup succeeds only when these values match:

1. ERP `From` identity matches supplier expected buyer identity.
2. ERP `To` identity matches supplier identity.
3. ERP `Sender` identity matches supplier expected sender identity.
4. ERP shared secret matches supplier shared secret.

Change one side only, then try to punch out from a product. The supplier returns `401 Unauthorized` and no `StartPage` URL. Set both sides back to matching values, or use the reset button, and the flow works again.
