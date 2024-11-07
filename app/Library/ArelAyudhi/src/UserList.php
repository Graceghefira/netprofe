<?php

namespace ArelAyudhi\DhivaProdevWa;

/**
 * @property UserList     $userlist
 */

const USER_LIST_GET_ALL = "eXvZjC3J1L3eNrqTEfc8mQ";
const USER_LIST_ALL_BY_GROUP_ID = "O7skdKVrEKhNkzkJm2WjaQms5SWijPt7b7yclfXwFvrTksqxEylS7tZC-Yff4RsW/";
const USER_LIST_ALL_BY_NAMA = "O7skdKVrEKhNkzkJm2WjaYhTwxA7MOeaqdT04OR6P0o/";
const USER_LIST_ALL_BY_NOMOR_TELEPON = "O7skdKVrEKhNkzkJm2WjaZ4hncBYJmRW5G5UynOx7JXLKLtvEhDJI4Ql2wG5mB--/";
const USER_LIST_SHOW_BY_NAMA_ID = "o3O-xMLw9O65qs2ibXvUkXPd5nqGKyYF39S_Y3LVuaQoPq4rsejLJPHi93ha_ilo/";
const USER_LIST_INSERT = "omj4wqnUSftatKfOYM5jyFcmIBSCJuwm3XQAR-jeTVw";
const USER_LIST_UPDATE = "mcJ9dTuItmpxsD5CuSswKgVqCDFIj2sLeqvY_JkCNQw/";
const GET_ALL = "eXvZjC3J1L3eNrqTEfc8mQ";
const USER_LIST_DELETE = "ucenwGz_ed9qVf6QxFxExZb1nnBvKxdxdbAiDZUdup4/";

class UserList extends ProdevMessagesAbstract
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
	public function allByGroupId(string $group_penerima): array
	{
		$response = $this->client->request(
			'POST',
			USER_LIST_ALL_BY_GROUP_ID . $group_penerima,
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
	public function allByNama(string $nama_penerima): array
	{
		$response = $this->client->request(
			'POST',
			USER_LIST_ALL_BY_NAMA . $nama_penerima,
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
	public function allByNomorTelepon(string $nomor_penerima): array
	{
		$response = $this->client->request(
			'POST',
			USER_LIST_ALL_BY_NOMOR_TELEPON . $nomor_penerima,
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
	public function showByNamaId(string $daftar_penerima_id): array
	{
		$response = $this->client->request(
			'POST',
			USER_LIST_SHOW_BY_NAMA_ID . $daftar_penerima_id,
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
	/** @param array{ nama_penerima[0]: string, nomor_penerima[0]: string, nomor_penerima[0]: string } $data */
	public function insert(array $data): array
	{
		$response = $this->client->request(
			'POST',
			USER_LIST_INSERT,
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
	public function update(string $nama_penerima = '', int $nomor_penerima, string $group_penerima = '', string $daftar_penerima_id): array
	{
		$form = [];
		if ($nama_penerima) {
			$form['nama_penerima'] = $nama_penerima;
		}
		if ($nomor_penerima) {
			$form['nomor_penerima'] = $nomor_penerima;
		}
		if ($group_penerima) {
			$form['group_penerima'] = $group_penerima;
		}

		$response = $this->client->request(
			'POST',
			USER_LIST_UPDATE . $daftar_penerima_id,
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
	public function delete(string $daftar_penerima_id): array
	{
		$response = $this->client->request(
			'POST',
			USER_LIST_DELETE . $daftar_penerima_id,
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
