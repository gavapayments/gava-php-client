# Gava PHP Client

A PHP client for your Gava installation

---

## Creating a checkout

```php

<?php

require 'vendor/autoload.php';

$g = new Gava\Gava('http://gava.dev', '12345678');

$checkoutUrl = $g->createCheckout(1, 1.00, 'http://example.com/thankyou', 'http://example.com.cart');

echo "<a href='" . $checkoutUrl . "'>Make payment</a>";



echo $checkout->reference;

echo 'Ok';


```

## Receiving, validating, and processing a webhook notification

The PHP client does all the work for you, so you can simply:

```php
<?php

require 'vendor/autoload.php';

$g = new Gava\Gava('http://gava.dev', '12345678');

try
{
	$checkout = $g->processWebhook();
}
catch(Gava\Exceptions\WebhookException $e)
{
	//Handle how you want. Or simply ignore, because Gava will resend another notification later
}

//We get here, the checkout is valid and paid, and you can fetch its details

$order = $checkout->reference;

```