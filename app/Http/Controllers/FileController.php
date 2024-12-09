<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class FileController extends Controller
{
    protected function getClient()
    {
        $config = [
            'host' => 'id-4.hostddns.us',  // Ganti dengan domain DDNS kamu
            'user' => 'admin',             // Username Mikrotik
            'pass' => 'admin2',            // Password Mikrotik
            'port' => 21326,               // Port FTP Mikrotik (default 21)
        ];

        // Buat koneksi FTP
        $ftpConnection = ftp_connect($config['host'], $config['port']);

        if (!$ftpConnection) {
            Log::error('Could not connect to the Mikrotik FTP server.');
            throw new \Exception('Could not connect to the Mikrotik FTP server.');
        }

        // Login ke FTP
        $login = ftp_login($ftpConnection, $config['user'], $config['pass']);
        if (!$login) {
            Log::error('Could not login to the Mikrotik FTP server.');
            ftp_close($ftpConnection);
            throw new \Exception('Could not login to the Mikrotik FTP server.');
        }

        // Set passive mode jika diperlukan
        ftp_pasv($ftpConnection, true);
        Log::info('FTP connection established successfully.');

        return $ftpConnection;
    }

    public function uploadFileToMikrotik(Request $request)
{
    // Validasi input untuk memastikan file dan folder yang diunggah ada
    $request->validate([
        'file' => 'required|file|max:2048', // Optional: Batasi ukuran file 2MB
        'folder' => 'required|string' // Nama folder sebagai parameter
    ]);

    // Ambil file yang diunggah dari request
    $file = $request->file('file');
    $filePath = $file->getRealPath();
    $fileName = $file->getClientOriginalName();
    $confirm = $request->input('confirm', false); // Ambil confirm, default-nya false
    $folder = rtrim($request->input('folder'), '/'); // Ambil folder, pastikan tidak ada trailing slash

    try {
        // Koneksi FTP menggunakan fungsi getClient()
        $ftpConnection = $this->getClient();
        if (!$ftpConnection) {
            throw new \Exception('Unable to establish FTP connection.');
        }

        Log::info("FTP connection established for checking file in folder: {$folder}");

        // Cek apakah folder sudah ada, jika belum buat folder
        if (!@ftp_chdir($ftpConnection, $folder)) {
            if (!ftp_mkdir($ftpConnection, $folder)) {
                throw new \Exception("Failed to create directory {$folder}");
            }
        }

        // Kembali ke folder root sebelum mengupload file
        ftp_chdir($ftpConnection, '/');

        // Cek apakah file dengan nama yang sama sudah ada di folder yang ditentukan
        $existingFiles = ftp_nlist($ftpConnection, $folder);
        if (in_array("{$folder}/{$fileName}", $existingFiles)) {
            // Jika file sudah ada dan confirm belum diberikan, minta konfirmasi dulu
            if (!$confirm) {
                return response()->json([
                    'message' => 'A file with the same name already exists in the specified folder. Do you want to overwrite it?',
                    'overwrite' => true
                ], 200);
            }
            // Jika user memberikan konfirmasi untuk overwrite, lanjutkan ke proses upload
            Log::info('User confirmed overwrite for file in folder: ' . $folder);
        }

        // Lakukan upload file (overwrite atau upload baru) ke folder yang ditentukan
        $uploadResult = ftp_put($ftpConnection, "{$folder}/{$fileName}", $filePath, FTP_BINARY);
        Log::info('Upload attempt to folder completed with result: ' . ($uploadResult ? 'success' : 'failure'));

        // Cek apakah upload berhasil
        if (!$uploadResult) {
            throw new \Exception('File upload to Mikrotik folder failed.');
        }

        // Jika sukses, return success response
        return response()->json(['message' => 'File uploaded successfully to folder.'], 200);

    } catch (\Exception $e) {
        // Jika ada error, log error dan kirim response error
        Log::error('File upload to folder failed: ' . $e->getMessage());
        return response()->json(['message' => 'File upload failed: ' . $e->getMessage()], 500);

    } finally {
        // Pastikan koneksi FTP selalu ditutup
        if (isset($ftpConnection) && $ftpConnection) {
            ftp_close($ftpConnection);
            Log::info('FTP connection closed after file upload attempt to folder.');
        }
    }
    }

    public function listFilesOnMikrotik(Request $request)
{
    // Ambil parameter 'directory' dari query string, default ke root '/'
    $directory = $request->query('directory', '/');

    try {
        // Koneksi FTP menggunakan fungsi getClient()
        $ftpConnection = $this->getClient();
        Log::info('FTP connection established for listing files in directory: ' . $directory);

        // Ambil daftar file di direktori yang ditentukan
        $files = ftp_nlist($ftpConnection, $directory);

        if ($files === false) {
            throw new \Exception('Failed to retrieve file list from Mikrotik.');
        }

        // Tutup koneksi FTP
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
        // Cek apakah nama file valid
        if (empty($fileName)) {
            throw new \Exception('File name is required.');
        }

        // Koneksi FTP menggunakan fungsi getClient()
        $ftpConnection = $this->getClient();
        if (!$ftpConnection) {
            throw new \Exception('Unable to establish FTP connection.');
        }

        Log::info('FTP connection established for downloading file: ' . $fileName);

        // Tentukan direktori lokal untuk menyimpan file yang diunduh sementara di server Laravel
        $localDirectory = storage_path('app/downloads/');

        // Pastikan direktori lokal ada, jika tidak maka buat direktori tersebut
        if (!file_exists($localDirectory)) {
            mkdir($localDirectory, 0755, true);
        }

        // Tentukan path lengkap untuk file yang akan diunduh
        $localFilePath = $localDirectory . basename($fileName);

        // Unduh file dari Mikrotik ke server lokal, mendukung path dengan folder
        $downloadResult = ftp_get($ftpConnection, $localFilePath, $fileName, FTP_BINARY);

        if (!$downloadResult) {
            throw new \Exception('File download from Mikrotik failed.');
        }

        Log::info('File downloaded successfully from Mikrotik to local: ' . $localFilePath);

        // Tutup koneksi FTP
        ftp_close($ftpConnection);
        Log::info('FTP connection closed after downloading file: ' . $fileName);

        // Pastikan file sudah ada di server sebelum mengirim ke user
        if (!file_exists($localFilePath)) {
            throw new \Exception('File not found on server after download.');
        }

        // Kirim file ke browser user dan hapus dari server setelah diunduh
        return response()->download($localFilePath)->deleteFileAfterSend(true);

    } catch (\Exception $e) {
        Log::error('File download failed: ' . $e->getMessage());

        return response()->json(['message' => 'File download failed: ' . $e->getMessage()], 500);
    }
    }

    public function deleteFileOnMikrotik($filename)
{
    try {
        // Establish FTP connection
        $ftpConnection = $this->getClient();
        if (!$ftpConnection) {
            throw new \Exception('Unable to establish FTP connection.');
        }

        // Check if file exists on Mikrotik server
        $fileSize = ftp_size($ftpConnection, $filename);
        if ($fileSize === -1) {
            throw new \Exception('File not found on Mikrotik server.');
        }

        // Delete the file
        if (!ftp_delete($ftpConnection, $filename)) {
            throw new \Exception('Failed to delete file on Mikrotik.');
        }

        // Log successful deletion
        Log::info('File deleted successfully on Mikrotik: ' . $filename);

        // Close FTP connection
        ftp_close($ftpConnection);

        return response()->json(['message' => 'File deleted successfully.'], 200);
    } catch (\Exception $e) {
        // Close FTP connection if still open
        if (isset($ftpConnection) && $ftpConnection) {
            ftp_close($ftpConnection);
        }

        // Log error and return response
        Log::error('File deletion failed: ' . $e->getMessage());
        return response()->json(['message' => 'File deletion failed: ' . $e->getMessage()], 500);
    }
}







}
