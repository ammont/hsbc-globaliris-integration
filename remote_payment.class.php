<?php
class RemotePayment
{
	private $timestamp;
	private $merchant;
	private $account = NULL;
	private $order_id;
	private $amount;
	private $card_num;
	private $card_exp;
	private $card_name;
	private $card_type;
	private $maestro_issue = NULL;
	private $ccv2 = NULL;
	private $presind = NULL;
	private $valid = FALSE;
	private $currency;
	private $secret;
	//schedule parameters
	private $schedule = FALSE;
	private $schedule_frequency = NULL;
	private $schedule_repeats = NULL;
	private $schedule_alias = NULL;
	private $payer_ref = NULL;
	private $card_ref = NULL;
	//3d secure parameters
	private $cavv = NULL;
	private $eci = NULL;
	private $xid = NULL;
	private $pares = NULL;
	//AVS parameters
	private $avs = FALSE;
	private $avs_code = NULL;
	private $country = 'UK';
	//AVS Settings
	private static $rejectAvsAddress = array('N',);
	private static $rejectAvsPostCode = array('N',);


	private static $setting = array('s1' => 'A',
									's2' => 'A',
									's3' => 'A',
									's4' => 'N',//this should be always N
									's5' => 'A',//this should be always A
									's6' => 'A',
									's7' => 'N',//this should be always N
									's8' => 'N',
									's9' => 'N');//this should be always N

	public function  __construct()
	{
		$this->valid = FALSE;
	}

	public function setInfo($merchant, $order_id, $amount, $secret, $account = NULL)
	{
		$this->merchant = $merchant;
		$this->order_id = $order_id;
		$this->amount = $amount;
		if(!is_null($account)) $this->account = $account;
		$this->timestamp = strftime("%Y%m%d%H%M%S");
		$this->currency = 'GBP';
		$this->secret = $secret;
	}

	public function setCartInfo($card_num, $card_exp, $card_name, $card_type, $maestro_issue = NULL, $ccv2 = NULL, $presind = NULL)
	{
		$this->card_num = $card_num;
		$this->card_exp = $card_exp;
		$this->card_name = $card_name;
		$this->card_type = $card_type;
		$this->valid = TRUE;
		if($this->card_type == 'MAESTRO')
		{
			if(is_null($maestro_issue)){
				 $this->valid = FALSE;
			}
			else
			{
				$this->maestro_issue = $maestro_issue;
				$this->valid = TRUE;
			}
		}
		if(!is_null($ccv2))
		{
			$this->ccv2 = $ccv2;
			if(is_null($presind)) $this->valid = FALSE;
			else $this->presind = $presind;
		}
	}

	public function setAddressInfo($country, $street_num, $postal_code)
	{
		$this->country = $country;
		$avs_code = self::getDigits($postal_code).'|'.self::getDigits($street_num);
		$this->setAvsCode($avs_code);
	}

	public function setAvsCode($code)
	{
		$this->avs_code = $code;
		$this->avs = TRUE;
	}

	public function setSchedule($alias, $frequency, $repeats)
	{
		$this->schedule_alias = $alias;
		$this->schedule_frequency = $frequency;
		$this->schedule_repeats = $repeats;
		$this->schedule = TRUE;
	}

