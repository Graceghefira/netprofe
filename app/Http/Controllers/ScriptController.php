<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;
use RouterOS\Client;
use RouterOS\Config;
use RouterOS\Exceptions\ClientException;
use RouterOS\Exceptions\ConfigException;
use RouterOS\Exceptions\QueryException;
use RouterOS\Query;

class ScriptController extends CentralController
{
    public function addScriptAndScheduler(Request $request)
{
    $request->validate([
        'script_name' => 'required|string',
        'scheduler_name' => 'required|string',
        'tenant_id' => 'required|string',
    ]);

    $scriptName = $request->input('script_name');
    $schedulerName = $request->input('scheduler_name');
    $interval = $request->input('1m');
    $tenantId = $request->input('tenant_id');

    // Script dengan dynamic tenant ID
    $scriptSource = '
    :local tenantId "' . $tenantId . '"
    :local url1 ("https://dev.awh.co.id/api-netpro/api/delete-voucher-all-tenant?tenant_id=". $tenantId)
    :local response1 [/tool fetch url=$url1 mode=https http-method=post output=user as-value]
    :put ($response1->"data")
    ';

    try {
        $config = $this->getClientLogin();
        $client = $config;

        $addScriptQuery = new Query('/system/script/add');
        $addScriptQuery
            ->equal('name', $scriptName)
            ->equal('source', $scriptSource);
        $client->query($addScriptQuery)->read();

        $addSchedulerQuery = new Query('/system/scheduler/add');
        $addSchedulerQuery
            ->equal('name', $schedulerName)
            ->equal('interval', $interval)
            ->equal('on-event', "/system script run " . $scriptName);
        $client->query($addSchedulerQuery)->read();

        return response()->json(['message' => 'Script dan scheduler berhasil ditambahkan'], 200);
    } catch (ConfigException | ClientException | QueryException $e) {
        return response()->json(['message' => 'Terjadi kesalahan: ' . $e->getMessage()], 500);
    }
}
}
