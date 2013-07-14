<?php
include 'remote_payment.class.php';
echo 'Sample HSBC remote payment with 3d Secure Return<br>';

if(isset($_POST['PaRes']) && isset($_POST['MD']))
{
	$pasres = $_POST['PaRes'];
	$md = $_POST['MD'];
	$merchant = 'cvbrowsertest';
	$secret = 'secret';
	$account = 'internet';
	$payment = new RemotePayment();
	$res = $payment->recieve3dSecure($pasres, $md, $merchant, $secret, $account);

	if($res['status'] === 'success')
	{
		echo "Thanks for your payment, your payment was successful<br>";
	}
	else
	{
		echo "There was an error with your payment<br>";
		echo $res['message'].'<br>';
	}


	echo "<a href='/hsbc/'>Back to payment form</a>";
}
else
{
	echo 'NO POST!';
}

