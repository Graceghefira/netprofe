Echo.channel('data-updates')
    .listen('DataUpdated', (e) => {
        console.log('Data terbaru diterima:', e.data);
        // Anda bisa memperbarui tampilan pengguna dengan data baru di sini
    });
