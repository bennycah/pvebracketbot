<?php
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/config.php';

define('SHEETS_APPLICATION_NAME', 'PVE sheet bot backend client');
define('SHEETS_CLIENT_SECRET_PATH', __DIR__ . '/client_secret.json');
// If modifying these scopes, delete your previously saved credentials
// at SHEETS_CREDENTIALS_PATH (defined in config.php)
define('SHEETS_SCOPES', implode(' ', array(
  Google_Service_Sheets::SPREADSHEETS)
));

if (php_sapi_name() != 'cli') {
  throw new Exception('This application must be run on the command line.');
}

/**
 * Returns an authorized API client.
 * @return Google_Client the authorized client object
 */
function getClient() {
  $client = new Google_Client();
  $client->setApplicationName(SHEETS_APPLICATION_NAME);
  $client->setScopes(SHEETS_SCOPES);
  $client->setAuthConfig(SHEETS_CLIENT_SECRET_PATH);
  $client->setAccessType('offline');

  // Load previously authorized credentials from a file.
  $credentialsPath = expandHomeDirectory(SHEETS_CREDENTIALS_PATH);
  if (file_exists($credentialsPath)) {
    $accessToken = json_decode(file_get_contents($credentialsPath), true);
  } else {
    // Request authorization from the user.
    $authUrl = $client->createAuthUrl();
    printf("Open the following link in your browser:\n%s\n", $authUrl);
    print 'Enter verification code: ';
    $authCode = trim(fgets(STDIN));

    // Exchange authorization code for an access token.
    $accessToken = $client->fetchAccessTokenWithAuthCode($authCode);

    // Store the credentials to disk.
    if(!file_exists(dirname($credentialsPath))) {
      mkdir(dirname($credentialsPath), 0700, true);
    }
    file_put_contents($credentialsPath, json_encode($accessToken));
    printf("Credentials saved to %s\n", $credentialsPath);
  }
  $client->setAccessToken($accessToken);

  // Refresh the token if it's expired.
  if ($client->isAccessTokenExpired()) {
    $client->fetchAccessTokenWithRefreshToken($client->getRefreshToken());
    file_put_contents($credentialsPath, json_encode($client->getAccessToken()));
  }
  return $client;
}

/**
 * Expands the home directory alias '~' to the full path.
 * @param string $path the path to expand.
 * @return string the expanded path.
 */
function expandHomeDirectory($path) {
  $homeDirectory = getenv('HOME');
  if (empty($homeDirectory)) {
    $homeDirectory = getenv('HOMEDRIVE') . getenv('HOMEPATH');
  }
  return str_replace('~', realpath($homeDirectory), $path);
}

// Get the API client and construct the service object.
$client = getClient();
$service = new Google_Service_Sheets($client);

$spreadsheetId = $CONF['SHEET_TO_UPDATE'];

$commandtoRange = [
  'currentstate' => 'Updates!B2:F26'
  //,!countupdate is special-cased, below
];

//hardcode locations in the sheet - this could be looked up based on the rowindex of column B, but it's faster to hardcode. (saves a network round trip)
$slice2row = [ 
  '1.9' => '4'
  ,'2.9' => '5'
  ,'3.9' => '6'
  ,'4.9' => '7'
  ,'5.9' => '8'
  ,'1.8' => '10'
  ,'2.8' => '11'
  ,'3.8' => '12'
  ,'4.8' => '13'
  ,'5.8' => '14'
  ,'1.7' => '16'
  ,'2.7' => '17'
  ,'3.7' => '18'
  ,'4.7' => '19'
  ,'5.7' => '20'
  ,'1.6' => '22'
  ,'2.6' => '23'
  ,'3.6' => '24'
  ,'4.6' => '25'
  ,'5.6' => '26'
];


if (array_key_exists($argv[1], $commandtoRange)) {
  $range = $commandtoRange[$argv[1]];
  $response = $service->spreadsheets_values->get($spreadsheetId, $range);
  $values = $response->getValues();

  if (count($values) == 0) {
    print "No data found.\n";
  } else {
    foreach ($values as $row) {
      printf("%s %s%-3s\t%s %s\n", $row[0], $row[1], $row[2], $row[3], ($row[4] == ''?'':'(predicted:' + $row[4] + ')') );
    }
  }
} else if ($argv[1] == 'countupdate') { //!countupdate 2.9 338 "7/19/2018 6:22:00"
  
  $countcell = 'D' . $slice2row[$argv[2]];
  $updatetimecell = 'H' . $slice2row[$argv[2]];
  $fliptimecell = 'K' . $slice2row[$argv[2]];
  $flipcountcell = 'M' . $slice2row[$argv[2]];

  $count = $argv[3];
  $updatetime = $argv[4];

  $data = [
    ['range' => $countcell, 'values' => [[$count]]]
    ,['range' => $updatetimecell, 'values' => [[$updatetime]]]
  ];
  if($updatetime == 'flip'){
    $flipcount = $service->spreadsheets_values->get($spreadsheetId, $flipcountcell)[0][0];
    if(empty($flipcount)) {
      $flipcount = 1;
    }
    else {
      $flipcount += 1;
    }
    $data[] = ['range' => $fliptimecell, 'values' => [[$updatetime]]];
    $data[] = ['range' => $flipcountcell, 'values' => [[$flipcount]]];
  }
  $requestBody = new Google_Service_Sheets_BatchUpdateValuesRequest();
  $requestBody->setData($data);

  $requestBody->setValueInputOption('USER_ENTERED');
  $requestBody->setIncludeValuesInResponse(false);

  $response = $service->spreadsheets_values->batchUpdate($spreadsheetId, $requestBody);
  print "got it";

}
