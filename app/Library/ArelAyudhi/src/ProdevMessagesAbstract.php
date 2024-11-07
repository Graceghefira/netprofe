<?php

namespace ArelAyudhi\DhivaProdevWa;

/**
 * @property Group    		 $group
 * @property Multiple        $multiple
 */
const error_code =
[
	/**
	 * Account Error Code
	 */
	1 => "proses gagal",
	10000 => "invalid client_id",
	10001 => "invalid client",
	10003 => "invalid token",
	10004 => "invalid access",
	10007 => "username atau password salah",
	10011 => "invalid refresh token",
	20002 => "not lock admin",
	30002 => "invalid username, hanya huruf latin yang diperbolehkan",
	30003 => "user sudah ada",
	30004 => "invalid userid to delete",
	30005 => "password harus md5 encrypted",
	30006 => "exceeds the restrictions of API call number",
	80000 => "date harus waktu sekarang, dalam 5 menit",
	80002 => "invalid json format",
	90000 => "internal server error",
	-3 => "invalid parameter",
	-2018 => "permission denied",
	-4063 => "Please delete/transfer all yours locks first",

	/**
	 * Lock Error Code
	 */
	-1003 => "lock tidak ada",
	-2025 => "lock frozen, tidak bisa dioperasikan",
	-3011 => "Cannot Transfer Lock(s) to Yourself",
	-4043 => "The function is not supported for this lock",
	-4056 => "run out of memory",
	-4067 => "NB Device tidak terdaftar",
	-4082 => "waktu auto locking tidak sah",
	/**
	 * Gateway Error Code
	 */
	-2012 => "Lock tidak terhubung ke gateway manapun",
	-3002 => "The gateway is offline. Please check and try again.",
	-3003 => "gateway sibuk, coba lagi",
	-3016 => "Cannot Transfer Gateway(s) to Yourself.",
	-3034 => "Network not configed. Please config the network and try again.",
	-3035 => "Wifi lock is in power saving mode, please turn off power saving and try again",
	-3036 => "The lock is offline. Please check and try again",
	-3037 => "The lock is busy. Please try again later",
	-4037 => "No such Gateway exists",
	/**
	 * RFID / IC Card Error Code
	 */
	-1021 => "This IC Card does not exist",
	-1023 => "This Fingerprint does not exist",
	/**
	 * Passcode Error Code
	 */
	-1007 => "No password data of this lock",
	-2009 => "Invalid Password",
	-3006 => "Invalid Passcode. Passcode should be between 6 - 9 Digits in length",
	-3007 => "The same passcode already exists. Please use another one",
	-3008 => "A Passcode that has never been used on the Lock cannot be changed",
	-3009 => "There is NO SPACE to store Customized Passcodes. Please Delete Un-Used Customized Passcodes and try again",
];
/**
 * Class BaseAbstract
 */
abstract class ProdevMessagesAbstract
{
	/**
	 * @var string
	 */
	protected $ProdevToken = '';
	/**
	 * @var \GuzzleHttp\Client
	 */
	protected $client;
	final function __construct(string $ProdevToken, \GuzzleHttp\Client $client)
	{
		$this->ProdevToken = $ProdevToken;
		$this->client       = $client;
	}
	protected function getMillisecond()
	{
		list($t1, $t2) = explode(' ', microtime());
		return (float)sprintf('%.0f', (floatval($t1) + floatval($t2)) * 1000);
	}
	protected static function errorCode(int $code)
	{
		$data = [
			'code' => $code,
			'message' => error_code[$code]
		];
		return $data;
	}
	static function queryBuildier($get)
	{
		(string)$string = '';
		foreach ($get as $key => $value) {
			$string .= $key . '=' . $value . '&';
		}
		return substr_replace($string, "", -1);
	}
	function getform($form = [])
	{
		$option = [
			'AccessToken' => $this->ProdevToken
		];
		if ($form) {
			foreach ($form as $v => $d) {
				$option[$v] = $d;
			}
		}
		return $option;
	}
}