	public function authorize($threedsecure = FALSE, $cavv = NULL, $xid = NULL, $eci = NULL, $pares = NULL)
	{
		if($this->valid == FALSE)//the payment data is not complete
		{
			return array('status' => 'invalid' , 'message' => 'Invalid payment data');
		}
		else
		{
			if($threedsecure == FALSE)//direct payment without 3d secure redirect
			{
				$url = "https://remote.globaliris.com/realauth";
				$xml = $this->makeXML('auth');
			}
			elseif($threedsecure == TRUE && !is_null($cavv) && !is_null($xid) && !is_null($eci) && !is_null($pares))
			{
				$this->cavv = $cavv;
				$this->xid = $xid;
				$this->eci = $eci;
				$this->pares = $pares;
				$url = "https://epage.payandshop.com/epage-remote.cgi";
				$xml = $this->makeXML('auth3d');
			}
			else
			{
				return array('status' => 'invalid' , 'message' => 'Invalid 3d secure input data');
			}

			try
			{
				$response = $this->sendXML($url, $xml);
			}
			catch(Exception $e)
			{
				return array('status' => 'error', 'message' => $e->getMessage(), 'code' => $e->getCode());
			}

			$response = simplexml_load_string($response);
			if((string)$response->result === "00")
			{
				if($this->schedule == FALSE)// no schedule is set
				{
					return array('status' => 'success', 'message' => (string)$response->message, 'code' => (string)$response->result, 'avs_postal_code' => (string)$response->avspostcoderesponse, 'avs_address' => (string)$response->avsaddressresponse);
				}
				elseif($this->schedule == TRUE)// schedule is set
				{
					$result = $this->schedule();
					if($result['status'] === 'success')//the payment is authorized and the schedule is set
					{
						return array('status' => 'success', 'message' => (string)$response->message, 'code' => (string)$response->result, 'schedule' => $result, 'avs_postal_code' => (string)$response->avspostcoderesponse, 'avs_address' => (string)$response->avsaddressresponse);
					}
					else
					{
						return array('status' => 'error', 'message' => (string)$response->message, 'code' => (string)$response->result, 'schedule' => $result , 'avs_postal_code' => (string)$response->avspostcoderesponse, 'avs_address' => (string)$response->avsaddressresponse);
					}
				}
			}
			else
			{
				return array('status' => 'error', 'message' => (string)$response->message, 'code' => (string)$response->result);
			}
		}
	}

	public function send3dSecure($term_url)
	{
		if($this->valid == FALSE)
		{
			return array('status' => 'invalid' , 'message' => 'Invalid payment data');
		}
		else
		{
			$res = $this->verifyEnrolled();

			if($res['status']==='success')
			{
				$md ="orderid={$this->order_id}&cardnumber={$this->card_num}&cardname={$this->card_name}&cardtype={$this->card_type}&amount={$this->amount}&expdate={$this->card_exp}";
				if($this->avs === TRUE) $md .= "&avs_code={$this->avs_code}&country={$this->country}";
				if($this->schedule == TRUE)
				{
					$md .= "&schedulealias={$this->schedule_alias}&schedulefrequency={$this->schedule_frequency}&schedulerepeats={$this->schedule_repeats}";
				}
				if($this->maestro_issue)
				{
					$md .= "&maestroissue={$this->maestro_issue}";
				}
				if($this->ccv2)
				{
					$md .= "&ccv2={$this->ccv2}&presind={$this->presind}";
				}
				$md = self::encryptMd($md, $this->secret);
				return $this->createRedirectForm($res['url'], $term_url, $res['pareq'], $md);
			}
			elseif($res['status']==='authorize')
			{
				return $res;
			}
			else
			{
				return $res;
			}
		}
	}

	public function recieve3dSecure($pares, $md, $merchant, $secret, $account = NULL)
	{
		$md = self::decryptMd($md, $secret);
		$data = $this->parseMd($md);
		$this->setInfo($merchant, $data['orderid'], $data['amount'], $secret, $account);
		$this->setCartInfo($data['cardnumber'], $data['expdate'], $data['cardname'], $data['cardtype'], ($data['maestroissue'] ? $data['maestroissue'] : NULL), ($data['ccv2'] ? $data['ccv2'] : NULL), ($data['ccv2'] ? $data['presind'] : NULL));
		if(isset($data['avs_code']))
		{
			$this->country = $data['country'];
			$this->setAvsCode($data['avs_code']);
		}
		if(isset($data['schedulealias']))
		{
			$this->setSchedule($data['schedulealias'], $data['schedulefrequency'], $data['schedulerepeats']);
		}
		$res = $this->verifySig($pares);
		if($res['status'] === 'success')
		{
			$auth = $this->authorize(TRUE, $res['threedsecure']['cavv'], $res['threedsecure']['xid'], $res['threedsecure']['eci'], $pares);
			return $auth;
		}
		else
		{
			return $res;
		}

	}

