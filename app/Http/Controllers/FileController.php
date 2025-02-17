<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class FileController extends Controller
{
    protected function getClient()
    {
        $config = [
            'host' => '45.149.93.122',
            'user' => 'admin',
            'pass' => 'dhiva1029',
            'port' => 8183,
        ];

        $ftpConnection = ftp_connect($config['host'], $config['port']);

        if (!$ftpConnection) {
            Log::error('Could not connect to the Mikrotik FTP server.');
            throw new \Exception('Could not connect to the Mikrotik FTP server.');
        }

        $login = ftp_login($ftpConnection, $config['user'], $config['pass']);
        if (!$login) {
            Log::error('Could not login to the Mikrotik FTP server.');
            ftp_close($ftpConnection);
            throw new \Exception('Could not login to the Mikrotik FTP server.');
        }

        ftp_pasv($ftpConnection, true);
        Log::info('FTP connection established successfully.');

        return $ftpConnection;
    }

    public function uploadFileToMikrotik(Request $request)
{
    $request->validate([
        'file' => 'required|file|max:1024',
        'folder' => 'required|string'
    ]);

    $file = $request->file('file');
    $filePath = $file->getRealPath();
    $fileName = $file->getClientOriginalName();
    $confirm = $request->input('confirm', false);
    $folder = rtrim($request->input('folder'), '/');

    try {
        $ftpConnection = $this->getClient();
        if (!$ftpConnection) {
            throw new \Exception('Unable to establish FTP connection.');
        }

        Log::info("FTP connection established for checking file in folder: {$folder}");

        if (!@ftp_chdir($ftpConnection, $folder)) {
            if (!ftp_mkdir($ftpConnection, $folder)) {
                throw new \Exception("Failed to create directory {$folder}");
            }
        }

        ftp_chdir($ftpConnection, '/');

        $existingFiles = ftp_nlist($ftpConnection, $folder);
        if (in_array("{$folder}/{$fileName}", $existingFiles)) {
            if (!$confirm) {
                return response()->json([
                    'message' => 'A file with the same name already exists in the specified folder. Do you want to overwrite it?',
                    'overwrite' => true
                ], 200);
            }
            Log::info('User confirmed overwrite for file in folder: ' . $folder);
        }

        $uploadResult = ftp_put($ftpConnection, "{$folder}/{$fileName}", $filePath, FTP_BINARY);
        Log::info('Upload attempt to folder completed with result: ' . ($uploadResult ? 'success' : 'failure'));

        if (!$uploadResult) {
            throw new \Exception('File upload to Mikrotik folder failed.');
        }
        return response()->json(['message' => 'File uploaded successfully to folder.'], 200);

    } catch (\Exception $e) {
        Log::error('File upload to folder failed: ' . $e->getMessage());
        return response()->json(['message' => 'File upload failed: ' . $e->getMessage()], 500);

    } finally {
        if (isset($ftpConnection) && $ftpConnection) {
            ftp_close($ftpConnection);
            Log::info('FTP connection closed after file upload attempt to folder.');
        }
    }
    }

    public function listFilesOnMikrotik(Request $request)
{
    $directory = $request->query('directory', '/');

    try {
        $ftpConnection = $this->getClient();
        Log::info('FTP connection established for listing files in directory: ' . $directory);

        $files = ftp_nlist($ftpConnection, $directory);

        if ($files === false) {
            throw new \Exception('Failed to retrieve file list from Mikrotik.');
        }

        ftp_close($ftpConnection);
        Log::info('FTP connection closed after listing files.');

        return response()->json(['files' => $files], 200);

    } catch (\Exception $e) {
        Log::error('Failed to list files on Mikrotik: ' . $e->getMessage());

        return response()->json(['message' => 'Failed to retrieve file list.'], 500);
    }
    }

    public function downloadFileFromMikrotik($fileName)
{
    try {
        if (empty($fileName)) {
            throw new \Exception('File name is required.');
        }

        $ftpConnection = $this->getClient();
        if (!$ftpConnection) {
            throw new \Exception('Unable to establish FTP connection.');
        }

        Log::info('FTP connection established for downloading file: ' . $fileName);

        $localDirectory = storage_path('app/downloads/');

        if (!file_exists($localDirectory)) {
            mkdir($localDirectory, 0755, true);
        }

        $localFilePath = $localDirectory . basename($fileName);

        $downloadResult = ftp_get($ftpConnection, $localFilePath, $fileName, FTP_BINARY);

        if (!$downloadResult) {
            throw new \Exception('File download from Mikrotik failed.');
        }

        Log::info('File downloaded successfully from Mikrotik to local: ' . $localFilePath);

        ftp_close($ftpConnection);
        Log::info('FTP connection closed after downloading file: ' . $fileName);

        if (!file_exists($localFilePath)) {
            throw new \Exception('File not found on server after download.');
        }

        return response()->download($localFilePath)->deleteFileAfterSend(true);

    } catch (\Exception $e) {
        Log::error('File download failed: ' . $e->getMessage());

        return response()->json(['message' => 'File download failed: ' . $e->getMessage()], 500);
    }
    }

    public function deleteFileOnMikrotik($filename)
{
    try {
        $ftpConnection = $this->getClient();
        if (!$ftpConnection) {
            throw new \Exception('Unable to establish FTP connection.');
        }

        $fileSize = ftp_size($ftpConnection, $filename);
        if ($fileSize === -1) {
            throw new \Exception('File not found on Mikrotik server.');
        }

        if (!ftp_delete($ftpConnection, $filename)) {
            throw new \Exception('Failed to delete file on Mikrotik.');
        }

        Log::info('File deleted successfully on Mikrotik: ' . $filename);

        ftp_close($ftpConnection);

        return response()->json(['message' => 'File deleted successfully.'], 200);
    } catch (\Exception $e) {
        if (isset($ftpConnection) && $ftpConnection) {
            ftp_close($ftpConnection);
        }

        Log::error('File deletion failed: ' . $e->getMessage());
        return response()->json(['message' => 'File deletion failed: ' . $e->getMessage()], 500);
    }
}
}
