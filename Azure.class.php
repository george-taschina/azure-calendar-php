 <?php 
require_once __DIR__ . '/vendor/autoload.php';

class Azure{

    private $accessToken;
    private $provider;
    
    public function __construct($accessToken = null){

        //CLIENT SECRET SCADE IL 18/08/2023
        $provider = new TheNetworg\OAuth2\Client\Provider\Azure([
            'clientId'          => '',
            'clientSecret'      => '',
            'redirectUri'       => '',
            //Optional
            'scopes'            => [],
            //Optional
            'defaultEndPointVersion' => '2.0'
        ]);

        $this->provider = $provider;

        $this->accessToken = $accessToken;
    }

    public function auth(){
        
        // Set to use v2 API, skip the line or set the value to Azure::ENDPOINT_VERSION_1_0 if willing to use v1 API
        $this->provider->defaultEndPointVersion = TheNetworg\OAuth2\Client\Provider\Azure::ENDPOINT_VERSION_2_0;
        
        $baseGraphUri = $this->provider->getRootMicrosoftGraphUri(null);
        $this->provider->scope = 'openid profile email offline_access Calendars.ReadWrite  ' . $baseGraphUri . '/User.Read';
        
        if (isset($_GET['code']) && isset($_SESSION['OAuth2.state']) && isset($_GET['state'])) {
            if ($_GET['state'] == $_SESSION['OAuth2.state']) {
                unset($_SESSION['OAuth2.state']);
        
                // Try to get an access token (using the authorization code grant)
                /** @var AccessToken $token */
                $token = $this->provider->getAccessToken('authorization_code', [
                    'scope' => $this->provider->scope,
                    'code' => $_GET['code'],
                ]);
        
                // Verify token
                // Save it to local server session data
                session_start();
                $_SESSION['accessToken'] = $token;

                return $token->getToken();
            } else {
                echo 'Invalid state';
        
                return null;
            }
        } else {
             // Check local server's session data for a token
             // and verify if still valid 
             /** @var ?AccessToken $token */
             //$token = $this->accessToken; // token cached in session data, null if not found;
            
             //if (isset($token)) {
              //  $me = $provider->get($provider->getRootMicrosoftGraphUri($token) . '/v1.0/me', $token);
              //  $userEmail = $me['mail'];
            
              //  if ($token->hasExpired()) {
              //      if (!is_null($token->getRefreshToken())) {
              //         $token = $provider->getAccessToken('refresh_token', [
              //              'scope' => $provider->scope,
              //              'refresh_token' => $token->getRefreshToken()
              //          ]);
              //      } else {
              //         $token = null;
              //     }
              //  }
            //}
            
            // If the token is not found in 
            //if (!isset($token)) {
                $authorizationUrl = $this->provider->getAuthorizationUrl(['scope' => $this->provider->scope]);
        
                $_SESSION['OAuth2.state'] = $this->provider->getState();
        
                header('Location: ' . $authorizationUrl);
        
                exit;
            //}

        
            return $token->getToken();
        }
    
    }

    public function createEvent($subject,$content,$startDateTime,$endDateTime,$emails){

        $attendees = array();
        foreach($emails as $email){
            $attendee = array(
                "emailAddress" => array(
                    "address" => $email,
                    "name" => explode("@",$email)[0]
                ),
                "type" => "required"
            );
            array_push($attendees, $attendee);
        }

        $url = "https://graph.microsoft.com/v1.0/me/calendar/events";

        $data = array(
            "subject" => $subject,
            "body" => array(
                "contentType" => "HTML",
                "content" => $content
            ),
            "start" => array(
                "dateTime" => $startDateTime,
                "timeZone" => "W. Europe Standard Time"
            ),
            "end" => array(
                "dateTime" => $endDateTime,
                "timeZone" => "W. Europe Standard Time"
            ),
            "location" => array(
                "displayName" => "b4web"
            ),
            "attendees" => $attendees
        );


        
        $result = $this->send_post_request($url,$data);


        return $result;
    }

    public function updateEvent($eventId,$subject,$content,$startDateTime,$endDateTime,$emails){

        $attendees = array();
        foreach($emails as $email){
            $attendee = array(
                "emailAddress" => array(
                    "address" => $email,
                    "name" => explode("@",$email)[0]
                ),
                "type" => "required"
            );
            array_push($attendees, $attendee);
        }

        $url = "https://graph.microsoft.com/v1.0/me/events/".$eventId;

        $data = array(
            "subject" => $subject,
            "body" => array(
                "contentType" => "HTML",
                "content" => $content
            ),
            "start" => array(
                "dateTime" => $startDateTime,
                "timeZone" => "W. Europe Standard Time"
            ),
            "end" => array(
                "dateTime" => $endDateTime,
                "timeZone" => "W. Europe Standard Time"
            ),
            "location" => array(
                "displayName" => "b4web"
            ),
            "attendees" => $attendees
        );

        $result = $this->send_patch_request($url,$data);

        
        return $result;
    }

    public function deleteEvent($eventId){
        $result = $this->send_delete_request("https://graph.microsoft.com/v1.0/me/events/".$eventId);
        
        return $result;
    }


    function send_post_request($url,$data){

        $ch = curl_init($url);
        $payload = json_encode($data);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type:application/json','Authorization:Bearer '.$this->accessToken));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $result = curl_exec($ch);
        $result = json_decode($result);
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return $result;
    }

    function send_delete_request($url)
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Authorization:Bearer '.$this->accessToken));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $result = curl_exec($ch);
        $result = json_decode($result);
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);


        return $result;
    }

    function send_patch_request($url, $data)
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PATCH");
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Authorization:Bearer '.$this->accessToken, 'Content-Type:application/json;odata.metadata=minimal;odata.streaming=true;IEEE754Compatible=false'));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $result = curl_exec($ch);
        $result = json_decode($result);
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return $result;
    }

    
    
    
}