	private  function schedule()
	{
		if($this->schedule == FALSE)
		{
			return array('status' => 'invalid' , 'message' => 'Schedule not set');
		}
		else
		{
			$payer_res = $this->payerNew();
			if($payer_res['status'] === 'success')
			{
				$card_res = $this->cardNew($payer_res['payer_ref']);
				if($card_res['status'] === 'success')
				{
					$this->payer_ref = $payer_res['payer_ref'];
					$this->card_ref = $card_res['card_ref'];
					$xml = $this->makeXML('schedule-new');

					try
					{
						$response = $this->sendXML('https://remote.globaliris.com/realauth', $xml);
					}
					catch(Exception $e)
					{
						return array('status' => 'error', 'message' => $e->getMessage(), 'code' => $e->getCode());
					}
					$response = simplexml_load_string($response);
					if((string)$response->result === "00")
					{
						return array('status' => 'success', 'message' => (string)$response->message, 'code' => (string)$response->result, 'payer_ref' => $payer_res['payer_ref'], 'card_ref' => $card_res['card_ref'], 'scheduletext' => (string)$response->scheduletext);
					}
					else
					{
						return array('status' => 'error', 'message' => (string)$response->message, 'code' => (string)$response->result, 'payer_ref' => $payer_res['payer_ref'], 'card_ref' => $card_res['card_ref']);
					}
				}
				else
				{
					return $card_res;
				}
			}
			else
			{
				return $payer_res;
			}

		}
	}

	private function payerNew()
	{
		$payer_ref = 'payer_'.$this->order_id;
		$this->payer_ref = $payer_ref;
		$xml = $this->makeXML('payer-new');

		try
		{
			$response = $this->sendXML('https://remote.globaliris.com/realvault', $xml);
		}
		catch(Exception $e)
		{
			return array('status' => 'error', 'message' => $e->getMessage(), 'code' => $e->getCode());
		}
		$response = simplexml_load_string($response);
		//check the response and return the payerref
		if((string)$response->result === "00")
		{
			return array('status' => 'success', 'message' => (string)$response->message, 'code' => (string)$response->result, 'payer_ref' => $payer_ref);
		}
		else
		{
			return array('status' => 'error', 'message' => (string)$response->message, 'code' => (string)$response->result);
		}
	}

	private function cardNew($payer_ref)
	{
		$this->payer_ref = $payer_ref;
		$xml = $this->makeXML('card-new');

		try
		{
			$response = $this->sendXML('https://remote.globaliris.com/realvault', $xml);
		}
		catch(Exception $e)
		{
			return array('status' => 'error', 'message' => $e->getMessage(), 'code' => $e->getCode());
		}
		$response = simplexml_load_string($response);
		//check the response and return the payerref
		if((string)$response->result === "00")
		{
			return array('status' => 'success', 'message' => (string)$response->message, 'code' => (string)$response->result, 'card_ref' => $this->card_type);
		}
		else
		{
			return array('status' => 'error', 'message' => (string)$response->message, 'code' => (string)$response->result);
		}
	}

	private function makeMd5()
	{
		$tmp = "{$this->timestamp}.{$this->merchant}.{$this->order_id}.{$this->amount}.{$this->currency}.{$this->card_num}";
		$tmp = md5($tmp);
		return md5("{$tmp}.{$this->secret}");
	}

	private function makeSha1($type = 'default', $payer_ref = NULL)
	{
		if($type == 'payernew')
		{
			$tmp = "{$this->timestamp}.{$this->merchant}.{$this->order_id}...{$payer_ref}";
		}
		elseif($type == 'cardnew')
		{
			$tmp = "{$this->timestamp}.{$this->merchant}.{$this->order_id}...{$payer_ref}.{$this->card_name}.{$this->card_num}";
		}
		elseif($type == 'schedule')
		{
			$tmp = "{$this->timestamp}.{$this->merchant}.{$this->order_id}.{$this->amount}.{$this->currency}.{$payer_ref}.{$this->schedule_frequency}";
		}
		else
		{
			$tmp = "{$this->timestamp}.{$this->merchant}.{$this->order_id}.{$this->amount}.{$this->currency}.{$this->card_num}";
		}

		$tmp = sha1($tmp);
		return sha1("{$tmp}.{$this->secret}");
	}

	private function parseMd($md)
	{
		$valuearray = split("&",$md);
		foreach ($valuearray as $postvalue) {
		   list($field,$content) = split("=",$postvalue);
		   $formatarray[$field] = $content;
		}
		return $formatarray;
	}

