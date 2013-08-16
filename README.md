Global Iris (HSBC payment) Integration
======================

Global Iris (HSBC new payment gateway) Integration Class

https://resourcecentre.globaliris.com/

You can find a sample remote payment gateway in index.php, process.php is the the sender to and return.php is the reciever from 3D Secure.

You can also change the payment gateway configs on different scenarios based on Global Iris doc in RemotePayment.class.php using the RemotePayment::$setting static property.

here is the default configuration:

	array(
		's1' => 'A',
		's2' => 'A',
		's3' => 'A',
		's4' => 'N',//this should be always N
		's5' => 'A',//this should be always A
		's6' => 'A',
		's7' => 'N',//this should be always N
		's8' => 'N',
		's9' => 'N');//this should be always N
	);

	// 'A' is for Authorize and 'N' is for Not Authorize


here is the scenarios documentation: https://resourcecentre.globaliris.com/products.html?doc_id=102&id=124


please consider that, this class is only for remote gateway, not redirect, it also has support for 3D Secure.

Here is how you would implement a regular payment gateway without 3D Secure:

	$payment = new RemotePayment;

	$payment->setInfo($merchant, $order_id, $amount, $secret, $account);

	$payment->setCartInfo($card_num, $card_exp, $card_name, $card_type, $maestro_issue, $ccv2, $presind);

	$payment->authorize();

the authorize method will return an array with the below structure:

	array(
		'status' => 'error', // the payment result status 'success' for successful payment and 'error' for 
							// unsuccessful payment
		'message' => 'Invalid card number', // the returned message from HSBC
		'code' => 'A' // the returned code from HSBC
	);


Here is how you would implement a scheduled payment:

	$payment = new RemotePayment;

	$payment->setInfo($merchant, $order_id, $amount, $secret, $account);

	$payment->setCartInfo($card_num, $card_exp, $card_name, $card_type, $maestro_issue, $ccv2, $presind);

	$payment->setSchedule($alias, $frequency, $repeats);

	$payment->authorize(); // the return array will also include the schedule result


Here is how you would implement a payment with Adress Verification (AVS):

	$payment = new RemotePayment;

	$payment->setInfo($merchant, $order_id, $amount, $secret, $account);

	$payment->setCartInfo($card_num, $card_exp, $card_name, $card_type, $maestro_issue, $ccv2, $presind);

	$payment->setAddressInfo($country, $street_num, $postal_code);

	$payment->authorize(); // the return array will also include the AVS check result


Implementing the remote payment gateway with 3D Secure support needs a little more work, here is how you would do it:

for 3D secure we have a redirect to the issuing bank for checking the password, so we need to have 2 steps, a step for code that redirects the user to the bank and another step for returning the user from the bank and completing the payment, there is a functional sample payment gateway implemented in process.php and return.php files which you can take a look at. first lets take a look at the first step:

	$payment = new RemotePayment;

	$payment->setInfo($merchant, $order_id, $amount, $secret, $account);

	$payment->setCartInfo($card_num, $card_exp, $card_name, $card_type, $maestro_issue, $ccv2, $presind);

	$res = $payment->send3dSecure($term_url)// $term_url is the return url which you specify.

	if (is_array($res))
	{
		// there was an error which the payment so RemotePayment::send3dSecure() method has returned the result array
	}
	else
	{
		// the  first step has been successful so RemotePayment::send3dSecure() will return the redirect form which you will need to echo

		echo $res;
	}

ok this will send the user to the bank, then the user enters his/her password and bank will return the user to the url specified by you with $term_url, when returning, The GlobalIris system will send you some parameters in $_POST which you will need to pass to RemotePayment::recieve3dSecure() method, now lets take a look at the return step:

	$payment = new RemotePayment;

	$payment->setInfo($merchant, $order_id, $amount, $secret, $account);

	$payment->setCartInfo($card_num, $card_exp, $card_name, $card_type, $maestro_issue, $ccv2, $presind);

	$res = $payment->recieve3dSecure($_POST['PaRes'], $_POST['MD'], $merchant, $secret, $account);

	if($res['status'] === 'success')
	{
		echo "Thanks for your payment, your payment was successful<br>";
	}
	else
	{
		echo "There was an error with your payment<br>";
		echo $res['message'].'<br>';
	}

$res will be an array with the payment result info same as what RemotePayment::authorize() method returns, you can check $res['status'] to determine if the payment have been successful or not.

please note that you can set schedule or AVS check for 3d Secure payments too.

at last you can find a list of parameters used, along with their description:

1. $merchant: your GlobalIris merchant id
1. $order_id: the order id to track your order, the payment will be submitted with this order id in the GlobalIris Back Office.
1. $amount: the payment amount
1. $secret: your GlobalIris secret key.
1. $account: your GlobalIris account.
1. $card_num: the payer's card number.
1. $card_exp: the payer's card expiration date.
1. $card_type: the payer's card type, supported values: VISA, MC, MAESTRO, AMEX
1. $card_name: the cardholder's name
1. $maestro_issue: issue number for MAESTRO card type, specify it only for MAESTRO cards
1. $ccv2: the CVN of the card, if you wan CVN check (CCV2 for VISA cards).
1. $presind: indicates if you want CVN check or not, 1 is for forcing CVN check.
1. $term_url: the url to return the user from 3D secure to.

For Schedule:

1. $alias: the alias for schedule, this will be saved with the scheduled payment, enter what better describes the payment.
1. $frequency: the schedule frequency, you can use any frequency supported by GlobalIris, you can find the list at the documentation below, example: 'monthly'
https://resourcecentre.globaliris.com/products.html?doc_id=118&id=177
1. $repeats: number of times you want the payment to be taken.

### Important Notice:
I have no guaranty that this class would function properly, it might have bugs and mistakes, so you have to use it with your own responsibility and I will not accept any responsibility for any problem، difficulty or damage that using my tool (this class) would cause.

	



