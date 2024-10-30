<?php
namespace ArelAyudhi\DhivaProdevWa;

/**
 * @property Multiple     $multiple
 */
class Multiple extends ProdevMessagesAbstract
{
	public function sendText(string $username, string $password): array
	{
		$response = $this->client->request('POST', '/api/v2/send-message', [
			'form_params' => [
				'username'     => $username,
				'password'     => $password,
				'date'         => $this->getMillisecond(),
			],
		]);
		$body     = json_decode($response->getBody()->getContents(), true);
		if ($response->getStatusCode() === 200) {
			return (array)$body;
		} else {
			return $this->errorCode($body['errcode']);
		}
	}
}
