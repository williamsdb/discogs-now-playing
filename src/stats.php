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
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
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

        // Connect to an existing database
        $pdo = new PDO('sqlite:' . $databasePath);
    
        // Set error mode to exceptions
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    } catch (PDOException $e) {
        die("Error: " . $e->getMessage());
    }

    // Top ten artists based on highest number of plays
    $query2 = "SELECT artist, SUM(played) as total_plays FROM release GROUP BY artist ORDER BY total_plays DESC LIMIT 5";
    $topArtists = $pdo->query($query2)->fetchAll(PDO::FETCH_ASSOC);

    // Top ten titles based on highest number of plays
    $query3 = "SELECT title, SUM(played) as total_plays FROM release GROUP BY title ORDER BY total_plays DESC LIMIT 5";
    $topTitles = $pdo->query($query3)->fetchAll(PDO::FETCH_ASSOC);

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
            width: 500px; 
            cursor: pointer;
            position: relative;
            margin-bottom: 20px;
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
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        th, td {
            padding: 10px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        th {
            background-color: #1DB954;
            color: white;
        }
        tr:nth-child(even) { background-color: #169743; }
        tr:nth-child(odd) { background-color: #1DB954; }
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
    <div class="above-text"><h1>Go Play Stats</h1></div>
        
        <div class="now-playing">
            <h2>Top 10 Artists</h2>
            <table>
                <thead>
                    <tr>
                        <th>Artist</th>
                        <th>Total Plays</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($topArtists as $artist): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($artist['artist']); ?></td>
                            <td><?php echo htmlspecialchars($artist['total_plays']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="now-playing">
            <h2>Top 10 Titles</h2>
            <table>
                <thead>
                    <tr>
                        <th>Title</th>
                        <th>Total Plays</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($topTitles as $title): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($title['title']); ?></td>
                            <td><?php echo htmlspecialchars($title['total_plays']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <div class="below-text">
        <small>Built by <a href="https://neilthompson.me">Neil Thompson</a>. <a href="index.php">@</a></small>
    </div>

</body>
</html>