	private function verifyEnrolled()
	{
		if($this->valid == FALSE)
		{
			return array('status' => 'invalid' , 'message' => 'Invalid payment data');
		}
		else
		{
			$xml = $this->makeXML('verify-enroll');

			try
			{
				$response = $this->sendXML("https://remote.globaliris.com/realmpi", $xml);
			}
			catch(Exception $e)
			{
				return array('status' => 'error', 'message' => $e->getMessage(), 'code' => $e->getCode());
			}
			$response = simplexml_load_string($response);
			if((string)$response->result === "00")
			{
				return array('status' => 'success', 'message' => (string)$response->message, 'code' => (string)$response->result, 'url' => (string)$response->url, 'pareq' => (string)$response->pareq);
			}
			elseif((string)$response->result === "110")
			{
				if((string)$response->url)
				{
					return array('status' => 'success', 'message' => (string)$response->message, 'code' => (string)$response->result, 'url' => (string)$response->url, 'pareq' => (string)$response->pareq);
				}
				elseif(((string)$response->enrolled === "N" && !(string)$response->url) && self::$setting['s1'] === 'A')// scenario 1 cardholder not enrolled
				{
					if($this->card_type == 'VISA')
					{
						$res = $this->authorize(TRUE, '', '', '6', (string)$response->pareq);
					}
					else
					{
						$res = $this->authorize(TRUE, '', '', '1', (string)$response->pareq);
					}
					if($res['status'] == 'success')
					{
						$res['status'] = 'authorize';
					}
					return $res;

				}
				elseif(((string)$response->enrolled === "U" && !(string)$response->url) && self::$setting['s2'] === 'A')// scenario 2 Unable To Verify Enrolment
				{
					if($this->card_type == 'VISA')
					{
						$res = $this->authorize(TRUE, '', '', '7', (string)$response->pareq);
					}
					else
					{
						$res = $this->authorize(TRUE, '', '', '0', (string)$response->pareq);
					}
					if($res['status'] === 'success')
					{
						$res['status'] = 'authorize';
					}
					return $res;
				}
				else
				{
					return array('status' => 'error', 'message' => (string)$response->message, 'code' => (string)$response->result);
				}
			}
			elseif(substr((string)$response->result, 0, 1) === "5" && self::$setting['s3'] === 'A')// result code is like 5xx scenario 3 invalid response from enrollement server
			{
				if($this->card_type == 'VISA')
				{
					$res = $this->authorize(TRUE, '', '', '7', (string)$response->pareq);
				}
				else
				{
					$res = $this->authorize(TRUE, '', '', '0', (string)$response->pareq);
				}
				if($res['status'] == 'success')
				{
					$res['status'] = 'authorize';
				}
				return $res;
			}
			else
			{
				return array('status' => 'error', 'message' => (string)$response->message, 'code' => (string)$response->result);
			}
		}
	}

