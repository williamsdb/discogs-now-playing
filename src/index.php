<?php

// set error handling
error_reporting(E_NOTICE);
ini_set('display_errors', 0);

try {
    require __DIR__.'/config.php';
} catch (\Throwable $th) {
    die('config.php file not found. Have you renamed from config_dummy.php?');
}

$endpoint = "https://api.discogs.com/";

// get the user details
$ch = curl_init($endpoint."/users/{$username}");
curl_setopt($ch, CURLOPT_USERAGENT, 'MyDiscogsClient/1.0 +https://nei.lt');
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET' );
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Authorization: Discogs token={$token}"
]);

$response = curl_exec($ch);
curl_close($ch);

$dets = json_decode($response);

// find the number of items in the collection
$num = $dets->num_collection;


$desc = [];

while (!(in_array("LP", $desc) || in_array("12\"", $desc))) {
    // select a random item
    $rand = rand(1, $num);

    // work out what page it is on
    $page = intval($rand/10);

    // what's the number on the page
    $item = $rand - ($page*10);

    $ch = curl_init($endpoint."/users/{$username}/collection/folders/0/releases?page=".$page."&per_page=10");
    curl_setopt($ch, CURLOPT_USERAGENT, 'MyDiscogsClient/1.0 +https://nei.lt');
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET' );
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Discogs token={$token}"
    ]);

    $response = curl_exec($ch);
    curl_close($ch);

    $dets = json_decode($response);
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    $release = $dets->releases[$item];

    $ch = curl_init($release->basic_information->master_url);
    curl_setopt($ch, CURLOPT_USERAGENT, 'MyDiscogsClient/1.0 +https://nei.lt');
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET' );
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Discogs token={$token}"
    ]);

    $response = curl_exec($ch);
    curl_close($ch);

    $res = json_decode($response);

    $desc = $release->basic_information->formats[0]->descriptions;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- Favicon -->
	<link rel="shortcut icon" type="image/png" href="favicon.png">
	<link rel="apple-touch-icon" href="favicon.png">

    <title>Now Playing</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #121212;
            color: #FFFFFF;
            display: flex;
            flex-direction: column; /* Arrange elements in a column */
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
            text-align: center; /* Center-align text by default */
        }
        .now-playing {
            text-align: center;
            background-color: #1DB954;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
            width: 320px; 
            cursor: pointer;
        }
        .cover-art {
            width: 300px;
            height: 300px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        .song-title {
            font-size: 24px;
            font-weight: bold;
            margin: 10px 0;
            word-wrap: break-word; 
            overflow-wrap: break-word; 
        }
        .artist {
            font-size: 20px;
            margin: 5px 0;
        }
        .release-year {
            font-size: 16px;
            color: #B3B3B3;
        }
        .above-text,
        .below-text {
            font-size: 18px;
            margin: 10px 0;
        }
        h1 {
            font-size: 36px;
        }
        .refresh {
            display: inline-block;
            padding: 10px 20px;
            background-color: #007bff;
            color: #fff;
            text-decoration: none;
            border-radius: 5px;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="above-text"><h1>Go Play</h1></div>
    <div class="now-playing" onclick="openInNewTab('<?php echo $res->uri; ?>')">
        <img src="<?php echo $release->basic_information->cover_image ?>" alt="Cover Art" class="cover-art">
        <div class="song-title"><?php echo $release->basic_information->title ?></div>
        <div class="artist"><?php echo $release->basic_information->artists[0]->name ?></div>
        <div class="release-year">Released: <?php echo $release->basic_information->year ?></div>
    </div>
    <div class="below-text">
        <p><a class="refresh" href="#" id="reloadLink" aria-haspopup="true" aria-expanded="false">Refresh</a></p>
        <small>Built by <a href="https://neilthompson.me">Neil Thompson</a>.</small></div>

    <script>
        function openInNewTab(url) {
            window.open(url, '_blank'); // Open URL in a new tab
        }

        // Window reload event
        document.getElementById('reloadLink').addEventListener('click', function(event) {
            event.preventDefault(); // Prevent the default behavior of the anchor tag
            window.location.reload(); // Reload the page
        });
    </script>
</body>
</html>
