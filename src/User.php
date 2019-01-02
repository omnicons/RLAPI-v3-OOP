<?php

include_once '../vendor/autoload.php'; //I happen to be an idiot, this file is installed with Composer
include_once './SentrySys.php'; // SentrySys by Sxribe
class User
{


  use Ramsey\Uuid\Uuid;
  use Ramsey\Uuid\Exception\UnsatisfiedDependencyException;

  public $username;
  public $email;
  public $password;
  public $userid;
  public $token;
  public $tier;
  public $userDetails;
  public $isLoggedIn;
  public $isAdmin;
  public $isBlocked;
  //TODO: add more variables

  public function __construct(string $username,string $password = null)
  {
    include '../inc/development_db_password.inc.php';
    $dbconn = pg_connect("host=localhost port=5432 dbname=rlapi_devel user=rlapi_devel password=" . $dbPass); //Note, $dbPass is defined in development_db_password.inc.php
    $this->sentry_instance = new SentryInstance('API-KEY-HERE!'); // Get api key from Sxribe#1182
  }

  /* Functions related to user detail fetching */

  public function getUserIdByApiKey(string $apikey)
  {
    //TODO: make it work
  }
  
  public function getUserById(mixed $id)
  {
    //TODO: make it work
  }

  public function getUserByEmail(string $email)
  {
    //TODO: make it work
  }

  public function getUserByUsername(string $username)
  {
    $this->username = htmlspecialchars($username);
    $prepareStatement = pg_prepare($dbconn, "get_user_by_username", "SELECT * FROM users WHERE username = $1");
    $executePreparedStatement = pg_execute($dbconn, "get_user_by_username", $this->username);

    if($prepareStatement !== false && $executePreparedStatement !== false)
    {
      $this->userDetails = pg_fetch_object($executePreparedStatement);
    }
    else
    {
      return json_encode(array('success' => false, 'message' => 'Error! getUserByName failed, either prepareStatement or executePreparedStatement didnt work!'));
      $this->sentry_instance->log_error('getUserByName failed, either prepareStatement or executePreparedStatement didnt work! Time: ' . gmdate("Y-m-d H:i:s", time()));
    }
  }

  /* Functions Related to creating and deleting users */

  public function createUser(string $username, string $password, string $email)
  {
    // Sanitize
    $this->username = htmlspecialchars($username);
    $this->email = htmlspecialchars($email);
    // Encrypt Password
    $this->password = password_hash(htmlentities($password), PASSWORD_BCRYPT);
    unset($password); // We dont want to store the password in the code

    // Create User ID
    $this->userid = Uuid::uuid4();
    $this->userid = $this->userid->toString();

    // Add the user to DB
    $preparedStatement = pg_prepare($dbconn, "create_user", "INSERT INTO users ('id', 'username', 'password', 'email', 'tier', 'is_admin', 'is_blocked') VALUES ($1, $2, $3, $4, 'free', false, false)");
    $executePreparedStatement =  pg_execute($dbconn, "create_user", array($this->userid, $this->username, $this->password, $this->email));

    if(pg_result_status($executePreparedStatement) == 1 || pg_result_status($executePreparedStatement) == 6)
    {
      return json_encode(array('success' => true, 'status' => 'created', 'account' => array('id' => $this->userid, 'username' => $this->username, 'email' => $this->email)));
    }
    else
    {
      return json_encode(array('success' => false, 'message' => 'Something went horribly wrong while inserting the user into the database! Check the logs!'));
      $this->sentry_instance->log_error('Something went horribly wrong while inserting the user into the database! Check the logs! Time: ' . gmdate("Y-m-d H:i:s", time()));
    }
  }

  public function deleteUser(mixed $id, string $email)
  {
    $this->userid = htmlspecialchars($id);
    $this->email = htmlspecialchars($email);

    $preparedStatement = pg_prepare($dbconn, "delete_user", "DELETE FROM users WHERE id = $1 AND email = $2");
    $executePreparedStatement = pg_execute($dbconn, "delete_user", array($this->userid, $this->email));

    $prepareStatementApiKeys = pg_prepare($dbconn, "delete_user_api_keys", "DELETE FROM tokens WHERE user_id = $1");
    $executePreparedStatementApiKeys = pg_execute($dbconn, "delete_user_api_keys", array($this->userid));

    if(pg_result_status($executePreparedStatement) == 1 || pg_result_status($executePreparedStatement) == 6 && pg_result_status($executePreparedStatementApiKeys) == 1 || pg_result_status($executePreparedStatementApiKeys) == 6)
    {
      return json_encode(array('success' => true, 'account' => array('deleted' => true)));
    }
    else
    {
      return json_encode(array('success' => false, 'message' => 'Something went horribly wrong while deleting the user from the database! Check the logs!'));
      $this->sentry_instance->log_error('Something went horribly wrong while deleting the user from the database! Check the logs! Time: ' . gmdate("Y-m-d H:i:s", time()));
    }
  }

  /* Functions Regarding API Keys (Tokens) */

  public function createUserAPIKey(mixed $id)
  {
    $this->userid = $id;
    $unique = false;
    while ($unique == false)
    {
      $apikey = Uuid::uuid4();
      $apikey = $apikey->toString();

      $prepareStatement = pg_prepare($dbconn, "check_if_api_key_exists", "SELECT * FROM tokens WHERE token = $1");
      $executePreparedStatement = pg_execute($dbconn, "check_if_api_key_exists", array($apikey));
      $numberOfRows = pg_num_rows($executePreparedStatement);
      if($numberOfRows == 0)
      {
        $unique = true;
      }
    }
    $prepareStatement = pg_prepare($dbconn, "instert_api_key", "INSERT INTO tokens ('user_id', 'token') VALUES ($1, $2)");
    $executePreparedStatement = pg_execute($dbconn, "insert_api_key", $this->userid, $apikey);

    if($prepareStatement !== false && $executePreparedStatement !== false)
    {
      //success?
    }
    else
    {
      return json_encode(array('success' => false, 'message' => 'There was an oopsie. Check logs (ln 140)'));
      $this->sentry_instance->log_error('There was an oopsie. Check logs (ln 140) Time: ' . gmdate("Y-m-d H:i:s", time()));
    }
  }

  public function deleteUserAPIKey(string $apikey, mixed $id, string $email)
  {
    $this->userid = htmlspecialchars($id);
    $this->email = htmlspecialchars($email);
    $this->token = htmlspecialchars($apikey);

    $prepareStatement = pg_prepare($dbconn, "delete_api_key", "DELETE FROM tokens WHERE user_id = $1 AND token = $2");
    $executePreparedStatement = pg_execute($dbconn, "delete_api_key", array($this->userid, $this->token));

    if($prepareStatement !== false && $executePreparedStatement !== false)
    {
      //success
    }
    else
    {
      return json_encode(array('success' => false, 'message' => 'there was an oopsie. Check the logs (ln 159)'));
      $this->sentry_instance->log_error('There was an oopsie. Check logs (ln 159) Time: ' . gmdate("Y-m-d H:i:s", time()));
    }
  }

  /* Other user-related functions */

  public function setUserTier(mixed $id, string $email, string $tier)
  {
    //TODO
  }
}

?>
