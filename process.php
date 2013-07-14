<?php
include 'remote_payment.class.php';
echo 'Sample HSBC remote payment with 3d Secure <br>';
$merchant = 'cvbrowsertest';
$order_id = '1111'.rand(1, 100000);
$account = 'internet';
$amount = $_POST['amount'];
$card_num = $_POST['card_num'];
$card_exp = $_POST['card_exp'];
$card_name = $_POST['card_name'];
$card_type = $_POST['card_type'];
$card_cvn = $_POST['card_cvn'];
$secret = 'secret';
$payment = new RemotePayment();
$payment->setInfo($merchant, $order_id, $amount, $secret, $account);
if($_POST['card_type'] == 'MAESTRO')
{
	$payment->setCartInfo($card_num, $card_exp, $card_name, $card_type, $_POST['card_issue'], $card_cvn, '1');
}
else
{
	$payment->setCartInfo($card_num, $card_exp, $card_name, $card_type, NULL, $card_cvn, '1');
}

$payment->setAddressInfo($_POST['country'], $_POST['street'], $_POST['postal_code']);

if($_POST['payment_type'] == 'regular')
{
	$res = $payment->send3dSecure('http://sample.mail-cit.com/hsbc/return.php');
}
elseif($_POST['payment_type'] == 'schedule')
{
	$payment->setSchedule('1 month test schedule payment', 'monthly', '-1');
	$res = $payment->send3dSecure('http://sample.mail-cit.com/hsbc/return.php');
}

if(is_array($res))
{
	if($res['status'] === 'success' || $res['status'] === 'authorize')
	{
		echo "Thanks for your payment, your payment was successful<br>";
	}
	else
	{
		echo "There was an error with your payment:<br>";
		echo $res['message']."<br>";
	}
	echo "<a href='/hsbc/'>Back to payment form</a>";

}
else
{
	// the method returns the redirect form
	echo $res;
}
