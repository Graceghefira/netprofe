<?php

namespace ArelAyudhi\DhivaProdevWa;

/**
 * @property Broadcast     $broadcast
 */

const SEND_GROUP = "T2vGHuNWqwUQFtk2D96xt9Hkq8dyHEXVwBXPMz8_oaA";
const SEND_INSTAN = "T2vGHuNWqwUQFtk2D96xt3hwktFc4t7cTiz74HCwOaM";
class Broadcast extends ProdevMessagesAbstract
{
	/** @param array{ daftar_group: string, daftar_pesan_id: string } $data */
	public function sendGroup(string $daftar_group, string $daftar_pesan_id): array
	{
		$form = [
			'daftar_group' => $daftar_group,
			'daftar_pesan_id' => $daftar_pesan_id
		];
		$response = $this->client->request(
			'POST',
			SEND_GROUP,
			[
				'form_params' => $this->getform($form)
			]
		);
		$body     = json_decode($response->getBody()->getContents(), true);
		if ($response->getStatusCode() === 200) {
			return $body;
		} else {
			return $this->errorCode($body['errcode']);
		}
	}
	public function sendInstan(array $data): array
	{
		$response = $this->client->request(
			'POST',
			SEND_INSTAN,
			[
				'form_params' => $this->getform($data)
			]
		);
		$body     = json_decode($response->getBody()->getContents(), true);
		if ($response->getStatusCode() === 200) {
			return $body;
		} else {
			return $this->errorCode($body['errcode']);
		}
	}
}
