# Gava PHP Client

A PHP client for your Gava installation

## Creating a checkout

```php

<?php

require 'vendor/autoload.php';

$g = new Gava\Gava('http://gava.dev', '12345678');

$checkoutUrl = $g->createCheckout(1, 1.00, 'http://example.com/thankyou', 'http://example.com.cart');

echo "<a href='" . $checkoutUrl . "'>Make payment</a>";

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

### Looking up the status of a checkout

You can also look up a checkout. Only remember to store the checkout hash returned in the `createCheckout` method
to be able to retrieve it for subsequent lookups.

Like the other methods in the class, `fetchCheckout` returns all the checkout's details as an object

For example:

```php
<?php

require 'vendor/autoload.php';

$gava = new Gava\Gava('http://gava.dev', '12345678');

$checkout = $gava->fetchCheckout('abcdefg');

if (!$checkout->paid) {

	//Do stuff

}


```

### Processing a ZIPIT payment

Gava is capable of processing payment notifications from the Zipit money transfer platform. Please consult us
before enabling them for your bank.

If your bank is supported, simply instruct (in your website or application) the customer to make the transfer and
then prompt them for the reference number. With the reference number collected, you can now process their payment.
Even if the payment hasn't reflected for any reason, when it does come through Gava will complete it and notify your website
as long as the reference number provided is valid and correct.

```php
<?php

require 'vendor/autoload.php';

$g = new Gava\Gava('http://gava.dev', '12345678');

//Let's assume the user submits the reference number for their transfer in a form
$refNo = $_POST['refNo'];

$checkoutUrl = $g->createCheckout(
	$reference = 1,
	$amount = 1.00,
	$returnUrl = 'http://example.com/thankyou',
	$cancelUrl = 'http://example.com.cart',
	//We don't need the phone
	$phone = null,
	//We pass the reference number to Gava
	$transactionCode = $refNo,
	//And let Gava know this is a ZIPIT payment 
	$method = 'ZIPIT'
);

//We can drop off processing at this point since Gava will notify your webhook URL. But for fun we can:

$checkoutHash = $g->hashFromURL($checkoutUrl);

$checkout = $g->fetchCheckout($checkoutHash);

if ($checkout->paid) {
	echo "Thank you for you payment";
}

```
