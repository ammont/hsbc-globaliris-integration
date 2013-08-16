Globaliris (HSBC payment) Integration
======================

Global Iris (HSBC new payment gateway) Integration Class

https://resourcecentre.globaliris.com/

You can find a sample remote payment gateway in index.php, process.php is the the sender to and return.php is the reciever from 3D Secure.

You can also change the payment gateway configs on different scenarios based on Global Iris doc in remote_payment.class.php using the RemotePayment::$setting static property.

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


here is the scenarios documentaion: https://resourcecentre.globaliris.com/products.html?doc_id=102&id=124


please consinder that this class if only ofr remote gateway, not redirect, it also has support for 3D Secure.

Here is how you would implement a regular payment gateway whithout 3D Secure:

	$payment = new RemotePayment;

	$payment->setInfo($merchant, $order_id, $amount, $secret, $account);

	$payment->setCartInfo($card_num, $card_exp, $card_name, $card_type, $maestro_issue, $ccv2, $presind);

	$payment->authorize();

the authorize method will return an array with the structure below:

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

	



