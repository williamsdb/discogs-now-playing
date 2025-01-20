<?php

/**
 * What should I play next?
 * 
 * Display a random item from your Discogs collection to play.
 *
 * @author  Neil Thompson <hi@nei.lt>
 * @see     https://nei.lt/plexnp
 * @license GNU Lesser General Public License, version 3
 *
 * I've got a lot of records (At the time of writing 1,076 Discogs tells me)
 * and I always seem to gravitate to the same ones. Therefore, I decided I 
 * needed help selecting something to play so I wrote Now Playing to help guide me.
 * 
 **/

    class nowPlayingException extends Exception {}

    // set error handling
    error_reporting(E_NOTICE);
    ini_set('display_errors', 0);

    // have we got a config file?
    try {
        require __DIR__.'/config.php';
    } catch (\Throwable $th) {
        throw new nowPlayingException("config.php file not found. Have you renamed from config_dummy.php?.");
    }

    // create and connect to the SQLite database to hold the cached data
    try {
        // Specify the path and filename for the SQLite database
        $databasePath = './cache.sqlite';

        if (!file_exists($databasePath)) {
            // Create a new SQLite database or connect to an existing one
            $pdo = new PDO('sqlite:' . $databasePath);
        
            // Set error mode to exceptions
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
            // Create the necessary tables if they don't already exist
            $sql = "CREATE TABLE IF NOT EXISTS release (
                        id INTEGER PRIMARY KEY AUTOINCREMENT,
                        title TEXT NOT NULL,
                        artist TEXT NOT NULL,
                        year INTEGER,
                        image TEXT,
                        played INTEGER,
                        lastPlayedAt DATETIME
                    )";
            $pdo->exec($sql);
        }else{
            // Connect to an existing database
            $pdo = new PDO('sqlite:' . $databasePath);
        
            // Set error mode to exceptions
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        }
    } catch (PDOException $e) {
        die("Error: " . $e->getMessage());
    }


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

    // loop round until we get an entry of the type either LP or 12"
    while (!(in_array("LP", $desc) || in_array("12\"", $desc))) {

        // select a random item
        $rand = rand(1, $num);

        // work out what page it is on
        $page = intval($rand/10);

        // what's the number on the page
        $item = $rand - ($page*10);

        // get the page with the random entry on
        $ch = curl_init($endpoint."/users/{$username}/collection/folders/0/releases?page=".$page."&per_page=10");
        curl_setopt($ch, CURLOPT_USERAGENT, 'MyDiscogsClient/1.0 +https://nei.lt/now-playing');
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET' );
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Authorization: Discogs token={$token}"
        ]);

        $response = curl_exec($ch);
        curl_close($ch);

        $dets = json_decode($response);

        $release = $dets->releases[$item];

        if (isset($release->basic_information->formats[0]->descriptions)){
            $desc = $release->basic_information->formats[0]->descriptions;

            // get the master release information
            $ch = curl_init($release->basic_information->master_url);
            curl_setopt($ch, CURLOPT_USERAGENT, 'MyDiscogsClient/1.0 +https://nei.lt/now-playing');
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET' );
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                "Authorization: Discogs token={$token}"
            ]);

            $response = curl_exec($ch);
            curl_close($ch);

            $master = json_decode($response);

            // download and cache the image
            $masterUrl = $master->uri;
            $parsedMasterUrl = parse_url($masterUrl, PHP_URL_PATH);
            $masterStub = basename($parsedMasterUrl);

            $imgUrl = $release->basic_information->cover_image;
            // Remove query parameters if needed
            $parsedUrl = parse_url($imgUrl, PHP_URL_PATH);
            $img = basename($parsedUrl);

            // have we got an image?
            if (empty($img)){
                $img = 'nocoverart.jpeg';
            }else{
                // check to see if the image file is already cached
                if (file_exists('./cache/'.$masterStub.'-'.$img)){
                    // do nothing
                }else{
                    if ($image = file_get_contents($imgUrl)){
                        try {
                            file_put_contents('./cache/'.$masterStub.'-'.$img, $image);
                        } catch (\Throwable $th) {
                            //throw $th;
                        }
                    }else{
                        $img = 'nocoverart.jpeg';
                    }
                }
            }
        }else{
            $desc = [];
        }

    }

    // Check if the entry already exists in the database
    $stmt = $pdo->prepare("SELECT id, played FROM release WHERE title = :title AND artist = :artist");
    $stmt->execute([
        ':title' => $release->basic_information->title,
        ':artist' => $release->basic_information->artists[0]->name
    ]);
    $existingRelease = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($existingRelease) {
        // Update the existing entry
        $stmt = $pdo->prepare("UPDATE release SET played = played + 1, lastPlayedAt = :lastPlayedAt WHERE id = :id");
        $stmt->execute([
            ':lastPlayedAt' => date('Y-m-d H:i:s'),
            ':id' => $existingRelease['id']
        ]);
    } else {
        // Insert a new entry
        $stmt = $pdo->prepare("INSERT INTO release (title, artist, year, image, played, lastPlayedAt) VALUES (:title, :artist, :year, :image, :played, :lastPlayedAt)");
        $stmt->execute([
            ':title' => $release->basic_information->title,
            ':artist' => $release->basic_information->artists[0]->name,
            ':year' => $release->basic_information->year,
            ':image' => './cache/'.$masterStub.'-'.$img,
            ':played' => 1,
            ':lastPlayedAt' => date('Y-m-d H:i:s')
        ]);
    }
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, minimal-ui, user-scalable=no">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">

    <!-- Favicon -->
	<link rel="shortcut icon" type="image/png" href="favicon.png">
    <link rel="apple-touch-icon" sizes="57x57" href="/favicon-57x57.png">
    <link rel="apple-touch-icon" sizes="72x72" href="/favicon-72x72.png">
    <link rel="apple-touch-icon" sizes="114x114" href="/favicon-114x114.png">
    <link rel="apple-touch-icon" sizes="120x120" href="/favicon-120x120.png">
    <link rel="apple-touch-icon" sizes="152x152" href="/favicon-152x152.png">
    <link rel="apple-touch-icon" sizes="180x180" href="/favicon-180x180.png">

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
        a:visited, a:link {
            color: #FFFFFF;
        }
        .now-playing {
            text-align: center;
            background-color: #1DB954;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
            width: 320px; 
            cursor: pointer;
            position: relative;
        }
        .cover-art {
            width: 300px;
            height: 300px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        .loading-spinner {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 30px;
            height: 30px;
            border: 4px solid rgba(255, 0, 0, 0.5);
            border-top: 4px solid #fff;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            visibility: hidden; /* Initially hidden */
            z-index: 2; /* Ensure it appears above content */
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
        @keyframes spin {
            from {
                transform: translate(-50%, -50%) rotate(0deg);
            }
            to {
                transform: translate(-50%, -50%) rotate(360deg);
            }
        }
    </style>
</head>
<body>
    <div class="above-text"><h1>Go Play</h1></div>
    <div class="now-playing" onclick="openInNewTab('<?php echo $master->uri; ?>')">
        <div class="loading-spinner"></div>
        <img src="<?php echo './cache/'.$masterStub.'-'.$img; ?>" alt="Cover Art" class="cover-art">
        <div class="song-title"><?php echo $release->basic_information->title ?></div>
        <div class="artist"><?php echo $release->basic_information->artists[0]->name ?></div>
        <div class="release-year">Released: <?php echo $release->basic_information->year ?></div>
    </div>
    <div class="below-text">
        <p><a class="refresh" href="#" id="reloadLink" aria-haspopup="true" aria-expanded="false">Next selection</a></p>
        <small>Built by <a href="https://neilthompson.me">Neil Thompson</a>. <a href="stats.php">#</a></small>
    </div>

        <script>
        // Show the spinner
        function showSpinner() {
            const spinner = document.querySelector('.loading-spinner');
            if (spinner) {
                spinner.style.visibility = 'visible'; // Use visibility instead of display for compatibility
            }
        }

        // Hide the spinner
        function hideSpinner() {
            const spinner = document.querySelector('.loading-spinner');
            if (spinner) {
                spinner.style.visibility = 'hidden';
            }
        }

        // Open in a new tab
        function openInNewTab(url) {
            window.open(url, '_blank'); // Open URL in a new tab
        }

        // Reload the page
        document.getElementById('reloadLink').addEventListener('click', function (event) {
            event.preventDefault(); // Prevent default anchor behavior
            showSpinner(); // Show spinner
            setTimeout(function () {
                window.location.reload(); // Reload the page
            }, 100);
        });

        // Polyfill for `Element.closest` for older browsers
        if (!Element.prototype.closest) {
            Element.prototype.closest = function (selector) {
                var el = this;
                while (el) {
                    if (el.matches(selector)) return el;
                    el = el.parentElement || el.parentNode;
                }
                return null;
            };
        }
    </script></body>
</html>
