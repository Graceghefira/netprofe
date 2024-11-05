<?php

namespace ArelAyudhi\DhivaProdevWa;

/**
 * @property Group     $multiple
 */

const GET_ALL = "4YsZVeivKQDHNbC9QapHxA";
const INSERT = "ucdyZBMN9J7hivfx1AQIeg";
const UPDATE = "H9w1hTiHDGgiINLIVCgqOg/";
class Group extends ProdevMessagesAbstract
{
	public function getAll()
	{
		$response = $this->client->request(
			'POST',
			GET_ALL,
			[
				'form_params' => $this->getform()
			]
		);
		$body     = json_decode($response->getBody()->getContents(), true);
		if ($response->getStatusCode() === 200) {
			return $body;
		} else {
			return $this->errorCode($body);
		}
	}
	/** @param array{ nama_group: string, isi_pesan: string } $data */
	public function insert(string $nama_group, string $isi_pesan = '')
	{
		$form = [
			'nama_group' => $nama_group,
			'isi_pesan' => $isi_pesan
		];
		$response = $this->client->request(
			'POST',
			INSERT,
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
	public function update(string $nama_group, string $daftar_group_id): array
	{
		$form = [
			'nama_group' => $nama_group,
		];
		$response = $this->client->request(
			'POST',
			UPDATE . $daftar_group_id,
			[
				'form_params' => $this->getform($form)
			]
		);
		$body     = json_decode($response->getBody()->getContents(), true);
		if ($response->getStatusCode() === 200) {
			return $body;
		} else {
			return $this->errorCode($body);
		}
	}
}
