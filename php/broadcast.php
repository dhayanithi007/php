<?php



set_include_path("./google-api-php-client/src");
session_start();
require_once 'Google_Client.php';
require_once 'contrib/Google_YouTubeService.php';
require_once 'service/Google_ServiceResource.php';
/*
 * You can acquire an OAuth 2.0 client ID and client secret from the
 * {{ Google Cloud Console }} <{{ https://cloud.google.com/console }}>
 * For more information about using OAuth 2.0 to access Google APIs, please see:
 * <https://developers.google.com/youtube/v3/guides/authentication>
 * Please ensure that you have enabled the YouTube Data API for your project.
 */
$OAUTH2_CLIENT_ID = '1025119742306-m2p383u3chs61qmto36p39fjqoo4d6jf.apps.googleusercontent.com';
$OAUTH2_CLIENT_SECRET = 'CK-pcOT4INi8hTrx9DG_9A1S';

$client = new Google_Client();
$client->setClientId($OAUTH2_CLIENT_ID);
$client->setClientSecret($OAUTH2_CLIENT_SECRET);
$client->setScopes('https://www.googleapis.com/auth/youtube');
$redirect = filter_var('http://' . $_SERVER['HTTP_HOST'] . $_SERVER['PHP_SELF'],
    FILTER_SANITIZE_URL);
$client->setRedirectUri($redirect);

// Define an object that will be used to make all API requests.
$youtube = new Google_YoutubeService($client);

// Check if an auth token exists for the required scopes
$tokenSessionKey = 'token-';
if (isset($_GET['code'])) {
  if (strval($_SESSION['state']) !== strval($_GET['state'])) {
    die('The session state did not match.');
  }

  $client->authenticate($_GET['code']);
  $_SESSION[$tokenSessionKey] = $client->getAccessToken();
  header('Location: ' . $redirect);
}

if (isset($_SESSION[$tokenSessionKey])) {
  $client->setAccessToken($_SESSION[$tokenSessionKey]);
}

// Check to ensure that the access token was successfully acquired.
if ($client->getAccessToken()) {
  try {
    // Create an object for the liveBroadcast resource's snippet. Specify values
    // for the snippet's title, scheduled start time, and scheduled end time.
    $broadcastSnippet = new Google_YoutubeService_LiveBroadcast();
    $broadcastSnippet->setTitle('New Broadcast');
    $broadcastSnippet->setScheduledStartTime('2034-01-30T00:00:00.000Z');
    $broadcastSnippet->setScheduledEndTime('2034-01-31T00:00:00.000Z');

    // Create an object for the liveBroadcast resource's status, and set the
    // broadcast's status to "private".
    $status = new Google_YoutubeService_LiveBroadcastStatus();
    $status->setPrivacyStatus('private');

    // Create the API request that inserts the liveBroadcast resource.
    $broadcastInsert = new Google_YoutubeService_LiveBroadcast();
    $broadcastInsert->setSnippet($broadcastSnippet);
    $broadcastInsert->setStatus($status);
    $broadcastInsert->setKind('youtube#liveBroadcast');

    // Execute the request and return an object that contains information
    // about the new broadcast.
    $broadcastsResponse = $youtube->liveBroadcasts->insert('snippet,status',
        $broadcastInsert, array());

    // Create an object for the liveStream resource's snippet. Specify a value
    // for the snippet's title.
    $streamSnippet = new Google_YoutubeService_LiveStreamSnippet();
    $streamSnippet->setTitle('New Stream');

    // Create an object for content distribution network details for the live
    // stream and specify the stream's format and ingestion type.
    $cdn = new Google_YoutubeService_CdnSettings();
    $cdn->setFormat("1080p");
    $cdn->setIngestionType('rtmp');

    // Create the API request that inserts the liveStream resource.
    $streamInsert = new Google_YoutubeService_LiveStream();
    $streamInsert->setSnippet($streamSnippet);
    $streamInsert->setCdn($cdn);
    $streamInsert->setKind('youtube#liveStream');

    // Execute the request and return an object that contains information
    // about the new stream.
    $streamsResponse = $youtube->liveStreams->insert('snippet,cdn',
        $streamInsert, array());

    // Bind the broadcast to the live stream.
    $bindBroadcastResponse = $youtube->liveBroadcasts->bind(
        $broadcastsResponse['id'],'id,contentDetails',
        array(
            'streamId' => $streamsResponse['id'],
        ));

    $htmlBody .= "<h3>Added Broadcast</h3><ul>";
    $htmlBody .= sprintf('<li>%s published at %s (%s)</li>',
        $broadcastsResponse['snippet']['title'],
        $broadcastsResponse['snippet']['publishedAt'],
        $broadcastsResponse['id']);
    $htmlBody .= '</ul>';

    $htmlBody .= "<h3>Added Stream</h3><ul>";
    $htmlBody .= sprintf('<li>%s (%s)</li>',
        $streamsResponse['snippet']['title'],
        $streamsResponse['id']);
    $htmlBody .= '</ul>';

    $htmlBody .= "<h3>Bound Broadcast</h3><ul>";
    $htmlBody .= sprintf('<li>Broadcast (%s) was bound to stream (%s).</li>',
        $bindBroadcastResponse['id'],
        $bindBroadcastResponse['contentDetails']['boundStreamId']);
    $htmlBody .= '</ul>';

  } catch (Google_Service_Exception $e) {
    $htmlBody = sprintf('<p>A service error occurred: <code>%s</code></p>',
        htmlspecialchars($e->getMessage()));
  } catch (Google_Exception $e) {
    $htmlBody = sprintf('<p>An client error occurred: <code>%s</code></p>',
        htmlspecialchars($e->getMessage()));
  }

  $_SESSION[$tokenSessionKey] = $client->getAccessToken();
} elseif ($OAUTH2_CLIENT_ID == '1025119742306-m2p383u3chs61qmto36p39fjqoo4d6jf.apps.googleusercontent.com') {
  $htmlBody = <<<END
  <h3>Client Credentials Required</h3>
  <p>
    You need to set <code>\$OAUTH2_CLIENT_ID</code> and
    <code>\$OAUTH2_CLIENT_ID</code> before proceeding.
  <p>
END;
} else {
  // If the user hasn't authorized the app, initiate the OAuth flow
  $state = mt_rand();
  $client->setState($state);
  $_SESSION['state'] = $state;

  $authUrl = $client->createAuthUrl();
  $htmlBody = <<<END
  <h3>Authorization Required</h3>
  <p>You need to <a href="$authUrl">authorize access</a> before proceeding.<p>
END;
}
?>

<!doctype html>
<html>
<head>
<title>Bound Live Broadcast</title>
</head>
<body>
  <?=$htmlBody?>
</body>
</html>