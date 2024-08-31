<?php
// Ambil IP pengunjung
$visitorIp = $_SERVER['REMOTE_ADDR'];

// Ambil parameter 'isp' dari query string, gunakan IP pengunjung jika 'isp' tidak ada atau kosong
$isp = isset($_GET['isp']) && !empty($_GET['isp']) ? $_GET['isp'] : $visitorIp;

// URL untuk mendapatkan data IP
$lookupUrl = 'https://blackbox.ipinfo.app/lookup/' . urlencode($isp);

// Inisialisasi cURL untuk mendapatkan data IP
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $lookupUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
$lookupResponse = curl_exec($ch);
curl_close($ch);

// URL untuk permintaan kedua
$apiUrl = 'https://www.myip.expert/api/' . urlencode($isp) . '/';

// Inisialisasi cURL untuk permintaan kedua
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $apiUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

// Set header sesuai dengan yang diberikan
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3;q=0.7',
    'Accept-Language: en-US,en;q=0.9',
    'Connection: keep-alive',
    'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/127.0.0.0 Safari/537.36',
    'sec-ch-ua: "Not)A;Brand";v="99", "Google Chrome";v="127", "Chromium";v="127"',
    'sec-ch-ua-mobile: ?0',
    'sec-ch-ua-platform: "Windows"',
]);

// Eksekusi permintaan GET
$apiResponse = curl_exec($ch);
curl_close($ch);

// Buat array untuk hasil akhir
$result = [
    'VPN_PROXY' => $lookupResponse,
    'info' => json_decode($apiResponse, true)
];

// Tampilkan hasil sebagai JSON dengan format yang rapi
header('Content-Type: application/json');
echo json_encode($result, JSON_PRETTY_PRINT);

// URL Firebase Realtime Database untuk mendapatkan ID terakhir
$firebaseIdUrl = 'https://botgan-c4eba-default-rtdb.asia-southeast1.firebasedatabase.app/banip/last_id.json';

// Inisialisasi cURL untuk mendapatkan ID terakhir
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $firebaseIdUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
$lastIdResponse = curl_exec($ch);
curl_close($ch);

// Decode ID terakhir
$lastId = json_decode($lastIdResponse, true);
$nextId = $lastId ? $lastId['id'] + 1 : 1;

// Tambahkan ID baru ke data
$resultWithId = [
    'id' => $nextId,
    'VPN_PROXY' => $result['VPN_PROXY'],
    'info' => $result['info']
];

// Tentukan URL Firebase berdasarkan nilai VPN_PROXY
if (isset($result['VPN_PROXY'])) {
    if ($result['VPN_PROXY'] === 'Y') {
        $firebaseUrl = 'https://botgan-c4eba-default-rtdb.asia-southeast1.firebasedatabase.app/banip/' . $nextId . '.json';
    } elseif ($result['VPN_PROXY'] === 'N') {
        $firebaseUrl = 'https://botgan-c4eba-default-rtdb.asia-southeast1.firebasedatabase.app/realip/' . $nextId . '.json';
    } else {
        echo 'VPN_PROXY value is neither "Y" nor "N".';
        exit;
    }

    // Update ID terakhir di Firebase
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $firebaseIdUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['id' => $nextId]));
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
    curl_exec($ch);
    curl_close($ch);

    // Encode data dengan ID dalam format JSON
    $jsonData = json_encode($resultWithId);

    // Inisialisasi cURL untuk POST ke Firebase
    $ch = curl_init($firebaseUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT'); // Menggunakan PUT untuk mengganti data pada ID yang ditentukan
    curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));

    // Kirim request
    $response = curl_exec($ch);

    // Cek jika ada error
    if(curl_errno($ch)) {
        echo 'Error:' . curl_error($ch);
    }

    // Tutup cURL
    curl_close($ch);
}
?>