	private function verifySig($pares)
	{
		if($this->valid == FALSE)
		{
			return array('status' => 'invalid' , 'message' => 'Invalid payment data');
		}
		else
		{
			$this->pares = $pares;
			$xml = $this->makeXML('verify-sig');

			try
			{
				$response = $this->sendXML("https://remote.globaliris.com/realmpi", $xml);
			}
			catch(Exception $e)
			{
				return array('status' => 'error', 'message' => $e->getMessage(), 'code' => $e->getCode());
			}
			$response = simplexml_load_string($response);
			if((string)$response->result === "00")
			{
				if((string)$response->threedsecure->status === "Y" && self::$setting['s5'] === 'A')// scenario 5 a Successful Authentication this should always authorize
				{
					$threedsecure = array('status' => (string)$response->threedsecure->status,
									 'eci' => (string)$response->threedsecure->eci,
									 'xid' => (string)$response->threedsecure->xid,
									 'cavv' => (string)$response->threedsecure->cavv
								);
					return array('status' => 'success', 'message' => (string)$response->message, 'code' => (string)$response->result, 'threedsecure' => $threedsecure);
				}
				elseif((string)$response->threedsecure->status === "A" && self::$setting['s6'] === 'A')// scenario 6  Issuing Bank With Attempt ACS
				{
					$threedsecure = array('status' => (string)$response->threedsecure->status,
									 'eci' => (string)$response->threedsecure->eci,
									 'xid' => (string)$response->threedsecure->xid,
									 'cavv' => (string)$response->threedsecure->cavv
								);
					return array('status' => 'success', 'message' => (string)$response->message, 'code' => (string)$response->result, 'threedsecure' => $threedsecure);
				}
				elseif((string)$response->threedsecure->status === "N" && self::$setting['s7'] === 'A')// scenario 7 Incorrect Password Entered you should not authorize this payment
				{
					$threedsecure = array('status' => (string)$response->threedsecure->status,
									 'eci' => $this->card_type == 'VISA'? '7':'0',
									 'xid' => (string)$response->threedsecure->xid,
									 'cavv' => (string)$response->threedsecure->cavv
								);
					return array('status' => 'success', 'message' => (string)$response->message, 'code' => (string)$response->result, 'threedsecure' => $threedsecure);
				}
				elseif((string)$response->threedsecure->status === "U" && self::$setting['s8'] === 'A')// scenario 8 Authentication Unavailable
				{
					$threedsecure = array('status' => (string)$response->threedsecure->status,
									 'eci' => $this->card_type == 'VISA'? '7':'0',
									 'xid' => (string)$response->threedsecure->xid,
									 'cavv' => (string)$response->threedsecure->cavv
								);
					return array('status' => 'success', 'message' => (string)$response->message, 'code' => (string)$response->result, 'threedsecure' => $threedsecure);
				}
				else
				{
					$threedsecure = array('status' => (string)$response->threedsecure->status,
									 'eci' => (string)$response->threedsecure->eci,
									 'xid' => (string)$response->threedsecure->xid,
									 'cavv' => (string)$response->threedsecure->cavv
								);
					return array('status' => 'error', 'message' => (string)$response->message, 'code' => (string)$response->result, 'threedsecure' => $threedsecure);
				}

			}
			elseif((string)$response->result === "110" && self::$setting['s4'] === 'A')// scenario 4 Enrolled But Invalid Response From ACS, this is fraud!
			{
				$threedsecure = array('status' => (string)$response->threedsecure->status,
									 'eci' => (string)$response->threedsecure->eci,
									 'xid' => (string)$response->threedsecure->xid,
									 'cavv' => (string)$response->threedsecure->cavv
								);
				return array('status' => 'success', 'message' => (string)$response->message, 'code' => (string)$response->result, 'threedsecure' => $threedsecure);
			}
			elseif(substr((string)$response->result, 0, 1) === "5" && self::$setting['s9'] === 'A')// scenario 9 Invalid Response From ACS possible fraud 5xx
			{
				$threedsecure = array('status' => (string)$response->threedsecure->status,
									 'eci' => $this->card_type == 'VISA'? '7':'0',
									 'xid' => (string)$response->threedsecure->xid,
									 'cavv' => (string)$response->threedsecure->cavv
								);
				return array('status' => 'success', 'message' => (string)$response->message, 'code' => (string)$response->result, 'threedsecure' => $threedsecure);
			}
			else
			{
				$threedsecure = array('status' => (string)$response->threedsecure->status,
									 'eci' => (string)$response->threedsecure->eci,
									 'xid' => (string)$response->threedsecure->xid,
									 'cavv' => (string)$response->threedsecure->cavv
								);
				return array('status' => 'error', 'message' => (string)$response->message, 'code' => (string)$response->result, 'threedsecure' => $threedsecure);
			}
		}
	}

