# MagentoGuy_PriceMatch

Shoppers submit a competitor's price for a product. The request is checked in the background against a pricing service, and on approval the shopper gets a single-use coupon locked to their account, that product and one unit.

Works on Magento Open Source and Adobe Commerce. Design, diagrams and decisions: [DESIGN.md](DESIGN.md).

## Install

```bash
composer config repositories.magentoguy-price-match vcs https://github.com/jeffliu68/module-price-match
composer require magentoguy/module-price-match
bin/magento setup:upgrade
```

Then keep cron and the evaluation consumer running:

```bash
bin/magento queue:consumers:start magentoguy.pricematch.request.evaluate
```

## Configure

- **Settings:** *Stores › Configuration › Sales › Price Match*. This covers the allowed competitor domains, discount cap, coupon lifetime, request limits and the pricing service URL and API key.
- **Per product:** *Allow Price Match*, in the product form. Setting it to No on a configurable product also excludes its variants.

## Pricing service

The module `POST`s each request to the configured URL, with the API key as a bearer token:

```json
{"request_id": 42, "sku": "24-MB01", "competitor_url": "https://www.amazon.com/dp/...", "claimed_price": 30.6, "currency": "USD"}
```

and expects:

```json
{"verified": true, "verified_price": 30.6}
```

Timeouts and 5xx responses are retried with backoff. Anything else unexpected goes to manual review.

### Mock pricing service

`dev/pricing-authority` is a small stand-in service for trying the module out. It comes with a git clone and is left out of the Composer package. Start it with PHP or Docker:

```bash
MOCK_API_KEY=demo-key php -S 0.0.0.0:8080 dev/pricing-authority/index.php

docker build -t pricing-authority dev/pricing-authority
docker run -d --name pricing-authority -p 8080:8080 pricing-authority
```

Then point the module at it. If Magento runs in Docker, run the mock on the same network with `--network <network>`; the default URL, `http://pricing-authority:8080/v1/verify`, then works as is.

```bash
bin/magento config:set magentoguy_pricematch/authority/url http://<host>:8080/v1/verify
bin/magento config:set magentoguy_pricematch/authority/api_key demo-key
```

A keyword in the competitor URL picks the outcome:

| URL contains | Result |
|---|---|
| *(nothing)* | Confirmed at the claimed price |
| `pm-reject` | Not verified: rejected |
| `pm-higher` | Confirmed at the claim + $5 |
| `pm-flaky` | Fails twice, then confirmed |
| `pm-timeout` | Times out: retried, then manual review |
| `pm-error` | HTTP 500: retried, then manual review |
| `pm-bad` | Malformed reply: manual review |

The mock confirms any price you claim. It's for demos, not a real price check.

## Use

- **Luma:** a *Price match* button on the product page. Requests are listed under *My Account › Price Match Requests*.
- **GraphQL:**
  - `submitPriceMatchRequest` takes either `sku`, or `parent_sku` + `selected_options`.
  - `priceMatchRequest` and `customerPriceMatchRequests` return the customer's requests.
  - `price_match_eligible` on products and `price_match_*` fields on `storeConfig` tell a headless storefront when to show the button.
- **Admin:** *Marketing › Price Match › Price Match Requests*, where you can review, approve or reject each request.
