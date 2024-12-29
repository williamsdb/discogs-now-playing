<?php


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
            border-top: 4px solid #FFF;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            display: none; /* Hidden by default */
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
        <small>Built by <a href="https://neilthompson.me">Neil Thompson</a>.</small></div>

    <script>
        function openInNewTab(url) {
            window.open(url, '_blank'); // Open URL in a new tab
        }

        function showSpinner() {
            const spinner = document.querySelector('.loading-spinner');
            if (spinner) {
                spinner.style.display = 'block'; // Show the spinner
            }
        }

        function openInNewTab(url) {
            showSpinner(); // Show the spinner
            setTimeout(() => {
                window.open(url, '_blank'); // Open the URL in a new tab after a slight delay
                const spinner = document.querySelector('.loading-spinner');
                if (spinner) spinner.style.display = 'none'; // Optionally hide the spinner
            }, 100); // Add a small delay for spinner visibility
        }

        // Reload button event
        document.getElementById('reloadLink').addEventListener('click', function (event) {
            event.preventDefault(); // Prevent the default behavior of the anchor tag
            showSpinner(); // Show the spinner
            setTimeout(() => {
                window.location.reload(); // Reload the page after a slight delay
            }, 100);
        });
    </script>
</body>
</html>
