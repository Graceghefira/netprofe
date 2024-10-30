<?php

namespace ArelAyudhi\DhivaProdevWa;

// const URL = "http://localhost/adhivasindo/wa-api/index.php/RMuumzsQWg5HtzQb/";
const URL = "https://blast.awh.co.id/index.php/RMuumzsQWg5HtzQb/";
/**
 * @property Group    		 $group
 * @property Multiple        $multiple
 * @property ListMessage     $listmessage
 * @property Broadcast       $broadcast
 * @property UserList        $userlist
 */
class ProdevMessages
{
	/**
	 * @var string
	 */
	private $ProdevToken = '';

	/**
	 * @var  \GuzzleHttp\Client
	 */
	private $client;

	public function __construct(string $ProdevToken)
	{
		$headers = [
			'Content-Type' => 'application/x-www-form-urlencoded'
		];
		$this->ProdevToken     = $ProdevToken;
		$this->client       = new \GuzzleHttp\Client(
			[
				'base_uri' => URL,
				'headers' => $headers,
			],
		);
	}

	protected $container = [];

	protected $providers = [
		"multiple"     	=> Multiple::class,
		"group"     	=> Group::class,
		"listmessage"   => ListMessage::class,
		"broadcast"     => Broadcast::class,
		"userlist"     	=> UserList::class,
	];

	/**
	 * @param $name
	 * @return mixed
	 * @throws \Exception
	 */
	public function __get($name)
	{
		if (!isset($this->providers[$name])) {
			throw new \Exception("class not found");
		} else {
			if (!isset($this->container[$name]) || !$this->container[$name] instanceof  ProdevMessagesAbstract) {
				try {
					$this->container["{$name}"] = new $this->providers[$name]($this->ProdevToken, $this->client);
				} catch (\Exception $e) {
					throw new $e;
				}
			}
			return $this->container["{$name}"];
		}
	}


	static function getDateTimeMillisecond(string $dateTime): int
	{
		$dateTime = $dateTime . ".0";
		list($usec, $sec) = explode(".", $dateTime);
		$date        = strtotime($usec);
		$return_data = str_pad($date . $sec, 13, "0", STR_PAD_RIGHT);
		return (int)$return_data;
	}
	/**
	 * @param string $dateTime
	 * @return int
	 */
}
