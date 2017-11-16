<?php
include 'define.php';
//ini_set('max_execution_time', 300);
ini_set('max_execution_time', 0); //untuk mengatasi fatal error timeout
//yang di dapat dari bapak bot :
// aktifkan ini jika perlu debugging
$debug = false;

// fungsi untuk mengirim/meminta/memerintahkan sesuatu ke bot
function request_url($method)
{
    global $TOKEN;
    return "https://api.telegram.org/bot" . $TOKEN . "/". $method;
}

// fungsi untuk meminta pesan
// bagian ebook di sesi Meminta Pesan, polling: getUpdates
function get_updates($offset)
{
    $url = request_url("getUpdates")."?offset=".$offset;
        $resp = file_get_contents($url);
        $result = json_decode($resp, true);
        if ($result["ok"]==1)
            return $result["result"];
        return array();
}
// fungsi untuk mebalas pesan,
// bagian ebook Mengirim Pesan menggunakan Metode sendMessage
function send_reply($chatid, $msgid, $text)
{
    global $debug;
    $data = array(
        'chat_id' => $chatid,
        'text'  => $text,
        'reply_to_message_id' => $msgid   // <---- biar ada reply nya balasannya, opsional, bisa dihapus baris ini
    );
    // use key 'http' even if you send the request to https://...
    $options = array(
        'http' => array(
            'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
            'method'  => 'POST',
            'content' => http_build_query($data),
        ),
    );
    $context  = stream_context_create($options);
    $result = file_get_contents(request_url('sendMessage'), false, $context);
    if ($debug)
        print_r($result);
}

// fungsi mengolahan pesan, menyiapkan pesan untuk dikirimkan
function create_response($text, $message)
{
    global $usernamebot;
    // inisiasi variable hasil yang mana merupakan hasil olahan pesan
    $hasil = '';
    $fromid = $message["from"]["id"]; // variable penampung id user
    $chatid = $message["chat"]["id"]; // variable penampung id chat
    $pesanid= $message['message_id']; // variable penampung id message
    // variable penampung username nya user
    isset($message["from"]["username"])
        ? $chatuser = $message["from"]["username"]
        : $chatuser = '';

    // variable penampung nama user
    isset($message["from"]["last_name"])
        ? $namakedua = $message["from"]["last_name"]
        : $namakedua = '';
    $namauser = $message["from"]["first_name"]. ' ' .$namakedua;
    // ini saya pergunakan untuk menghapus kelebihan pesan spasi yang dikirim ke bot.
    $textur = preg_replace('/\s\s+/', ' ', $text);
    // memecah pesan dalam 2 blok array, kita ambil yang array pertama saja
    $command = explode(' ',$textur,2); //
   // identifikasi perintah (yakni kata pertama, atau array pertamanya)
    switch ($command[0]) {
        // jika ada pesan /id, bot akan membalas dengan menyebutkan idnya user
        case '/id':
        case '/id'.$usernamebot : //dipakai jika di grup yang haru ditambahkan @usernamebot
            $hasil = "$namauser, ID kamu adalah $fromid";
            break;

        // jika ada permintaan waktu
        case '/time':
        case '/time'.$usernamebot :
            $hasil  = "$namauser, waktu lokal bot sekarang adalah :\n";
            $hasil .= date("d M Y")."\nPukul ".date("H:i:s");
            break;
		case '/halo':
		case '/halo'.$usernamebot:
			$hasil = "Hai $namauser, Saya adalah bot ciptaan Juno\n";
			break;
    case '/antrian':
  	case '/antrian'.$usernamebot:

        $file = file_get_contents("http://api.rsjd-sujarwadi.com/antrian_poli/json/");
        $json = json_decode($file, true);
        $result = array();
               foreach( $json as $row ) {
                 $result[] = $row['nama_bagian'].', No. Antrian '.$row['sdh_panggil'].' dari '.$row['jml_pasien'];
               };
        //print_r($result);
        $result_string = implode("\n",$result);


  			$hasil = "Hai $namauser, Berikut Situasi Antrian Saat ini\n $result_string";
  			break;
		case '/guide':
		case '/guide'.$usernamebot:
			$hasil = "$namauser, berikut perintah yang bisa saya lakukan :\n".
			"/guide = List Perintah \n".
      "/halo = Memperkenalkan diri \n".
			"/antrian = Menampilkan Antrian Semua Poli\n".
			"/antrian1 = Menampilkan Antrian Poli Jiwa\n".
			"/antrian2 = Menampilkan Antrian Poli Dalam\n".
			"/antrian3 = Menampilkan Antrian Poli Syaraf 1\n".
			"/antrian4 = Menampilkan Antrian Poli Syaraf 2\n".
			"/antrian5 = Menampilkan Antrian Poli Gigi\n";
			break;

        // balasan default jika pesan tidak di definisikan
        default:
            $hasil = 'Mohon maaf, perintah tidak dikenali.';
            break;
    }
    return $hasil;
}

// jebakan token, klo ga diisi akan mati
// boleh dihapus jika sudah mengerti
//if (strlen($TOKEN)<20)
  //  die("Token mohon diisi dengan benar!\n");
// fungsi pesan yang sekaligus mengupdate offset
// biar tidak berulang-ulang pesan yang di dapat
function process_message($message)
{
    $updateid = $message["update_id"];
    $message_data = $message["message"];
    if (isset($message_data["text"])) {
    $chatid = $message_data["chat"]["id"];
        $message_id = $message_data["message_id"];
        $text = $message_data["text"];
        $response = create_response($text, $message_data);
        if (!empty($response))
          send_reply($chatid, $message_id, $response);
    }
    return $updateid;
}

// hapus baris dibawah ini, jika tidak dihapus berarti kamu kurang teliti!
//die("Mohon diteliti ulang codingnya..\nERROR: Hapus baris atau beri komen line ini yak!\n");

// hanya untuk metode poll
// fungsi untuk meminta pesan
// baca di ebooknya, yakni ada pada proses 1
function process_one()
{
    global $debug;
    $update_id  = 0;
    echo "-";

    if (file_exists("last_update_id"))
        $update_id = (int)file_get_contents("last_update_id");

    $updates = get_updates($update_id);
    // jika debug=0 atau debug=false, pesan ini tidak akan dimunculkan
    if ((!empty($updates)) and ($debug) )  {
        echo "\r\n===== isi diterima \r\n";
        print_r($updates);
    }

    foreach ($updates as $message)
    {
        echo '+';
        $update_id = process_message($message);
    }

    // update file id, biar pesan yang diterima tidak berulang
    file_put_contents("last_update_id", $update_id + 1);
}
// metode poll
// proses berulang-ulang
// sampai di break secara paksa
// tekan CTRL+C jika ingin berhenti

while (true) {
    process_one();
    sleep(1);
}
// metode webhook
// secara normal, hanya bisa digunakan secara bergantian dengan polling
// aktifkan ini jika menggunakan metode webhook

$entityBody = file_get_contents('php://input');
$pesanditerima = json_decode($entityBody, true);
process_message($pesanditerima);


?>