	private function sendXML($url, $xml)
	{
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_USERAGENT, "payandshop.com php version 0.9");
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $xml);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
		$response = array(
			'result' => curl_exec($ch),
			'error' => curl_error($ch),
			'info' => curl_getinfo($ch),
		);
		curl_close($ch);
		if ($response['error'])
		{
			throw new Exception($response['error'], -1);
		}
		if ($response['info']['http_code'] != 200)
		{
			throw new Exception("HTTP error", $response['info']['http_code']);
		}
		if(simplexml_load_string($response['result']) == FALSE)
		{
			throw new Exception('Invalid Response', -2);
		}

		return $response['result'];
}

	private function makeXML($type)
	{
		if($type == 'auth')
		{
			$hash = $this->makeMd5();
			$xml = "<request type='auth' timestamp='{$this->timestamp}'>
						<merchantid>{$this->merchant}</merchantid>
						<account>{$this->account}</account>
						<orderid>{$this->order_id}</orderid>
						<amount currency='{$this->currency}'>{$this->amount}</amount>
						<card>
							<number>{$this->card_num}</number>
							<expdate>{$this->card_exp}</expdate>
							<type>{$this->card_type}</type>
							<chname>{$this->card_name}</chname>";
			if(!is_null($this->maestro_issue))
			{
				$xml .= "<issueno>{$this->maestro_issue}</issueno>";
			}
			if(!is_null($this->ccv2))
			{
				$xml .= "<cvn>
						 <number>{$this->ccv2}</number>
						 <presind>{$this->presind}</presind>
						</cvn>";
			}

			$xml .= "</card>
					<autosettle flag='1'/>";
			if($this->avs === TRUE)
			{
				$xml .= "<tssinfo>
							<address type='billing'>
							 	<code>{$this->avs_code}</code>
							  	<country>{$this->country}</country>
							</address>
						</tssinfo>";
			}

			$xml .=	"<md5hash>{$hash}</md5hash>
					</request>";
			return $xml;
		}
		elseif($type == 'auth3d')
		{
			$hash = $this->makeMd5();
			$xml = "<request type='auth' timestamp='{$this->timestamp}'>
						<merchantid>{$this->merchant}</merchantid>
						<account>{$this->account}</account>
						<orderid>{$this->order_id}</orderid>
						<amount currency='{$this->currency}'>{$this->amount}</amount>
						<card>
							<number>{$this->card_num}</number>
							<expdate>{$this->card_exp}</expdate>
							<type>{$this->card_type}</type>
							<chname>{$this->card_name}</chname>";
			if(!is_null($this->maestro_issue))
			{
				$xml .= "<issueno>{$this->maestro_issue}</issueno>";
			}
			if(!is_null($this->ccv2))
			{
				$xml .= "<cvn>
						 <number>{$this->ccv2}</number>
						 <presind>{$this->presind}</presind>
						</cvn>";
			}
			$xml .= "</card>
					<mpi>
				        <cavv>{$this->cavv}</cavv>
				        <xid>{$this->xid}</xid>
				        <eci>{$this->eci}</eci>
				     </mpi>
				     <autosettle flag='1'/>";

			if($this->avs === TRUE)
			{
				$xml .= "<tssinfo>
							<address type='billing'>
							 	<code>{$this->avs_code}</code>
							  	<country>{$this->country}</country>
							</address>
						</tssinfo>";
			}

			$xml .=	"<md5hash>{$hash}</md5hash>
					<pares>{$this->pares}</pares>
					</request>";
			return $xml;
		}
		elseif($type == 'verify-enroll')
		{
			$hash = $this->makeSha1();
			$xml = "<request type='3ds-verifyenrolled' timestamp='{$this->timestamp}'>
						<merchantid>{$this->merchant}</merchantid>
						<account>{$this->account}</account>
						<orderid>{$this->order_id}</orderid>
						<amount currency='{$this->currency}'>{$this->amount}</amount>
						<card>
							<number>{$this->card_num}</number>
							<expdate>{$this->card_exp}</expdate>
							<type>{$this->card_type}</type>
							<chname>{$this->card_name}</chname>
						</card>
						<autosettle flag='1'/>
						<sha1hash>{$hash}</sha1hash>
					</request>";
			return $xml;
		}
		elseif($type == 'verify-sig')
		{
			$hash = $this->makeMd5();
			$xml = "<request type='3ds-verifysig' timestamp='{$this->timestamp}'>
						<merchantid>{$this->merchant}</merchantid>
						<account>{$this->account}</account>
						<orderid>{$this->order_id}</orderid>
						<amount currency='{$this->currency}'>{$this->amount}</amount>
						<card>
							<number>{$this->card_num}</number>
							<expdate>{$this->card_exp}</expdate>
							<type>{$this->card_type}</type>
							<chname>{$this->card_name}</chname>
						</card>
						<autosettle flag='1'/>
						<md5hash>{$hash}</md5hash>
					    <pares>{$this->pares}</pares>
					</request>";
			return $xml;
		}
		elseif($type == 'schedule-new')
		{
			$hash = $this->makeSha1('schedule', $this->payer_ref);
			$xml = "<request timestamp='{$this->timestamp}' type='schedule-new'>
						<merchantid>{$this->merchant}</merchantid>
						<scheduleref>{$this->order_id}</scheduleref>
						<alias>{$this->schedule_alias}</alias>
						<payerref>{$this->payer_ref}</payerref>
						<paymentmethod>{$this->card_ref}</paymentmethod>
						<account>{$this->account}</account>
						<transtype>auth</transtype>
						<amount currency='{$this->currency}'>{$this->amount}</amount>
						<prodid></prodid>
						<varref></varref>
						<custno></custno>
						<comment></comment>
						<schedule>{$this->schedule_frequency}</schedule>
						<numtimes>{$this->schedule_repeats}</numtimes>
						<sha1hash>{$hash}</sha1hash>
					</request>";
			return $xml;
		}
		elseif($type == 'payer-new')
		{
			list($fname, $lname) = explode(' ', $this->card_name,2);
			$hash = $this->makeSha1('payernew', $this->payer_ref);
			$xml = "<request type='payer-new' timestamp='{$this->timestamp}'>
						<merchantid>{$this->merchant}</merchantid>
						<orderid>{$this->order_id}</orderid>
						<payer type='Business' ref='{$this->payer_ref}'>
							<firstname>{$fname}</firstname>
							<surname>{$lname}</surname>
						</payer>
						<sha1hash>{$hash}</sha1hash>
						</request>";
			return $xml;
		}
		elseif($type == 'card-new')
		{
			$hash = $this->makeSha1('cardnew', $this->payer_ref);
			$xml = "<request type='card-new' timestamp='{$this->timestamp}'>
						<merchantid>{$this->merchant}</merchantid>
						<orderid>{$this->order_id}</orderid>
						<card>
							<ref>{$this->card_type}</ref>
							<payerref>{$this->payer_ref}</payerref>
							<number>{$this->card_num}</number>
							<expdate>{$this->card_exp}</expdate>
							<chname>{$this->card_name}</chname>
							<type>{$this->card_type}</type>";
			if(!is_null($this->maestro_issue))
			{
				$xml .= "<issueno>{$this->maestro_issue}</issueno>";
			}
			$xml .= "</card>
						<sha1hash>{$hash}</sha1hash>
					</request>";
			return $xml;
		}
		else
		{
			return FALSE;
		}
	}

	private function createRedirectForm($redirect_url, $term_url, $pareq, $md)
	{
		 $html = <<<HTML
<html>
<head>
<title>Redirect</title>
<script language="Javascript">
function submitForm(){
document.acsform.submit();
	                 }
</script>
<meta http-equiv="Pragma" content="no-cache">
<meta http-equiv="Expires" content="-1">
</head>

<!-- <body onLoad="submitForm()"> -->
<body onLoad="submitForm()">
<h1 align="center">You will be Redirected to the bank...</h1>
<form name="acsform" action={$redirect_url} method="POST">

<input type="hidden" name="PaReq" value="{$pareq}">
<input type="hidden" name="TermUrl" value="{$term_url}">
<input type="hidden" name="MD" value="{$md}">
<input type="submit" value="Submit">
</body>
</html>
HTML;
		return $html;
	}

	private static function encryptMd($md, $secret)
	{
		$md = serialize($md);
		$md = mcrypt_encrypt(MCRYPT_RIJNDAEL_256, $secret, $md, MCRYPT_MODE_ECB);
		$md = gzcompress($md);
		$md = base64_encode($md);
		return $md;
	}

	private static function decryptMd($md, $secret)
	{
		$md = base64_decode($md);
		$md = gzuncompress($md);
		$md = trim(mcrypt_decrypt(MCRYPT_RIJNDAEL_256, $secret, $md, MCRYPT_MODE_ECB));
		$md = unserialize($md);
		return $md;
	}

	private static function getDigits($string)
	{
		return preg_replace('/[^0-9]*/', '', $string);
	}
}
