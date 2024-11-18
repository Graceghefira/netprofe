<?php

namespace ArelAyudhi\DhivaProdevWa;

/**
 * @property ListMessage     $listmessage
 */

const GET_ALL = "380XyFK0cI_hY_wHrP4SjA";
const INSERT = "jn2GXQvR98hGPHIDqgqN8g";
const UPDATE = "sS9omGVprCLnzt1lq-7QQw/";
const SHOW_PESAN_BY_PESAN_ID = "RkL2559LgadI185K5-IthjCqvZSBEhWUEOj1AaxD6so/";
const DELETE = "F5y8aFIoVgS8zx5hmiVPEw/";
class ListMessage extends ProdevMessagesAbstract
{
	public function getAll(): array
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
			return $this->errorCode($body['errcode']);
		}
	}
	public function insert(string $daftar_pesan_isi, string $judul_pesan): array
	{
		$form = $this->getform(
			[
				'daftar_pesan_isi' => $daftar_pesan_isi,
				'judul_pesan' => $judul_pesan
			]
		);
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
	public function update(string $daftar_pesan_isi, string $judul_pesan, string $daftar_pesan_id): array
	{
		$form = $this->getform(
			[
				'daftar_pesan_isi' => $daftar_pesan_isi,
				'judul_pesan' => $judul_pesan
			]
		);
		$response = $this->client->request(
			'POST',
			UPDATE . $daftar_pesan_id,
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
	public function showByid(string $daftar_pesan_id): array
	{
		$response = $this->client->request(
			'POST',
			SHOW_PESAN_BY_PESAN_ID . $daftar_pesan_id,
			[
				'form_params' => $this->getform()
			]
		);
		$body     = json_decode($response->getBody()->getContents(), true);
		if ($response->getStatusCode() === 200) {
			return $body;
		} else {
			return $this->errorCode($body['errcode']);
		}
	}
	public function delete(string $daftar_pesan_id): array
	{
		$response = $this->client->request(
			'POST',
			DELETE . $daftar_pesan_id,
			[
				'form_params' => $this->getform()
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
